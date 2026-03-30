<?php

declare(strict_types=1);

namespace App\Relations;

use App\Models\Incident;
use App\Models\Neighborhood;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * A custom Eloquent relation that finds all Incidents within a donut-shaped
 * area around the centroid of a Neighborhood.
 *
 * The centroid is derived from the neighborhood's bounding box, and distance
 * is calculated using the Haversine formula so results are in real kilometres.
 */
class DonutRelation extends Relation
{
    private const EARTH_RADIUS_KM = 6371.0;

    private readonly Neighborhood $neighborhood;

    public function __construct(
        Neighborhood $neighborhood,
        private readonly float $innerKm,
        private readonly float $outerKm,
    ) {
        // Assign properties BEFORE calling parent::__construct, because the
        // parent constructor immediately calls addConstraints() which needs them.
        $this->neighborhood = $neighborhood;

        parent::__construct(
            Incident::query(),
            $neighborhood,
        );
    }

    /**
     * Set up the base query with the Haversine donut constraint.
     */
    public function addConstraints(): void
    {
        [$clat, $clng] = $this->neighborhood->centroid();

        $distExpr = self::EARTH_RADIUS_KM.' * 2 * asin(sqrt('
            .'power(sin(radians((lat - ?) / 2)), 2) + '
            .'cos(radians(?)) * cos(radians(lat)) * '
            .'power(sin(radians((lng - ?) / 2)), 2)'
            .'))';

        // Note: innerKm/outerKm are interpolated directly (not bound) because
        // SQLite compares bound float parameters as text, breaking BETWEEN.
        // They are cast to float so no user input can reach this interpolation.
        $inner = (float) $this->innerKm;
        $outer = (float) $this->outerKm;

        $this->query
            ->selectRaw('*, ('.$distExpr.') AS dist_km', [$clat, $clat, $clng])
            ->whereRaw('('.$distExpr.') BETWEEN '.$inner.' AND '.$outer, [$clat, $clat, $clng])
            ->orderByRaw('('.$distExpr.')', [$clat, $clat, $clng]);
    }

    /**
     * Eager loading: apply the same constraint for a collection of parents.
     *
     * @param array<int, Model> $models
     */
    public function addEagerConstraints(array $models): void
    {
        // Each neighborhood has its own centroid so we simply re-apply
        // the single-model constraint (eager loading one parent at a time
        // is acceptable for this relation type).
        $this->addConstraints();
    }

    /**
     * @param array<int, Model> $models
     * @param  Collection<int, Incident>  $results
     * @return array<int, Model>
     */
    public function initRelation(array $models, $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    /**
     * @param array<int, Model> $models
     * @param  Collection<int, Incident>  $results
     * @return array<int, Model>
     */
    public function match(array $models, Collection $results, $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $results);
        }

        return $models;
    }

    /**
     * @return Collection<int, Incident>
     */
    public function getResults(): Collection
    {
        /** @var Collection<int, Incident> */
        return $this->query->get();
    }

    /**
     * Return the underlying query builder so callers can chain further scopes.
     *
     * @return Builder<Incident>
     */
    public function getQuery(): Builder
    {
        /** @var Builder<Incident> */
        return $this->query;
    }
}
