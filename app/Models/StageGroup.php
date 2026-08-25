<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StageGroup extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'external_stage_groups';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tournament_id',
        'stage_id',
        'name',
        'group_order',
        'team_limit',
        'config',
        'source_created_at',
    ];

    protected $casts = [
        'group_order' => 'integer',
        'team_limit' => 'integer',
        'config' => 'array',
        'source_created_at' => 'datetime',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(StageGroupParticipant::class, 'group_id');
    }
}
