<?php

namespace App\Services\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Makes a service the single gateway to one Eloquent model.
 *
 * Every read or write of a model goes through its own service: code outside
 * that service never calls the model statically (Model::query(), ::create(),
 * ::find(), ...). The service declares which model it owns via model(), and
 * gets the generic data-access methods below; it can override any of them
 * with a richer, domain-specific version (the class method wins over the
 * trait method).
 *
 * @template TModel of Model
 */
trait ManagesModel
{
    /**
     * @return class-string<TModel>
     */
    abstract protected function model(): string;

    /**
     * A fresh query builder for the owned model.
     *
     * @return Builder<TModel>
     */
    public function query(): Builder
    {
        return ($this->model())::query();
    }

    /**
     * @return TModel|null
     */
    public function find(mixed $id): ?Model
    {
        return $this->query()->find($id);
    }

    /**
     * @return TModel
     */
    public function findOrFail(mixed $id): Model
    {
        return $this->query()->findOrFail($id);
    }

    /**
     * The first record whose column equals the value, if any.
     *
     * @return TModel|null
     */
    public function findBy(string $column, mixed $value): ?Model
    {
        return $this->query()->where($column, $value)->first();
    }

    /**
     * @return Collection<int, TModel>
     */
    public function all(): Collection
    {
        return $this->query()->get();
    }

    public function count(): int
    {
        return $this->query()->count();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function create(array $attributes): Model
    {
        return $this->query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     * @return TModel
     */
    public function firstOrCreate(array $attributes, array $values = []): Model
    {
        return $this->query()->firstOrCreate($attributes, $values);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     * @return TModel
     */
    public function updateOrCreate(array $attributes, array $values = []): Model
    {
        return $this->query()->updateOrCreate($attributes, $values);
    }

    /**
     * @param  TModel  $model
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function update(Model $model, array $attributes): Model
    {
        $model->fill($attributes);
        $model->save();

        return $model;
    }

    /**
     * Persists a model built or changed by the caller.
     *
     * @param  TModel  $model
     * @return TModel
     */
    public function save(Model $model): Model
    {
        $model->save();

        return $model;
    }

    /**
     * @param  TModel  $model
     */
    public function delete(Model $model): void
    {
        $model->delete();
    }
}
