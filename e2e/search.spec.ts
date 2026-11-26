import { expect, test, type Page, type Route } from '@playwright/test';

// The main flow in a real browser against the real server (architecture S6, AC-3.1–3.3).
// page.route is used only where timing or failure must be controlled (D37); every other answer
// comes from the real API and the real catalog.

const SEARCH_API = '**/api/search?*';

const body = (page: Page) => page.locator('body');
const realCards = (page: Page) => page.locator('#grid > li.card:not(.skeleton)');

/** Holds every search request until release() is called, then lets it through to the real server. */
async function holdSearchRequests(page: Page): Promise<() => void> {
    let release!: () => void;
    const released = new Promise<void>((resolve) => { release = resolve; });
    await page.route(SEARCH_API, async (route: Route) => {
        await released;
        await route.continue();
    });
    return release;
}

test.beforeEach(async ({ page }) => {
    await page.goto('/');
    await expect(body(page)).toHaveAttribute('data-state', 'idle');
});

test('typing a query shows skeletons, then product cards with image, name and ₪ price', async ({ page }) => {
    const release = await holdSearchRequests(page);

    await page.getByRole('searchbox', { name: 'חיפוש מוצרים' }).fill('שואב אבק');

    // While the request is held: 8 placeholder cards, no real ones yet.
    await expect(body(page)).toHaveAttribute('data-state', 'loading');
    await expect(page.locator('#grid > li.card.skeleton')).toHaveCount(8);
    await expect(realCards(page)).toHaveCount(0);

    release();

    await expect(body(page)).toHaveAttribute('data-state', 'results');
    await expect(page.locator('#grid > li.card.skeleton')).toHaveCount(0);
    await expect(page).toHaveURL(/\?q=%D7%A9%D7%95%D7%90%D7%91/); // ?q=שואב… kept in the URL

    const first = realCards(page).first();
    await expect(first.locator('h3')).toContainText('שואב אבק');
    await expect(first.locator('.price')).toHaveText(/^₪\d{1,3}(,\d{3})*$/);
    // The product photo or, if it can't load, the local fallback: either way a real, decoded image.
    await expect.poll(() => first.locator('img').evaluate((img: HTMLImageElement) => img.complete && img.naturalWidth > 0))
        .toBe(true);

    for (const card of await realCards(page).all()) {
        await expect(card.locator('h3')).not.toBeEmpty();
        await expect(card.locator('.price')).toContainText('₪');
    }
    await expect(page.locator('#count')).toHaveText(/\d+ מוצרים/);
});

test('a nonsense query shows the no-results panel with the query and suggestions', async ({ page }) => {
    await page.getByRole('searchbox', { name: 'חיפוש מוצרים' }).fill('zzqxv');

    await expect(body(page)).toHaveAttribute('data-state', 'empty');
    const empty = page.locator('#empty');
    await expect(empty.getByRole('heading', { level: 2 })).toContainText('לא מצאנו מוצרים');
    await expect(page.locator('#empty-q')).toHaveText('zzqxv');
    await expect(empty.locator('a.chip')).toHaveCount(5);
    await expect(page.locator('#results')).toBeHidden();
});

test('a server error shows the error panel, and Retry runs the same query again', async ({ page }) => {
    await page.route(SEARCH_API, (route) => route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({ error: { code: 'internal_error', message: 'שגיאה' } }),
    }));

    await page.getByRole('searchbox', { name: 'חיפוש מוצרים' }).fill('iPhone 16');

    await expect(body(page)).toHaveAttribute('data-state', 'error');
    const retry = page.getByRole('button', { name: 'נסו שוב' });
    await expect(retry).toBeVisible();
    await expect(page.getByRole('alert')).toContainText('משהו השתבש בחיפוש');

    // The server recovers; Retry must send the same query, and focus moves to the new results.
    await page.unroute(SEARCH_API);
    const retried = page.waitForRequest((request) => request.url().includes('/api/search?'), { timeout: 5_000 });
    await retry.click();

    expect(new URL((await retried).url()).searchParams.get('q')).toBe('iPhone 16');
    await expect(body(page)).toHaveAttribute('data-state', 'results');
    // The catalog has no iPhone 16 (brief example): closest models plus the missing-model notice.
    await expect(page.locator('#notice')).toHaveText('לא נמצא דגם מדויק, הנה המוצרים הקרובים');
    await expect(realCards(page)).not.toHaveCount(0);
    await expect(page.locator('#results-title')).toBeFocused();
});
