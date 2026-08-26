<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum TournamentStructure: string
{
    use HasValues;

    case STANDARD = 'STANDARD';
    case ADVANCED = 'ADVANCED';
}
