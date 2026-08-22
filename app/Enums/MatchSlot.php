<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum MatchSlot: string
{
    use HasValues;

    case A = 'A';
    case B = 'B';
}
