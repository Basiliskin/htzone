<?php

declare(strict_types=1);

namespace Htzone\Http\Controller;

use Htzone\Application\SearchProducts;
use Htzone\Domain\Search\InvalidFilter;
use Htzone\Domain\Search\InvalidQuery;
use Htzone\Domain\Search\SearchFilter;
use Htzone\Domain\Search\SearchQuery;
use Htzone\Http\ApiError;
use Htzone\Http\Controller;
use Htzone\Http\Request;
use Htzone\Http\Response;
use Htzone\Http\SearchResultJson;

/**
 * GET /api/search?q=&cat=&min=&max= — validates the input, runs the use case, returns JSON (thin: no search logic here).
 */
final readonly class SearchController implements Controller
{
    /** Search results change only on deploy; a minute of browser/CDN caching is safe (architecture §8). */
    private const CACHE = 'public, max-age=60';

    public function __construct(private SearchProducts $search)
    {
    }

    public function handle(Request $request): Response
    {
        $raw = $request->queryString('q');
        if ($raw === null) {
            return Response::error(ApiError::QueryMissing);
        }

        try {
            $query = SearchQuery::fromRaw($raw);
        } catch (InvalidQuery) {
            return Response::error(ApiError::QueryTooShort)->withLog(['q' => mb_substr(mb_scrub($raw, 'UTF-8'), 0, SearchQuery::MAX_LENGTH)]);
        }

        try {
            $filter = SearchFilter::fromRaw($request->queryString('cat'), $request->queryString('min'), $request->queryString('max'));
        } catch (InvalidFilter) {
            return Response::error(ApiError::InvalidFilter)->withLog(['q' => $query->text]);
        }

        $result = $this->search->execute($query, $filter);

        return Response::json(SearchResultJson::from($result), cacheControl: self::CACHE)->withLog([
            'q' => $query->text,
            'results' => count($result->results),
            'notice' => $result->notice?->type->value,
            'fallback' => $result->usedFallback,
        ] + ($filter->isEmpty() ? [] : [
            'cat' => $filter->category,
            'min_shekels' => $filter->min === null ? null : intdiv($filter->min->minor, 100),
            'max_shekels' => $filter->max === null ? null : intdiv($filter->max->minor, 100),
        ]));
    }
}
