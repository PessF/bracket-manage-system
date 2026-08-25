<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum AdvancementRuleType: string
{
    use HasValues;

    case TOP_N = 'TOP_N';
    case RANK_TO_SLOT = 'RANK_TO_SLOT';
    case WINNER_TO_SLOT = 'WINNER_TO_SLOT';
    case LOSER_TO_SLOT = 'LOSER_TO_SLOT';
    case MANUAL = 'MANUAL';
}
