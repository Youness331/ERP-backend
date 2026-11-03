<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Adds a flexible "search" scope driven by the model's searchable columns.
 */
trait Searchable
{
    /**
     * Columns scanned by the "search" query parameter.
     *
     * @return list<string>
     */
    abstract protected function searchableColumns(): array;

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $builder) use ($like) {
            foreach ($this->searchableColumns() as $column) {
                $builder->orWhere($column, 'like', $like);
            }
        });
    }
}
