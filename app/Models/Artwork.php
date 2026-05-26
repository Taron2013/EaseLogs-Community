<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Artwork extends Model
{
    public const STATUSES = [
        'in_progress',
        'in_inventory',
        'available',
        'reserved',
        'sold',
        'gifted',
        'on_display',
        'in_storage',
        'archived',
    ];

    public const CONDITIONS = [
        'excellent',
        'good',
        'fair',
        'needs_repair',
        'damaged',
    ];

    /** @use HasFactory<\Database\Factories\ArtworkFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'in_progress',
        'title' => '',
        'professional_art_reproduction_photo' => false,
    ];

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
        'professional_art_reproduction_photo',
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
            'professional_art_reproduction_photo' => 'boolean',
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

    public function latestPhoto(): HasOne
    {
        return $this->hasOne(ArtworkPhoto::class)->ofMany(
            ['is_primary' => 'max', 'uploaded_at' => 'max', 'id' => 'max'],
        );
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
