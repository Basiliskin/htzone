<?php

declare(strict_types=1);

namespace Htzone\Domain\Search;

/**
 * Hebrew spelling ↔ Latin name of the catalog's frequent brands and product lines (counted in the CSV),
 * looked up both ways. Multi-word or geresh names (אל ג׳י, לוג׳יטק) are left to the enrichment tags.
 */
final class Aliases
{
    private const HEBREW_TO_LATIN = [
        'אייפון' => 'iphone', 'אייפד' => 'ipad', 'מקבוק' => 'macbook', 'איירפודס' => 'airpods', 'גלקסי' => 'galaxy',
        'אפל' => 'apple', 'סמסונג' => 'samsung', 'שיאומי' => 'xiaomi', 'פיליפס' => 'philips', 'בוש' => 'bosch',
        'טפאל' => 'tefal', 'בראון' => 'braun', 'סוני' => 'sony', 'אנקר' => 'anker', 'פנסוניק' => 'panasonic',
        'דייסון' => 'dyson', 'אלקטרולוקס' => 'electrolux', 'סמסונייט' => 'samsonite', 'הסברו' => 'hasbro',
        'גרמין' => 'garmin', 'קרשר' => 'karcher', 'סימנס' => 'siemens', 'וואווי' => 'huawei', 'גוגל' => 'google',
        'מיקרוסופט' => 'microsoft', 'לנובו' => 'lenovo', 'נספרסו' => 'nespresso', 'דלונגי' => 'delonghi',
        'קנווד' => 'kenwood', 'טורנדו' => 'tornado', 'אלקטרה' => 'electra', 'תדיראן' => 'tadiran', 'רמינגטון' => 'remington',
        'רובנטה' => 'rowenta', 'סטנלי' => 'stanley', 'ברבנטיה' => 'brabantia', 'בודום' => 'bodum', 'ימאהה' => 'yamaha',
        'בלקין' => 'belkin', 'גברה' => 'jabra', 'אמזון' => 'amazon', 'קינדל' => 'kindle', 'פלייסטיישן' => 'playstation',
        'נינטנדו' => 'nintendo', 'לגו' => 'lego', 'הוניוול' => 'honeywell', 'ויטמיקס' => 'vitamix',
    ];

    public static function of(string $word): ?string
    {
        if (isset(self::HEBREW_TO_LATIN[$word])) {
            return self::HEBREW_TO_LATIN[$word];
        }
        $hebrew = array_search($word, self::HEBREW_TO_LATIN, true);

        return is_string($hebrew) ? $hebrew : null;
    }
}
