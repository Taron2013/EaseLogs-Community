<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Artwork extends Model
{
    /** @use HasFactory<\Database\Factories\ArtworkFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'inventory_code',
        'sku',
        'title',
        'description',
        'started_date',
        'started_date_is_estimated',
        'finished_date',
        'finished_date_is_estimated',
        'medium',
        'surface',
        'width',
        'height',
        'depth',
        'dimension_unit',
        'category',
        'style',
        'subject',
        'status',
        'condition',
        'location',
        'storage_area',
        'estimated_value',
        'sale_price',
        'currency',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_date' => 'date',
            'finished_date' => 'date',
            'started_date_is_estimated' => 'boolean',
            'finished_date_is_estimated' => 'boolean',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'depth' => 'decimal:2',
            'estimated_value' => 'decimal:2',
            'sale_price' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ArtworkPhoto::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ArtworkEvent::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ArtworkTag::class, 'artwork_tag');
    }
}
