<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    public $timestamps = false;

    protected $fillable = ['id', 'lat', 'lng', 'metadata', 'tags', 'occurred_at'];

    protected $casts = [
        'metadata'    => 'array',
        'tags'        => 'array',
        'occurred_at' => 'datetime',
    ];

    /**
     * The incident code extracted from metadata.
     */
    public function getCodeAttribute(): string
    {
        $meta = is_array($this->metadata)
            ? $this->metadata
            : json_decode((string) $this->metadata, true);

        return (string) data_get($meta, 'incident.code', '');
    }
}
