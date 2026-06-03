<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ArtworkPhoto extends Model
{
    /** @use HasFactory<\Database\Factories\ArtworkPhotoFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'artwork_id',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'width',
        'height',
        'caption',
        'photo_type',
        'progress_sequence',
        'is_primary',
        'taken_at',
        'uploaded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'taken_at' => 'datetime',
            'uploaded_at' => 'datetime',
        ];
    }

    public function artwork(): BelongsTo
    {
        return $this->belongsTo(Artwork::class);
    }

    public function existsOnDisk(): bool
    {
        return $this->file_path !== ''
            && Storage::disk('public')->exists($this->file_path);
    }

    public function publicUrl(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }
}
