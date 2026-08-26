<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdvancementRuleType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StageAdvancementRule extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'external_stage_advancement_rules';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tournament_id',
        'source_stage_id',
        'source_group_id',
        'target_stage_id',
        'rule_order',
        'rule_type',
        'rank_from',
        'rank_to',
        'target_slot',
        'config',
        'source_created_at',
    ];

    protected $casts = [
        'rule_order' => 'integer',
        'rule_type' => AdvancementRuleType::class,
        'rank_from' => 'integer',
        'rank_to' => 'integer',
        'target_slot' => 'integer',
        'config' => 'array',
        'source_created_at' => 'datetime',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function sourceStage(): BelongsTo
    {
        return $this->belongsTo(Stage::class, 'source_stage_id');
    }

    public function sourceGroup(): BelongsTo
    {
        return $this->belongsTo(StageGroup::class, 'source_group_id');
    }

    public function targetStage(): BelongsTo
    {
        return $this->belongsTo(Stage::class, 'target_stage_id');
    }
}
