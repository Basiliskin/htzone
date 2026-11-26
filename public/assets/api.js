// The only module that talks to the server (contract: architecture §8).

/** A non-2xx answer. `code` is the API's error code (query_too_short, internal_error …) or http_error. */
export class SearchError extends Error {
    constructor(status, code) {
        super(`search failed: ${status} ${code}`);
        this.name = 'SearchError';
        this.status = status;
        this.code = code;
    }
}

/**
 * GET /api/search?q=… → {query, total, results, notice}. An abort rejects with the browser's AbortError.
 * fetchFn is a parameter only so tests can pass a fake.
 */
export async function search(query, signal, fetchFn = fetch) {
    const response = await fetchFn(`/api/search?${new URLSearchParams({ q: query })}`, {
        signal,
        headers: { Accept: 'application/json' },
    });
    if (!response.ok) {
        const body = await response.json().catch(() => null);
        throw new SearchError(response.status, body?.error?.code ?? 'http_error');
    }
    return response.json();
}
