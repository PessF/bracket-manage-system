<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ParticipantStatus: string
{
    use HasValues;

    case ACTIVE = 'ACTIVE';
    case CHECKED_IN = 'CHECKED_IN';
    case WITHDRAWN = 'WITHDRAWN';
    case DISQUALIFIED = 'DISQUALIFIED';
}
