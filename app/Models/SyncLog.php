<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SyncStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    protected $table = 'external_sync_log';

    public $timestamps = false;

    protected $fillable = [
        'tournament_id',
        'status',
        'message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'status' => SyncStatus::class,
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }
}
