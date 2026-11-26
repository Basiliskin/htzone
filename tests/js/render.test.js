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

test('a notice (missing model, did you mean) is announced with the count, not only shown (AC-3.4)', () => {
    const missing = { type: 'missing_model', message: 'לא נמצא דגם מדויק, הנה המוצרים הקרובים' };
    assert.equal(
        announcement({ state: 'results', data: { total: 6, notice: missing } }),
        'נמצאו 6 מוצרים. לא נמצא דגם מדויק, הנה המוצרים הקרובים',
    );
    const didYouMean = { type: 'did_you_mean', message: 'האם התכוונת: מיקרוגל?', suggestion: 'מיקרוגל' };
    assert.equal(announcement({ state: 'results', data: { total: 5, notice: didYouMean } }), 'נמצאו 5 מוצרים. האם התכוונת: מיקרוגל?');
    assert.equal(announcement({ state: 'results', data: { total: 5, notice: null } }), 'נמצאו 5 מוצרים');
});
