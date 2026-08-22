<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasCompositePrimaryKey
{
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function setKeysForSelectQuery($query)
    {
        return $this->applyCompositeKeyConstraints($query);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function setKeysForSaveQuery($query)
    {
        return $this->applyCompositeKeyConstraints($query);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    private function applyCompositeKeyConstraints(Builder $query): Builder
    {
        foreach ($this->compositeKey as $key) {
            $value = $this->getOriginal($key, $this->getAttribute($key));
            $query->where($key, '=', $value);
        }

        return $query;
    }
}
