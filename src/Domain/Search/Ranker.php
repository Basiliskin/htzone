<?php

declare(strict_types=1);

namespace Htzone\Domain\Search;

use Htzone\Domain\Catalog\Product;

/**
 * Re-ranks the index's candidates with rules the index can't express (architecture §6).
 * Pure: the same candidates and terms always give the same order.
 */
final readonly class Ranker
{
    public function __construct(private RankingWeights $weights = new RankingWeights())
    {
    }

    /**
     * @param list<Candidate>  $candidates
     * @param list<SearchTerm> $terms
     * @return list<RankedProduct>
     */
    public function rank(array $candidates, array $terms): array
    {
        $words = array_values(array_filter($terms, static fn (SearchTerm $t): bool => !$t->isQualifier));
        $qualifiers = array_values(array_filter($terms, static fn (SearchTerm $t): bool => $t->isQualifier));
        if ($words === []) {        // a query of numbers only: the numbers are all we have
            [$words, $qualifiers] = [$qualifiers, []];
        }
        if ($candidates === [] || $words === []) {
            return [];
        }

        $base = $this->base($candidates);
        $scored = [];
        foreach ($candidates as $i => $candidate) {
            $scored[] = $this->score($candidate->product, $base[$i], $words, $qualifiers);
        }

        // Rule 6: products found only through a number are dropped once anything matches a real word.
        $anyWordMatch = array_filter($scored, static fn (array $s): bool => $s['coverage'] > 0.0) !== [];
        $kept = array_filter($scored, static fn (array $s): bool => !$anyWordMatch || $s['coverage'] > 0.0 || !$s['qualifierHit']);

        return $this->top(array_map(static fn (array $s): RankedProduct => new RankedProduct($s['product'], $s['score']), $kept));
    }

    /**
     * Typo fallback (D15): keep candidates with a word spelled close to each query word.
     *
     * @param list<Candidate> $candidates from the trigram index
     * @param list<string>    $words      the query words being corrected
     * @return list<RankedProduct>
     */
    public function rankFuzzy(array $candidates, array $words): array
    {
        if ($candidates === [] || $words === []) {
            return [];
        }

        $base = $this->base($candidates);
        $ranked = [];
        foreach ($candidates as $i => $candidate) {
            $product = $candidate->product;
            $productWords = array_values(array_filter(
                array_unique(Tokenizer::words(implode(' ', [$product->name, $product->brand ?? '', ...$product->tags]))),
                static fn (string $w): bool => mb_strlen($w) >= 3,
            ));
            $total = 0.0;
            $corrected = [];
            foreach ($words as $word) {
                $word = mb_strtolower($word);
                [$match, $similarity] = Trigrams::closest($word, $productWords) ?? [$word, 0.0];
                $close = $similarity >= $this->weights->minSimilarity;
                $total += $close ? $similarity : 0.0;
                $corrected[] = $close ? $match : $word;
            }
            if ($total > 0.0) {
                $score = $this->weights->coverage * $total / count($words) + $this->weights->base * $base[$i];
                $ranked[] = new RankedProduct($product, $score, implode(' ', $corrected));
            }
        }

        return $this->top($ranked);
    }

    /**
     * @param list<SearchTerm> $words
     * @param list<SearchTerm> $qualifiers
     * @return array{product: Product, score: float, coverage: float, qualifierHit: bool}
     */
    private function score(Product $product, float $base, array $words, array $qualifiers): array
    {
        $nameWords = array_values(array_unique(Tokenizer::words($product->name)));
        $categoryWords = array_values(array_unique(Tokenizer::words($product->category . ' ' . $product->subcategory)));
        $fieldWords = array_values(array_unique([
            ...$nameWords,
            ...$categoryWords,
            ...Tokenizer::words(implode(' ', [$product->brand ?? '', ...$product->tags])),
        ]));

        $coverage = $exact = $inName = $inCategory = 0.0;
        foreach ($words as $term) {
            $best = self::bestStrength($term, $fieldWords);
            $coverage += $best;
            $exact += $best >= 1.0 ? 1.0 : 0.0;
            $inName += self::bestStrength($term, $nameWords);
            $inCategory += self::bestStrength($term, $categoryWords);
        }
        $count = count($words);
        $coverage /= $count;

        $qualifierHits = 0;
        if ($qualifiers !== []) {
            $qualifierWords = [...$fieldWords, ...Tokenizer::words($product->attributes)];
            foreach ($qualifiers as $term) {
                $qualifierHits += self::bestStrength($term, $qualifierWords) > 0.0 ? 1 : 0;
            }
        }

        $brand = $product->brand === null ? '' : mb_strtolower($product->brand);
        $brandHit = false;
        foreach ($words as $term) {
            $brandHit = $brandHit || in_array($brand, $term->texts(), true);
        }

        $w = $this->weights;
        $score = $w->base * $base
            + $w->coverage * $coverage
            + $w->exact * $exact / $count
            + $w->name * $inName / $count
            + $w->category * $inCategory / $count
            + ($brandHit ? $w->brand : 0.0)
            + ($coverage > 0.0 && $qualifiers !== [] ? $w->qualifier * $qualifierHits / count($qualifiers) : 0.0);

        return ['product' => $product, 'score' => $score, 'coverage' => $coverage, 'qualifierHit' => $qualifierHits > 0];
    }

    /**
     * The strongest variant of the term that starts a word (like the index's prefix search);
     * numbers must match a whole word, so "16" doesn't match "160".
     *
     * @param list<string> $words
     */
    private static function bestStrength(SearchTerm $term, array $words): float
    {
        $best = 0.0;
        foreach ($term->variants as $variant) {
            if ($variant->strength <= $best) {
                continue;
            }
            foreach ($words as $word) {
                $hit = $term->isQualifier || mb_strlen($variant->text) < 2
                    ? $word === $variant->text
                    : str_starts_with($word, $variant->text);
                if ($hit) {
                    $best = $variant->strength;
                    break;
                }
            }
        }

        return $best;
    }

    /**
     * Rule 1: −bm25 scaled to 0..1 across the candidates (all equal → 0).
     *
     * @param non-empty-list<Candidate> $candidates
     * @return list<float>
     */
    private function base(array $candidates): array
    {
        $relevance = array_map(static fn (Candidate $c): float => -$c->bm25, $candidates);
        $min = min($relevance);
        $range = max($relevance) - $min;

        return array_map(static fn (float $r): float => $range > 0.0 ? ($r - $min) / $range : 0.0, $relevance);
    }

    /**
     * Rules 7–8: best score first, ties by id, at most `limit`.
     *
     * @param array<RankedProduct> $ranked
     * @return list<RankedProduct>
     */
    private function top(array $ranked): array
    {
        usort($ranked, static fn (RankedProduct $a, RankedProduct $b): int => [$b->score, $a->product->id] <=> [$a->score, $b->product->id]);

        return array_slice($ranked, 0, $this->weights->limit);
    }
}
