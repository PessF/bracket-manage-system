<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum BracketType: string
{
    use HasValues;

    case WINNERS = 'WINNERS';
    case LOSERS = 'LOSERS';
    case GRAND_FINAL = 'GRAND_FINAL';
    case ROUND_ROBIN = 'ROUND_ROBIN';
    case RANKING = 'RANKING';
}
