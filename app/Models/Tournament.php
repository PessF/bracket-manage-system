<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RankingType;
use App\Enums\SeedingMethod;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Enums\TournamentStructure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Tournament extends Model
{
    use HasFactory, HasUuids;

    /** Match team names or individual members without duplicating competitions. */
    public function scopeWithParticipantSearch(Builder $query, string $search): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }

        // Use an explicit escape character so %, _ and ! are literal search text.
        $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%';
        $query->whereHas('participants', fn ($participants) => $participants->where(
            fn ($names) => $names->whereRaw("LOWER(team_name) LIKE ? ESCAPE '!'", [$term])
                ->orWhereHas('members', fn ($members) => $members->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term]))
        ));
    }

    protected $table = 'external_tournaments';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'event_id',
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
            // Older integrations and seeders can still create competitions without
            // event_id. Keep them inside the hierarchy rather than orphaning them.
            $tournament->event_id ??= Event::unguarded(fn () => Event::firstOrCreate(
                ['id' => '00000000-0000-4000-8000-000000000001'],
                ['name' => 'Existing competitions'],
            ))->id;
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
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
