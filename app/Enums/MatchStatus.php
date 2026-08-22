<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum MatchStatus: string
{
    use HasValues;

    case PENDING = 'PENDING';
    case READY = 'READY';
    case LIVE = 'LIVE';
    case FINISHED = 'FINISHED';
    case HOLD = 'HOLD';
    case DISPUTED = 'DISPUTED';
    case DQ = 'DQ';
}
