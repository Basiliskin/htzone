<?php

declare(strict_types=1);

namespace Htzone\Application;

/**
 * Why the results come with a note. The values are the API's `notice.type`.
 */
enum NoticeType: string
{
    case MissingModel = 'missing_model';  // D14: a number no top result has ("iPhone 16")
    case DidYouMean = 'did_you_mean';     // D15: shown results come from the typo fallback
}
