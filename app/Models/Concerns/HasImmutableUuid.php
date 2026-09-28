<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Immutable opaque UUID separate from the integer primary key.
 *
 * Not fillable — generated on create and frozen against ordinary updates.
 * Not used for authorization, ordering, or route binding in B4.1 S1.
 *
 * Assignment also runs in performInsert so seeders that use WithoutModelEvents
 * still receive UUIDs.
 */
trait HasImmutableUuid
{
    public static function bootHasImmutableUuid(): void
    {
        static::creating(function (Model $model): void {
            $model->ensureImmutableUuidAssigned();
        });

        static::updating(function (Model $model): void {
            $model->restoreImmutableUuidIfDirty();
        });
    }

    /**
     * @param  Builder<*>  $query
     */
    protected function performInsert(Builder $query): bool
    {
        $this->ensureImmutableUuidAssigned();

        return parent::performInsert($query);
    }

    /**
     * @param  Builder<*>  $query
     */
    protected function performUpdate(Builder $query): bool
    {
        $this->restoreImmutableUuidIfDirty();

        return parent::performUpdate($query);
    }

    protected function ensureImmutableUuidAssigned(): void
    {
        if (blank($this->getAttribute('uuid'))) {
            $this->setAttribute('uuid', (string) Str::uuid());
        }
    }

    protected function restoreImmutableUuidIfDirty(): void
    {
        if ($this->isDirty('uuid')) {
            $this->setAttribute('uuid', $this->getOriginal('uuid'));
        }
    }
}
