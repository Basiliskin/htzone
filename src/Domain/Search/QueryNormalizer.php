<?php

declare(strict_types=1);

namespace Htzone\Domain\Search;

/**
 * SearchQuery → search terms (architecture §4): split like the index, drop stop-words, then give each word
 * its variants — Hebrew prefixes and plurals stripped, final letters restored (D32), aliases added.
 * Every guess gets a lower strength (D31), so the Ranker can prefer the word as typed.
 */
final class QueryNormalizer
{
    private const PREFIX_LETTERS = ['ו', 'ה', 'ב', 'ל', 'מ', 'ש', 'כ'];
    private const MAX_PREFIXES = 2;
    /** suffix → strength penalty; ־י is the construct plural (שואבי אבק) and a weaker guess */
    private const HEBREW_SUFFIXES = ['יות' => 0.1, 'ות' => 0.1, 'ים' => 0.1, 'י' => 0.2];
    private const FINAL_LETTERS = ['כ' => 'ך', 'מ' => 'ם', 'נ' => 'ן', 'פ' => 'ף', 'צ' => 'ץ'];
    private const PREFIX_PENALTY = 0.15;
    private const ENGLISH_PLURAL_PENALTY = 0.1;
    private const MIN_STRIPPED_LENGTH = 3; // D31: "של" from "לבשל" is noise

    /**
     * @return list<SearchTerm>
     */
    public function normalize(SearchQuery $query): array
    {
        $terms = [];
        foreach (Tokenizer::words($query->text) as $word) {
            if (!isset($terms[$word])) {
                $term = self::term($word);
                if ($term !== null) {
                    $terms[$word] = $term;
                }
            }
        }

        return array_values($terms);
    }

    private static function term(string $word): ?SearchTerm
    {
        if (preg_match('/^\p{N}+\p{L}{0,3}$/u', $word) === 1) {
            return new SearchTerm($word, [new Variant($word, 1.0)], isQualifier: true);
        }
        if (mb_strlen($word) < 2 || StopWords::contains($word)) {
            return null;
        }

        $variants = [$word => 1.0];
        if (preg_match('/^\p{Hebrew}+$/u', $word) === 1) {
            $prefixForms = Aliases::of($word) === null ? self::prefixForms($word) : [$word => 1.0];
            foreach (array_keys($prefixForms) as $form) {
                if (StopWords::contains($form)) {
                    return null;
                }
            }
            $variants = $prefixForms;
            foreach ($prefixForms as $form => $strength) {
                $variants = self::add($variants, self::hebrewPluralStems($form, $strength));
            }
        } elseif (preg_match('/^[a-z]+$/', $word) === 1) {
            $stem = self::englishSingular($word);
            if ($stem !== null) {
                $variants = self::add($variants, [$stem => 1.0 - self::ENGLISH_PLURAL_PENALTY]);
            }
        }

        foreach ($variants as $text => $strength) {
            $alias = Aliases::of($text);
            if ($alias !== null) {
                $variants = self::add($variants, [$alias => $strength]);
            }
        }

        unset($variants[$word]);
        $list = [new Variant($word, 1.0)];
        foreach ($variants as $text => $strength) {
            $list[] = new Variant($text, $strength);
        }

        return new SearchTerm($word, $list, isQualifier: false);
    }

    /**
     * מהספה → [מהספה 1.0, הספה 0.85, ספה 0.7]
     *
     * @return array<string, float>
     */
    private static function prefixForms(string $word): array
    {
        $forms = [$word => 1.0];
        $form = $word;
        for ($stripped = 1; $stripped <= self::MAX_PREFIXES; ++$stripped) {
            $rest = mb_substr($form, 1);
            if (!in_array(mb_substr($form, 0, 1), self::PREFIX_LETTERS, true) || mb_strlen($rest) < self::MIN_STRIPPED_LENGTH) {
                break;
            }
            $forms[$rest] = 1.0 - $stripped * self::PREFIX_PENALTY;
            $form = $rest;
        }

        return $forms;
    }

    /**
     * שערות → שער; ארונות → ארונ + ארון (D32: the index spells the word with a final letter)
     *
     * @return array<string, float>
     */
    private static function hebrewPluralStems(string $form, float $strength): array
    {
        $stems = [];
        foreach (self::HEBREW_SUFFIXES as $suffix => $penalty) {
            $stem = mb_substr($form, 0, -mb_strlen($suffix));
            if (str_ends_with($form, $suffix) && mb_strlen($stem) >= self::MIN_STRIPPED_LENGTH) {
                $stems[$stem] = $strength - $penalty;
                $final = self::FINAL_LETTERS[mb_substr($stem, -1)] ?? null;
                if ($final !== null) {
                    $stems[mb_substr($stem, 0, -1) . $final] = $strength - $penalty;
                }
            }
        }

        return $stems;
    }

    private static function englishSingular(string $word): ?string
    {
        $stem = match (true) {
            str_ends_with($word, 'ies') => substr($word, 0, -3) . 'y',
            preg_match('/(s|x|ch|sh)es$/', $word) === 1 => substr($word, 0, -2),
            str_ends_with($word, 's') && !str_ends_with($word, 'ss') => substr($word, 0, -1),
            default => null,
        };

        return $stem !== null && strlen($stem) >= self::MIN_STRIPPED_LENGTH ? $stem : null;
    }

    /**
     * Appends new variants in order; a repeated text keeps its strongest strength.
     *
     * @param array<string, float> $variants
     * @param array<string, float> $more
     * @return array<string, float>
     */
    private static function add(array $variants, array $more): array
    {
        foreach ($more as $text => $strength) {
            $variants[$text] = max($variants[$text] ?? 0.0, $strength);
        }

        return $variants;
    }
}
