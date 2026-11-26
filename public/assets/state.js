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

/** No category and no price bounds (D43). Prices are whole shekels as strings, the way the URL holds them. */
export const NO_FILTERS = Object.freeze({ cat: '', min: '', max: '' });
const SHEKELS = /^\d{1,7}$/; // the API's rule (SearchFilter): whole shekels up to ₪9,999,999

function cleanPrice(raw) {
    const text = String(raw ?? '').trim();
    return SHEKELS.test(text) ? String(Number(text)) : '';
}

/** Trimmed category, prices the API accepts (anything else dropped), min and max swapped when reversed. */
export function normalizeFilters({ cat = '', min = '', max = '' } = {}) {
    let [low, high] = [cleanPrice(min), cleanPrice(max)];
    if (low !== '' && high !== '' && Number(low) > Number(high)) [low, high] = [high, low];
    return { cat: String(cat).trim(), min: low, max: high };
}

export function hasFilters(filters) {
    return filters.cat !== '' || filters.min !== '' || filters.max !== '';
}

export function filtersFromUrl(href) {
    const params = new URL(href).searchParams;
    return normalizeFilters({ cat: params.get('cat') ?? '', min: params.get('min') ?? '', max: params.get('max') ?? '' });
}

export function queryFromUrl(href) {
    return new URL(href).searchParams.get('q') ?? '';
}

/** The same URL with ?q= and the filters set (or removed when empty), as a path for history.pushState/replaceState. */
export function urlWithQuery(href, query, filters = NO_FILTERS) {
    const url = new URL(href);
    for (const [name, value] of [['q', query], ['cat', filters.cat], ['min', filters.min], ['max', filters.max]]) {
        if (value) url.searchParams.set(name, value);
        else url.searchParams.delete(name);
    }
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
 * @param {{fetchResults: (query: string, signal: AbortSignal, filters: object) => Promise<any>, onState: (s: object) => void}} deps
 *   onState receives {state, query, data?, error?} with state one of idle|short|loading|results|empty|filtered|error.
 *   `filtered` = the search found products but the filters left none (D43), so "clear filters" is the way out.
 */
export function createSearch({ fetchResults, onState }) {
    let controller = null;
    let lastQuery = '';
    let lastFilters = NO_FILTERS;

    async function run(raw, filters = NO_FILTERS) {
        const query = cleanQuery(raw);
        lastQuery = query;
        lastFilters = filters;
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
            const data = await fetchResults(query, own.signal, filters);
            if (own.signal.aborted) return;
            const none = hasFilters(filters) ? 'filtered' : 'empty';
            onState({ state: data.results.length > 0 ? 'results' : none, query, data });
        } catch (error) {
            if (own.signal.aborted) return;
            onState({ state: 'error', query, error });
        }
    }

    return {
        run,
        retry: () => run(lastQuery, lastFilters),
    };
}
