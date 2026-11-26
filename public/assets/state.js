// Search state rules (architecture §9, design §4). No DOM here, so Node can test it (tests/js).

export const MIN_LENGTH = 2;
export const MAX_LENGTH = 100;

/** Mirrors the server's SearchQuery: trimmed, cut to 100 characters (code points, never half an emoji). */
export function cleanQuery(raw) {
    return Array.from(raw.trim()).slice(0, MAX_LENGTH).join('').trim();
}

/** @returns {'idle'|'short'|'search'} what to do with an already-cleaned query */
export function classify(query) {
    const length = Array.from(query).length;
    if (length === 0) return 'idle';
    return length < MIN_LENGTH ? 'short' : 'search';
}

export function queryFromUrl(href) {
    return new URL(href).searchParams.get('q') ?? '';
}

/** The same URL with ?q= set (or removed when empty), as a path for history.pushState/replaceState. */
export function urlWithQuery(href, query) {
    const url = new URL(href);
    if (query) url.searchParams.set('q', query);
    else url.searchParams.delete('q');
    return url.pathname + url.search + url.hash;
}

/**
 * Back/Forward (design §4): typing replaces the current history entry; a submitted query (Enter,
 * button, chip) "commits" it, so the next change opens a new entry and Back returns to it.
 *
 * @returns {{method: 'push'|'replace'|null, committed: boolean}} method null = URL unchanged
 */
export function historyMethod({ current, next, committed, commit }) {
    if (current === next) return { method: null, committed: committed || commit };
    return { method: committed ? 'push' : 'replace', committed: commit };
}

/**
 * One search session: every run aborts the previous request, and a response that arrives after a
 * newer run is dropped, so an old response can never replace a newer one (AC-3.1).
 *
 * @param {{fetchResults: (query: string, signal: AbortSignal) => Promise<any>, onState: (s: object) => void}} deps
 *   onState receives {state, query, data?, error?} with state one of idle|short|loading|results|empty|error.
 */
export function createSearch({ fetchResults, onState }) {
    let controller = null;
    let lastQuery = '';

    async function run(raw) {
        const query = cleanQuery(raw);
        lastQuery = query;
        controller?.abort();
        controller = null;

        const kind = classify(query);
        if (kind !== 'search') {
            onState({ state: kind, query });
            return;
        }

        const own = new AbortController();
        controller = own;
        onState({ state: 'loading', query });
        try {
            const data = await fetchResults(query, own.signal);
            if (own.signal.aborted) return;
            onState({ state: data.results.length > 0 ? 'results' : 'empty', query, data });
        } catch (error) {
            if (own.signal.aborted) return;
            onState({ state: 'error', query, error });
        }
    }

    return {
        run,
        retry: () => run(lastQuery),
    };
}
