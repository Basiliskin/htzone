// Turns a search state into DOM (design §3). Which section is visible is decided by CSS from
// body[data-state]; this module only fills the sections.
import { el } from './dom.js';

const FALLBACK_IMAGE = '/assets/img/fallback.svg';
const SKELETON_COUNT = 8;

/** "8 מוצרים" / "מוצר אחד". */
export function countText(total) {
    return total === 1 ? 'מוצר אחד' : `${total} מוצרים`;
}

/** What the aria-live region says for a state (AC-3.4). Loading stays silent to avoid chatter while typing. */
export function announcement({ state, data }) {
    switch (state) {
        case 'results': {
            const count = data.total === 1 ? 'נמצא מוצר אחד' : `נמצאו ${data.total} מוצרים`;
            return data.notice ? `${count}. ${data.notice.message}` : count; // the notice changes what the results mean
        }
        case 'empty': return 'לא נמצאו מוצרים';
        case 'filtered': return 'אין מוצרים בסינון הזה';
        case 'error': return 'אירעה שגיאה בחיפוש';
        case 'short': return 'הקלידו לפחות 2 תווים כדי לחפש';
        default: return '';
    }
}

export function skeletons(grid) {
    const cards = Array.from({ length: SKELETON_COUNT }, () =>
        el('li', { class: 'card skeleton', 'aria-hidden': 'true' },
            el('div', { class: 'media' }),
            el('div', { class: 'body' }, el('div', { class: 'ph-line' }), el('div', { class: 'ph-line short' }))));
    grid.replaceChildren(...cards);
}

function image(product) {
    const img = el('img', { src: product.image, alt: '', loading: 'lazy', decoding: 'async', width: 600, height: 600 });
    img.addEventListener('error', () => {
        img.src = FALLBACK_IMAGE;
        img.closest('.card')?.classList.add('no-img');
    }, { once: true });
    return img;
}

/** Name inherits RTL (never dir=auto, design §5); brand and price are isolated with <bdi>. */
function card(product) {
    return el('li', { class: 'card' },
        el('div', { class: 'media' }, image(product)),
        el('div', { class: 'body' },
            product.brand ? el('p', { class: 'brand' }, el('bdi', {}, product.brand)) : null,
            el('h3', { title: product.name }, product.name),
            el('p', { class: 'price' }, el('bdi', { dir: 'ltr' }, product.price.formatted))));
}

function notice(box, data, onSuggestion) {
    box.hidden = data.notice === null;
    if (data.notice === null) return box.replaceChildren();
    const { message, suggestion } = data.notice;
    box.replaceChildren(message);
    if (suggestion) {
        const button = el('button', { type: 'button', class: 'link' }, 'חפשו „', el('bdi', {}, suggestion), '”');
        button.addEventListener('click', () => onSuggestion(suggestion));
        box.append(' ', button);
    }
}

/** @param view {grid, count, notice} elements from the page */
export function results(view, data, onSuggestion) {
    view.grid.replaceChildren(...data.results.map(card));
    view.count.textContent = countText(data.total);
    notice(view.notice, data, onSuggestion);
}

/** Price placeholders: the range of the matching results in whole shekels, rounded outwards (D45). */
export function priceHints(facets) {
    const price = facets?.price;
    if (!price) return { min: '', max: '' };
    return { min: String(Math.floor(price.min / 100)), max: String(Math.ceil(price.max / 100)) };
}

function categoryChip(label, cat, pressed, onCategory) {
    const button = el('button', { type: 'button', class: 'chip', 'data-cat': cat, 'aria-pressed': String(pressed) }, ...label);
    button.addEventListener('click', () => onCategory(cat));
    return button;
}

/**
 * Category chips and price fields (D44, D45). Chips show only when the matches span 2+ categories, or one is
 * chosen (so it can be undone). A chip that had focus keeps it after the chips are rebuilt.
 *
 * @param view {filters, cats, min, max} elements from the page
 * @param active the filters in force: {cat, min, max}
 */
export function filters(view, data, active, onCategory) {
    const categories = data.facets?.categories ?? [];
    const focused = view.cats.contains(document.activeElement) ? document.activeElement.dataset.cat : null;

    const showCats = categories.length >= 2 || active.cat !== '';
    view.filters.hidden = !showCats && !data.facets?.price && active.min === '' && active.max === '';
    view.cats.hidden = !showCats;
    view.cats.replaceChildren(...(showCats ? [
        categoryChip(['הכול'], '', active.cat === '', onCategory),
        ...categories.map(({ name, count }) => categoryChip([el('bdi', {}, name), el('span', { class: 'n' }, `(${count})`)], name, name === active.cat, onCategory)),
    ] : []));
    if (focused !== null) view.cats.querySelector(`[data-cat="${CSS.escape(focused)}"]`)?.focus();
    // On mobile the chips are one scrolling row: keep the chosen one in view.
    if (view.cats.scrollWidth > view.cats.clientWidth) {
        view.cats.querySelector('[aria-pressed="true"]')?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    }

    const hints = priceHints(data.facets);
    view.min.placeholder = hints.min;
    view.max.placeholder = hints.max;
    // A field the visitor is editing (data-dirty, set by app.js) keeps their text: a response that lands
    // mid-typing must not wipe it. Applying the filter or Back/Forward clears the flag.
    for (const [input, value] of [[view.min, active.min], [view.max, active.max]]) {
        if (input.dataset.dirty !== 'true') input.value = value;
    }
}
