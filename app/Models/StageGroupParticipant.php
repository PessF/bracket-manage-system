<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StageGroupParticipant extends Model
{
    protected $table = 'external_stage_group_participants';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'tournament_id',
        'stage_id',
        'group_id',
        'participant_id',
        'slot_number',
        'source_created_at',
    ];

    protected $casts = [
        'slot_number' => 'integer',
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

    public function group(): BelongsTo
    {
        return $this->belongsTo(StageGroup::class, 'group_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
