<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StageStatus;
use App\Enums\StageSourceType;
use App\Enums\StageType;
use App\Enums\TournamentFormat;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stage extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'external_stages';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tournament_id',
        'name',
        'stage_order',
        'stage_type',
        'format',
        'status',
        'source_type',
        'source_stage_id',
        'advance_count',
        'team_limit',
        'config',
        'source_created_at',
    ];

    protected $casts = [
        'stage_order' => 'integer',
        'stage_type' => StageType::class,
        'format' => TournamentFormat::class,
        'status' => StageStatus::class,
        'source_type' => StageSourceType::class,
        'advance_count' => 'integer',
        'team_limit' => 'integer',
        'config' => 'array',
        'source_created_at' => 'datetime',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function sourceStage(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_stage_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(StageGroup::class);
    }

    public function advancementRulesFrom(): HasMany
    {
        return $this->hasMany(StageAdvancementRule::class, 'source_stage_id');
    }

    public function advancementRulesTo(): HasMany
    {
        return $this->hasMany(StageAdvancementRule::class, 'target_stage_id');
    }
}
