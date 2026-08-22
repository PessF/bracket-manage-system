<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum MatchOutcome: string
{
    use HasValues;

    case WINNER = 'WINNER';
    case LOSER = 'LOSER';
}
