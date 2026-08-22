<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum TournamentFormat: string
{
    use HasValues;

    case RANKING = 'RANKING';
    case ROUND_ROBIN = 'ROUND_ROBIN';
    case SINGLE_ELIMINATION = 'SINGLE_ELIMINATION';
    case DOUBLE_ELIMINATION = 'DOUBLE_ELIMINATION';

    public function isElimination(): bool
    {
        return $this === self::SINGLE_ELIMINATION
            || $this === self::DOUBLE_ELIMINATION;
    }
}
