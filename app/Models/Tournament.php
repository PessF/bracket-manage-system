<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RankingType;
use App\Enums\SeedingMethod;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Enums\TournamentStructure;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Tournament extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'external_tournaments';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'public_token',
        'name',
        'competition',
        'division',
        'structure',
        'format',
        'seeding_method',
        'status',
        'display_order',
        'participant_count',
        'competition_date',
        'bracket_schedule_start_time',
        'bracket_match_duration_minutes',
        'venue',
        'notes',
        'ranking_config',
        'round_robin_config',
        'double_elimination_config',
        'locked_at',
        'started_at',
        'completed_at',
        'advanced_config',
        'source_created_at',
        'source_updated_at',
        'synced_at',
    ];

    protected $hidden = [
        'public_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (Tournament $tournament): void {
            $tournament->public_token ??= (string) Str::uuid();
        });
    }

    public function publicShareUrl(): ?string
    {
        if (! is_string($this->public_token) || $this->public_token === '') {
            return null;
        }

        return route('public.tournaments.show', ['tournament' => $this->public_token]);
    }

    public function rankingType(): RankingType
    {
        $configured = RankingType::tryFrom((string) ($this->ranking_config['type'] ?? ''));

        if ($configured !== null) {
            return $configured;
        }

        return ($this->ranking_config['comparator'] ?? null) === 'BEST_TIME_LOWER'
            ? RankingType::RACING_ROBOT
            : RankingType::DRONE_MISSION;
    }

    public function rankingAttemptLimit(): int
    {
        return max(1, min(20, (int) ($this->ranking_config['attempts'] ?? 2)));
    }

    public function competitionProgressPercentage(): int
    {
        if ($this->status === TournamentStatus::COMPLETED) {
            return 100;
        }

        if ($this->format === TournamentFormat::RANKING) {
            $total = ((int) ($this->participants_count ?? $this->participant_count)) * $this->rankingAttemptLimit();
            $completed = (int) ($this->ranking_attempts_count ?? 0);
        } else {
            $total = (int) ($this->progress_total_matches_count ?? 0);
            $completed = (int) ($this->progress_completed_matches_count ?? 0);
        }

        return $total > 0 ? max(0, min(100, (int) round(($completed / $total) * 100))) : 0;
    }

    protected $casts = [
        'format' => TournamentFormat::class,
        'structure' => TournamentStructure::class,
        'seeding_method' => SeedingMethod::class,
        'status' => TournamentStatus::class,
        'participant_count' => 'integer',
        'display_order' => 'integer',
        'competition_date' => 'datetime',
        'bracket_match_duration_minutes' => 'integer',
        'ranking_config' => 'array',
        'round_robin_config' => 'array',
        'double_elimination_config' => 'array',
        'locked_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'advanced_config' => 'array',
        'source_created_at' => 'datetime',
        'source_updated_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(StageGroup::class);
    }

    public function advancementRules(): HasMany
    {
        return $this->hasMany(StageAdvancementRule::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class);
    }

    public function rankingAttempts(): HasMany
    {
        return $this->hasMany(RankingAttempt::class);
    }

    public function standings(): HasMany
    {
        return $this->hasMany(Standing::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }
}
