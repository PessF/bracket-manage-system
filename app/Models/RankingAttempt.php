<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RankingAttempt extends Model
{
    use HasCompositePrimaryKey;

    protected $table = 'external_ranking_attempts';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected array $compositeKey = [
        'tournament_id',
        'participant_id',
        'attempt_number',
    ];

    protected $fillable = [
        'tournament_id',
        'participant_id',
        'attempt_number',
        'attempt_value',
        'manual_score',
        'auto_score',
        'attempt_time',
        'is_valid',
        'synced_at',
    ];

    protected $casts = [
        'attempt_number' => 'integer',
        'attempt_value' => 'decimal:6',
        'manual_score' => 'decimal:2',
        'auto_score' => 'decimal:2',
        'attempt_time' => 'decimal:2',
        'is_valid' => 'boolean',
        'synced_at' => 'datetime',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
