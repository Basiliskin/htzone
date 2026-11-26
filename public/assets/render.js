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
        case 'results': return data.total === 1 ? 'נמצא מוצר אחד' : `נמצאו ${data.total} מוצרים`;
        case 'empty': return 'לא נמצאו מוצרים';
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
