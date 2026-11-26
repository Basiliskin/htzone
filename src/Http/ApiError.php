<?php

declare(strict_types=1);

namespace Htzone\Http;

/**
 * Every error the API can return (architecture §8): the code clients switch on, its HTTP status and the
 * Hebrew text shown to the visitor. One place, so the error shape can't drift between controllers.
 */
enum ApiError: string
{
    case QueryMissing = 'query_missing';
    case QueryTooShort = 'query_too_short';
    case InvalidFilter = 'invalid_filter';
    case NotFound = 'not_found';
    case MethodNotAllowed = 'method_not_allowed';
    case Internal = 'internal_error';

    public function status(): int
    {
        return match ($this) {
            self::QueryMissing, self::QueryTooShort, self::InvalidFilter => 400,
            self::NotFound => 404,
            self::MethodNotAllowed => 405,
            self::Internal => 500,
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::QueryMissing => 'חסרה מילת חיפוש',
            self::QueryTooShort => 'הקלידו לפחות 2 תווים',
            self::InvalidFilter => 'הסינון לא תקין',
            self::NotFound => 'הכתובת לא נמצאה',
            self::MethodNotAllowed => 'הפעולה אינה נתמכת',
            self::Internal => 'משהו השתבש. נסו שוב בעוד רגע.',
        };
    }
}
