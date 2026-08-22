<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StageStatus;
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
        'format',
        'status',
        'advance_count',
        'config',
        'source_created_at',
    ];

    protected $casts = [
        'stage_order' => 'integer',
        'format' => TournamentFormat::class,
        'status' => StageStatus::class,
        'advance_count' => 'integer',
        'config' => 'array',
        'source_created_at' => 'datetime',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class);
    }
}
