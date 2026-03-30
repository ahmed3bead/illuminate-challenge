<?php

declare(strict_types=1);

namespace App\Models;

use App\Relations\DonutRelation;
use Illuminate\Database\Eloquent\Model;

class Neighborhood extends Model
{
    public $timestamps = false;

    protected $fillable = ['id', 'name', 'boundary', 'properties'];

    protected $casts = [
        'properties' => 'array',
    ];

    /**
     * Return the centroid [lat, lng] derived from the bounding box of the boundary polygon.
     *
     * The boundary is stored as a PostgreSQL polygon string, e.g.:
     *   ((33.32,44.34),(33.325,44.365),(33.34,44.365),(33.34,44.34))
     *
     * @return array{float, float}
     */
    public function centroid(): array
    {
        preg_match_all('/\(([+-]?[\d.]+),([+-]?[\d.]+)\)/', (string) $this->boundary, $matches);

        $lats = array_map('floatval', $matches[1]);
        $lngs = array_map('floatval', $matches[2]);

        return [
            (min($lats) + max($lats)) / 2.0,
            (min($lngs) + max($lngs)) / 2.0,
        ];
    }

    /**
     * All incidents within a donut (annulus) around this neighborhood's centroid.
     */
    public function incidents(float $innerKm = 0.5, float $outerKm = 2.0): DonutRelation
    {
        return new DonutRelation($this, $innerKm, $outerKm);
    }
}
