<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum TournamentStatus: string
{
    use HasValues;

    case DRAFT = 'DRAFT';
    case READY = 'READY';
    case LIVE = 'LIVE';
    case COMPLETED = 'COMPLETED';
    case ARCHIVED = 'ARCHIVED';
}
