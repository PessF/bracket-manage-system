<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ParticipantStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Participant extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'external_participants';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tournament_id',
        'team_name',
        'team_code',
        'school',
        'coach_name',
        'seed_number',
        'status',
        'source_created_at',
        'synced_at',
    ];

    protected $casts = [
        'seed_number' => 'integer',
        'status' => ParticipantStatus::class,
        'source_created_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ParticipantMember::class);
    }

    public function matchesAsParticipantA(): HasMany
    {
        return $this->hasMany(TournamentMatch::class, 'participant_a_id');
    }

    public function matchesAsParticipantB(): HasMany
    {
        return $this->hasMany(TournamentMatch::class, 'participant_b_id');
    }

    public function wonMatches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class, 'winner_id');
    }

    public function lostMatches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class, 'loser_id');
    }

    public function rankingAttempts(): HasMany
    {
        return $this->hasMany(RankingAttempt::class);
    }

    public function standing(): HasOne
    {
        return $this->hasOne(Standing::class);
    }
}
