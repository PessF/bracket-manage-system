<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum StageSourceType: string
{
    use HasValues;

    case REGISTRATION = 'REGISTRATION';
    case PREVIOUS_STAGE = 'PREVIOUS_STAGE';
    case MANUAL = 'MANUAL';
}
