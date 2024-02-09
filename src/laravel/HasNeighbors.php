<?php

declare(strict_types=1);

namespace Pgvector\Laravel;

use Illuminate\Database\Eloquent\Builder;

trait HasNeighbors
{
    public function scopeNearestNeighbors(Builder $query, string $column, mixed $value, Distance $distance): void
    {
        $op = match ($distance) {
            Distance::L2 => '<->',
            Distance::InnerProduct => '<#>',
            Distance::Cosine => '<=>',
        };

        $wrapped = $query->getGrammar()->wrap($column);
        $order = "$wrapped $op ?";
        $neighborDistance = $distance == Distance::InnerProduct ? "($order) * -1" : $order;
        $vector = $value instanceof Vector ? $value : new Vector($value);

        // ideally preserve existing select, but does not appear to be a way to get columns
        $query->select()
            ->selectRaw("$neighborDistance AS neighbor_distance", [$vector])
            ->withCasts(['neighbor_distance' => 'double'])
            ->whereNotNull($column)
            ->orderByRaw($order, [$vector]);
    }

    public function nearestNeighbors(string $column, int $distance): Builder
    {
        $id = $this->getKey();
        if (!array_key_exists($column, $this->attributes)) {
            // TODO use MissingAttributeException when Laravel 9 no longer supported
            throw new \OutOfBoundsException('Missing attribute');
        }
        $value = $this->getAttributeValue($column);
        return static::whereKeyNot($id)->nearestNeighbors($column, $value, $distance);
    }
}
