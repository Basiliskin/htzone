import { test } from 'node:test';
import assert from 'node:assert/strict';
import { countText, announcement } from '../../public/assets/render.js';

test('count uses the Hebrew singular for one product', () => {
    assert.equal(countText(1), 'מוצר אחד');
    assert.equal(countText(8), '8 מוצרים');
});

test('every visible state has a screen-reader announcement; loading and idle stay silent (AC-3.4)', () => {
    assert.equal(announcement({ state: 'results', data: { total: 3 } }), 'נמצאו 3 מוצרים');
    assert.equal(announcement({ state: 'results', data: { total: 1 } }), 'נמצא מוצר אחד');
    assert.equal(announcement({ state: 'empty' }), 'לא נמצאו מוצרים');
    assert.equal(announcement({ state: 'error' }), 'אירעה שגיאה בחיפוש');
    assert.equal(announcement({ state: 'short' }), 'הקלידו לפחות 2 תווים כדי לחפש');
    assert.equal(announcement({ state: 'loading' }), '');
    assert.equal(announcement({ state: 'idle' }), '');
});
