import { test } from 'node:test';
import assert from 'node:assert/strict';
import { cleanQuery, classify, queryFromUrl, urlWithQuery, historyMethod, createSearch, filtersFromUrl, normalizeFilters, hasFilters, NO_FILTERS } from '../../public/assets/state.js';

test('cleanQuery trims and cuts to 100 characters (AC-4.2)', () => {
    assert.equal(cleanQuery('  שואב אבק  '), 'שואב אבק');
    assert.equal(cleanQuery('א'.repeat(150)), 'א'.repeat(100));
    // cut by code points, so an emoji is never split in half
    assert.equal(cleanQuery('😀'.repeat(101)), '😀'.repeat(100));
    // trimmed again after the cut
    assert.equal(cleanQuery('a'.repeat(99) + ' b'), 'a'.repeat(99));
});

test('classify: empty → idle, 1 character → short, otherwise search (AC-4.1, AC-4.2)', () => {
    assert.equal(classify(''), 'idle');
    assert.equal(classify('א'), 'short');
    assert.equal(classify('😀'), 'short');
    assert.equal(classify('אב'), 'search');
});

test('queryFromUrl and urlWithQuery round-trip ?q= and keep other parts (AC-5.5)', () => {
    assert.equal(queryFromUrl('https://x.test/?q=%D7%A9%D7%95%D7%90%D7%91'), 'שואב');
    assert.equal(queryFromUrl('https://x.test/'), '');
    assert.equal(urlWithQuery('https://x.test/?a=1#top', 'iPhone 16'), '/?a=1&q=iPhone+16#top');
    assert.equal(urlWithQuery('https://x.test/?q=old', ''), '/');
});

/** A fake API: every call returns a promise the test resolves or rejects by hand. */
function fakeApi() {
    const calls = [];
    const fetchResults = (query, signal, filters) => new Promise((resolve, reject) => {
        calls.push({ query, signal, filters, resolve, reject });
        signal.addEventListener('abort', () => reject(new DOMException('aborted', 'AbortError')));
    });
    return { calls, fetchResults };
}

function recorder() {
    const states = [];
    return { states, onState: (s) => states.push(s), names: () => states.map((s) => s.state) };
}

const response = (n) => ({ query: 'q', total: n, results: Array.from({ length: n }, (_, i) => ({ id: `P${i}` })), notice: null });

test('a search goes loading → results, and no results → empty', async () => {
    const api = fakeApi();
    const ui = recorder();
    const search = createSearch({ fetchResults: api.fetchResults, onState: ui.onState });

    let done = search.run('שואב אבק');
    api.calls[0].resolve(response(3));
    await done;
    done = search.run('xyzzy');
    api.calls[1].resolve(response(0));
    await done;

    assert.deepEqual(ui.names(), ['loading', 'results', 'loading', 'empty']);
    assert.equal(ui.states[1].data.total, 3);
    assert.equal(ui.states[3].query, 'xyzzy');
});

test('short and empty queries never call the API (AC-4.1, AC-4.2)', async () => {
    const api = fakeApi();
    const ui = recorder();
    const search = createSearch({ fetchResults: api.fetchResults, onState: ui.onState });

    await search.run('   ');
    await search.run(' א ');

    assert.equal(api.calls.length, 0);
    assert.deepEqual(ui.names(), ['idle', 'short']);
});

test('an older response never replaces a newer one (AC-3.1)', async () => {
    const api = fakeApi();
    const ui = recorder();
    const search = createSearch({ fetchResults: api.fetchResults, onState: ui.onState });

    const first = search.run('שואב');
    const second = search.run('שואב אבק');
    assert.ok(api.calls[0].signal.aborted, 'the first request is aborted');
    api.calls[1].resolve(response(2));
    await Promise.all([first, second]);

    assert.deepEqual(ui.names(), ['loading', 'loading', 'results']);
    assert.equal(ui.states[2].query, 'שואב אבק');
});

test('a late success of an aborted request is ignored too', async () => {
    const api = { calls: [], fetchResults: (query, signal) => new Promise((resolve) => api.calls.push({ query, signal, resolve })) };
    const ui = recorder();
    const search = createSearch({ fetchResults: api.fetchResults, onState: ui.onState });

    const first = search.run('שואב');
    const second = search.run('');
    api.calls[0].resolve(response(5)); // a fetch that ignores its signal
    await Promise.all([first, second]);

    assert.deepEqual(ui.names(), ['loading', 'idle']);
});

test('a failed request → error, and retry runs the same query again (AC-3.3)', async () => {
    const api = fakeApi();
    const ui = recorder();
    const search = createSearch({ fetchResults: api.fetchResults, onState: ui.onState });

    const failed = search.run('מיקרוגל');
    api.calls[0].reject(new Error('network'));
    await failed;
    const retried = search.retry();
    api.calls[1].resolve(response(1));
    await retried;

    assert.deepEqual(ui.names(), ['loading', 'error', 'loading', 'results']);
    assert.equal(api.calls[1].query, 'מיקרוגל');
});

test('history: typing replaces the entry, but the first change after a submit opens a new one (AC-5.5)', () => {
    // typing on a fresh page: replace, entry not yet committed
    assert.deepEqual(historyMethod({ current: '/', next: '/?q=a', committed: false, commit: false }), { method: 'replace', committed: false });
    // Enter on that query: same URL, nothing to write, but the entry is now committed
    assert.deepEqual(historyMethod({ current: '/?q=ab', next: '/?q=ab', committed: false, commit: true }), { method: null, committed: true });
    // typing after a submit: push once, so Back returns to the submitted query …
    assert.deepEqual(historyMethod({ current: '/?q=ab', next: '/?q=c', committed: true, commit: false }), { method: 'push', committed: false });
    // … then keep replacing while typing
    assert.deepEqual(historyMethod({ current: '/?q=c', next: '/?q=cd', committed: false, commit: false }), { method: 'replace', committed: false });
    // typing that doesn't change the URL (a trailing space) keeps a submitted entry committed
    assert.deepEqual(historyMethod({ current: '/?q=ab', next: '/?q=ab', committed: true, commit: false }), { method: null, committed: true });
    // a chip click right after a submit: a new entry, committed
    assert.deepEqual(historyMethod({ current: '/?q=ab', next: '/?q=xy', committed: true, commit: true }), { method: 'push', committed: true });
});

test('filters: read from the URL, prices cleaned to whole shekels, min/max swapped when reversed (D43–D45)', () => {
    assert.deepEqual(filtersFromUrl('https://x.test/?q=a&cat=%D7%9E%D7%98%D7%91%D7%97&min=100&max=500'), { cat: 'מטבח', min: '100', max: '500' });
    assert.deepEqual(filtersFromUrl('https://x.test/?q=a'), NO_FILTERS);
    // anything the API would reject is dropped instead of becoming an error page
    assert.deepEqual(filtersFromUrl('https://x.test/?min=-5&max=1e3'), NO_FILTERS);
    assert.deepEqual(normalizeFilters({ cat: ' בית ', min: '0500', max: '100' }), { cat: 'בית', min: '100', max: '500' });
    assert.deepEqual(normalizeFilters({ cat: '', min: '9.5', max: '' }), NO_FILTERS);
    assert.equal(hasFilters(NO_FILTERS), false);
    assert.equal(hasFilters({ cat: '', min: '', max: '200' }), true);
});

test('urlWithQuery carries the filters and drops empty ones', () => {
    assert.equal(urlWithQuery('https://x.test/', 'מתנה', { cat: 'בית', min: '', max: '200' }), '/?q=%D7%9E%D7%AA%D7%A0%D7%94&cat=%D7%91%D7%99%D7%AA&max=200');
    assert.equal(urlWithQuery('https://x.test/?q=a&cat=x&min=1', 'a', NO_FILTERS), '/?q=a');
});

test('a filter that leaves nothing is its own state, not "no results"; retry keeps the filters', async () => {
    const api = fakeApi();
    const ui = recorder();
    const search = createSearch({ fetchResults: api.fetchResults, onState: ui.onState });
    const filters = { cat: 'בית', min: '', max: '50' };

    const done = search.run('ספה', filters);
    assert.deepEqual(api.calls[0].filters, filters);
    api.calls[0].resolve(response(0));
    await done;
    const retried = search.retry();
    api.calls[1].resolve(response(2));
    await retried;

    assert.deepEqual(ui.names(), ['loading', 'filtered', 'loading', 'results']);
    assert.deepEqual(api.calls[1].filters, filters);
});
