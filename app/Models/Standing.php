<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Standing extends Model
{
    use HasCompositePrimaryKey;

    protected $table = 'external_standings';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected array $compositeKey = ['tournament_id', 'participant_id'];

    protected $fillable = [
        'tournament_id',
        'participant_id',
        'rank_number',
        'best_value',
        'played',
        'wins',
        'draws',
        'losses',
        'score_for',
        'score_against',
        'score_difference',
        'points',
        'format_data',
        'synced_at',
    ];

    protected $casts = [
        'rank_number' => 'integer',
        'best_value' => 'decimal:6',
        'played' => 'integer',
        'wins' => 'integer',
        'draws' => 'integer',
        'losses' => 'integer',
        'score_for' => 'decimal:6',
        'score_against' => 'decimal:6',
        'score_difference' => 'decimal:6',
        'points' => 'integer',
        'format_data' => 'array',
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
