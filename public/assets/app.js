// Entry point: wires page events to the search session (design §4). The only module that touches
// window, history and the keyboard; rules live in state.js, DOM building in render.js.
import { search } from './api.js';
import { byId } from './dom.js';
import { cleanQuery, createSearch, historyMethod, queryFromUrl, urlWithQuery } from './state.js';
import * as render from './render.js';

const DEBOUNCE_MS = 250;

const form = byId('search-form');
const input = byId('q');
const clearButton = byId('clear');
const live = byId('live');
const view = { grid: byId('grid'), count: byId('count'), notice: byId('notice') };
const emptyQuery = byId('empty-q');

let debounce = 0;
let committed = queryFromUrl(location.href) !== ''; // a URL that already has ?q= is a submitted search
let focusAfterRetry = false;

const session = createSearch({ fetchResults: search, onState: show });

function show(event) {
    document.body.dataset.state = event.state;
    if (event.state === 'loading') render.skeletons(view.grid);
    if (event.state === 'results') render.results(view, event.data, (q) => submit(q));
    if (event.state === 'empty') emptyQuery.textContent = event.query;
    announce(render.announcement(event));

    // Retry hides the button the user is on; move focus to the new content instead of losing it.
    if (focusAfterRetry && event.state !== 'loading') {
        focusAfterRetry = false;
        document.getElementById(`${event.state}-title`)?.focus();
    }
}

/** Clearing first makes a repeated identical message ("לא נמצאו מוצרים") be read again. */
function announce(text) {
    live.textContent = '';
    if (text) setTimeout(() => { live.textContent = text; }, 50);
}

function syncUrl(query, commit) {
    const current = location.pathname + location.search + location.hash;
    const next = urlWithQuery(location.href, query);
    const step = historyMethod({ current, next, committed, commit });
    committed = step.committed;
    if (step.method === 'push') history.pushState(null, '', next);
    if (step.method === 'replace') history.replaceState(null, '', next);
}

/** commit = a deliberate search (Enter, button, chip, suggestion, clear); false = typing. */
function run(raw, commit) {
    clearTimeout(debounce);
    clearButton.hidden = input.value === '';
    syncUrl(cleanQuery(raw), commit);
    session.run(raw);
}

function submit(query) {
    input.value = query;
    run(query, true);
    input.focus();
}

function clear() {
    input.value = '';
    run('', true);
    input.focus();
}

input.addEventListener('input', () => {
    clearTimeout(debounce);
    clearButton.hidden = input.value === '';
    debounce = setTimeout(() => run(input.value, false), DEBOUNCE_MS);
});

form.addEventListener('submit', (e) => {
    e.preventDefault();
    run(input.value, true);
});

clearButton.addEventListener('click', clear);

byId('retry').addEventListener('click', () => {
    focusAfterRetry = true;
    session.retry();
});

// Chips are real links (/?q=…) so they work without JS; with JS they search in place.
document.addEventListener('click', (e) => {
    const chip = e.target instanceof Element ? e.target.closest('a.chip[data-query]') : null;
    if (!chip || e.ctrlKey || e.metaKey || e.shiftKey) return; // let "open in new tab" work
    e.preventDefault();
    submit(chip.dataset.query);
});

document.addEventListener('keydown', (e) => {
    if (e.key === '/' && !isTyping(e.target)) {
        e.preventDefault();
        input.focus();
        input.select();
    } else if (e.key === 'Escape' && e.target === input) {
        e.preventDefault();
        clear();
    }
});

function isTyping(target) {
    return target instanceof HTMLElement
        && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
}

window.addEventListener('popstate', () => {
    const query = queryFromUrl(location.href);
    input.value = query;
    clearButton.hidden = query === '';
    committed = true;
    clearTimeout(debounce);
    session.run(query);
});

// The template already put ?q= into the input (it also works without JS); run it once.
clearButton.hidden = input.value === '';
session.run(input.value);
