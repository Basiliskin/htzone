import { test } from 'node:test';
import assert from 'node:assert/strict';
import { search, SearchError } from '../../public/assets/api.js';

const json = (status, body) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });

test('search() calls /api/search with the encoded query and the signal', async () => {
    let seen;
    const fetchFn = async (url, init) => { seen = { url, init }; return json(200, { query: 'a&b', total: 0, results: [], notice: null }); };
    const signal = new AbortController().signal;

    const body = await search('a&b שואב', signal, fetchFn);

    assert.equal(seen.url, '/api/search?q=a%26b+%D7%A9%D7%95%D7%90%D7%91');
    assert.equal(seen.init.signal, signal);
    assert.equal(seen.init.headers.Accept, 'application/json');
    assert.equal(body.total, 0);
});

test('a non-2xx response becomes a SearchError with the API error code', async () => {
    const fetchFn = async () => json(400, { error: { code: 'query_too_short', message: '…' } });

    await assert.rejects(search('א', undefined, fetchFn), (e) => e instanceof SearchError && e.status === 400 && e.code === 'query_too_short');
});

test('a non-JSON error body (e.g. a proxy 502 page) still becomes a SearchError', async () => {
    const fetchFn = async () => new Response('<html>Bad gateway</html>', { status: 502 });

    await assert.rejects(search('ab', undefined, fetchFn), (e) => e instanceof SearchError && e.status === 502 && e.code === 'http_error');
});

test('an abort is passed through unchanged so the caller can ignore it', async () => {
    const fetchFn = async () => { throw new DOMException('aborted', 'AbortError'); };

    await assert.rejects(search('ab', undefined, fetchFn), { name: 'AbortError' });
});
