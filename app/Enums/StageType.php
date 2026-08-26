<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum StageType: string
{
    use HasValues;

    case MAIN = 'MAIN';
    case GROUP = 'GROUP';
    case PLAYOFF = 'PLAYOFF';
    case FINAL = 'FINAL';
}
