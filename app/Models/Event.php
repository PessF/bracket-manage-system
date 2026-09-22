<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An event owns competitions; match data continues to belong to a Tournament. */
class Event extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'description', 'venue', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function competitions(): HasMany
    {
        return $this->hasMany(Tournament::class);
    }
}
