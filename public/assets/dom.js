// The one DOM-building helper. Text goes in through append() (a text node) and attributes through
// setAttribute, never innerHTML, so product data can't inject markup (AC-4.4).

/**
 * el('li', {class: 'card'}, child, 'text') → <li class="card">…</li>. null/undefined/false attributes
 * and children are skipped, which keeps optional parts (e.g. no brand) readable at the call site.
 */
export function el(tag, attrs = {}, ...children) {
    const node = document.createElement(tag);
    for (const [name, value] of Object.entries(attrs)) {
        if (value !== null && value !== undefined && value !== false) node.setAttribute(name, String(value));
    }
    for (const child of children) {
        if (child !== null && child !== undefined && child !== false) node.append(child);
    }
    return node;
}

export function byId(id) {
    const node = document.getElementById(id);
    if (!node) throw new Error(`#${id} is missing from templates/page.php`);
    return node;
}
