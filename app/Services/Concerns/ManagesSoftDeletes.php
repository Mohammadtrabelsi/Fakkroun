<?php

namespace App\Services\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Trash-aware queries for services whose model uses SoftDeletes. Kept apart
 * from ManagesModel so a service for a model without soft deletes cannot
 * offer withTrashed() / onlyTrashed() (they would fail at run time).
 *
 * @template TModel of Model
 */
trait ManagesSoftDeletes
{
    /**
     * @return Builder<TModel>
     */
    abstract public function query(): Builder;

    /**
     * A query that also includes soft-deleted rows.
     *
     * @return Builder<TModel>
     */
    public function withTrashed(): Builder
    {
        /** @phpstan-ignore method.notFound (added to the builder by SoftDeletes) */
        return $this->query()->withTrashed();
    }

    /**
     * A query limited to soft-deleted rows.
     *
     * @return Builder<TModel>
     */
    public function onlyTrashed(): Builder
    {
        /** @phpstan-ignore method.notFound (added to the builder by SoftDeletes) */
        return $this->query()->onlyTrashed();
    }
}
