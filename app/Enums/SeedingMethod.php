<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum SeedingMethod: string
{
    use HasValues;

    case RANDOM = 'RANDOM';
    case REGISTRATION_ORDER = 'REGISTRATION_ORDER';
    case MANUAL = 'MANUAL';
    case RANKING = 'RANKING';
}
