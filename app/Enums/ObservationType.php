<?php

declare(strict_types=1);

namespace App\Enums;

enum ObservationType: string
{
    case Bugfix = 'bugfix';
    case Feature = 'feature';
    case Refactor = 'refactor';
    case Discovery = 'discovery';
    case Decision = 'decision';
    case Change = 'change';
}
