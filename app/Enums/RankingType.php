<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum RankingType: string
{
    use HasValues;

    case RACING_ROBOT = 'RACING_ROBOT';
    case DRONE_MISSION = 'DRONE_MISSION';
}
