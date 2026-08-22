<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum SyncStatus: string
{
    use HasValues;

    case STARTED = 'STARTED';
    case SUCCESS = 'SUCCESS';
    case FAILED = 'FAILED';
}
