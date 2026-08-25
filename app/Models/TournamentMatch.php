<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BracketType;
use App\Enums\MatchOutcome;
use App\Enums\MatchSlot;
use App\Enums\MatchStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentMatch extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'external_matches';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tournament_id',
        'stage_id',
        'stage_group_id',
        'match_number',
        'bracket_type',
        'round_number',
        'status',
        'is_bye',
        'participant_a_id',
        'participant_a_label',
        'participant_a_source_match_id',
        'participant_a_source_outcome',
        'participant_b_id',
        'participant_b_label',
        'participant_b_source_match_id',
        'participant_b_source_outcome',
        'score_a',
        'score_b',
        'winner_id',
        'loser_id',
        'winner_next_match_id',
        'winner_next_slot',
        'loser_next_match_id',
        'loser_next_slot',
        'started_at',
        'finished_at',
        'synced_at',
    ];

    protected $casts = [
        'match_number' => 'integer',
        'bracket_type' => BracketType::class,
        'round_number' => 'integer',
        'status' => MatchStatus::class,
        'is_bye' => 'boolean',
        'participant_a_source_outcome' => MatchOutcome::class,
        'participant_b_source_outcome' => MatchOutcome::class,
        'score_a' => 'decimal:6',
        'score_b' => 'decimal:6',
        'winner_next_slot' => MatchSlot::class,
        'loser_next_slot' => MatchSlot::class,
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function stageGroup(): BelongsTo
    {
        return $this->belongsTo(StageGroup::class, 'stage_group_id');
    }

    public function participantA(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'participant_a_id');
    }

    public function participantB(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'participant_b_id');
    }

    public function participantASourceMatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'participant_a_source_match_id');
    }

    public function participantBSourceMatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'participant_b_source_match_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'winner_id');
    }

    public function loser(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'loser_id');
    }

    public function winnerNextMatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'winner_next_match_id');
    }

    public function loserNextMatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'loser_next_match_id');
    }

    public function incomingWinnerMatches(): HasMany
    {
        return $this->hasMany(self::class, 'winner_next_match_id');
    }

    public function incomingLoserMatches(): HasMany
    {
        return $this->hasMany(self::class, 'loser_next_match_id');
    }

    public function participantALabel(): string
    {
        return $this->localizedParticipantLabel($this->participant_a_label);
    }

    public function participantBLabel(): string
    {
        return $this->localizedParticipantLabel($this->participant_b_label);
    }

    private function localizedParticipantLabel(?string $label): string
    {
        if (preg_match('/^(Winner|Loser) of Match #(\d+)$/', (string) $label, $matches) === 1) {
            return __($matches[1] === 'Winner' ? 'ui.source_winner_label' : 'ui.source_loser_label', ['number' => $matches[2]]);
        }

        return match ($label) {
            null, '', 'TBD' => __('ui.to_be_determined'),
            'BYE' => __('ui.bye'),
            'Unknown participant' => __('ui.unknown_participant'),
            default => $label,
        };
    }
}
