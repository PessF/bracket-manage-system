<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum StageStatus: string
{
    use HasValues;

    case PENDING = 'PENDING';
    case LIVE = 'LIVE';
    case COMPLETED = 'COMPLETED';
}
