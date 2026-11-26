<?php

declare(strict_types=1);

namespace Htzone\Domain\Search;

/**
 * Filler words that would match almost everything (Hebrew and English). Checked on the word as typed
 * and after prefix stripping, so "למישהו" is dropped like "מישהו".
 */
final class StopWords
{
    private const WORDS = [
        // Hebrew
        'של', 'את', 'עם', 'על', 'אל', 'או', 'גם', 'כי', 'אם', 'זה', 'זו', 'זאת', 'מה', 'מי', 'יש', 'אין', 'לא', 'כן',
        'הוא', 'היא', 'הם', 'הן', 'אני', 'אתה', 'אנחנו', 'לי', 'לו', 'לה', 'שלי', 'שלו', 'שלה', 'שלנו', 'כל', 'רק', 'עוד',
        'משהו', 'מישהו', 'דבר', 'מאוד', 'ממש', 'הכי', 'כמו', 'בשביל', 'עבור', 'אפשר', 'צריך', 'צריכה', 'רוצה', 'מחפש',
        'מחפשת', 'יעזור', 'שיעזור', 'עוזר', 'שעוזר', 'טוב', 'טובה', 'מומלץ',
        // English
        'the', 'an', 'and', 'or', 'for', 'of', 'to', 'with', 'in', 'on', 'at', 'by', 'my', 'me', 'is', 'are', 'it', 'that',
        'this', 'some', 'something', 'someone', 'who', 'best', 'good', 'need', 'want', 'looking', 'help', 'helps',
    ];

    public static function contains(string $word): bool
    {
        return in_array($word, self::WORDS, true);
    }
}
