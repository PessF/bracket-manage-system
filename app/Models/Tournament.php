<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SeedingMethod;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tournament extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'external_tournaments';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'competition',
        'division',
        'format',
        'seeding_method',
        'status',
        'participant_count',
        'competition_date',
        'venue',
        'notes',
        'ranking_config',
        'round_robin_config',
        'locked_at',
        'started_at',
        'completed_at',
        'source_created_at',
        'source_updated_at',
        'synced_at',
    ];

    protected $casts = [
        'format' => TournamentFormat::class,
        'seeding_method' => SeedingMethod::class,
        'status' => TournamentStatus::class,
        'participant_count' => 'integer',
        'competition_date' => 'datetime',
        'ranking_config' => 'array',
        'round_robin_config' => 'array',
        'locked_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'source_created_at' => 'datetime',
        'source_updated_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class);
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
