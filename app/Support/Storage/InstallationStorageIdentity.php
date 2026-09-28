<?php

namespace App\Support\Storage;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Concurrency-safe installation tenant UUID for Community persistent storage.
 *
 * Persistence lives in app_settings. Migrations insert the initial row
 * directly and must not call this service.
 */
class InstallationStorageIdentity
{
    public const SETTING_KEY = 'installation_storage_tenant_uuid';

    public function ensure(): string
    {
        $existing = $this->existingValidUuid();

        if ($existing !== null) {
            return $existing;
        }

        if ($this->hasPrivateBindings()) {
            throw new InstallationStorageIdentityMissingException(
                'Installation storage identity is missing while private artwork-photo bindings exist.',
            );
        }

        $repaired = $this->repairInvalidRowIfPresent();

        if ($repaired !== null) {
            return $repaired;
        }

        $uuid = (string) Str::uuid();

        try {
            $this->insertIdentity($uuid);
        } catch (UniqueConstraintViolationException) {
            $winner = $this->existingValidUuid();

            if ($winner !== null) {
                return $winner;
            }

            throw new InstallationStorageIdentityMissingException(
                'Installation storage identity could not be established.',
            );
        }

        return $uuid;
    }

    private function existingValidUuid(): ?string
    {
        $value = DB::table('app_settings')
            ->where('setting_key', self::SETTING_KEY)
            ->value('setting_value');

        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return $this->isUuid($normalized) ? $normalized : null;
    }

    /**
     * Repair an existing invalid row with a compare-and-swap update.
     *
     * Returns null when no row exists so the caller can insert.
     * A valid stored UUID is never overwritten.
     */
    private function repairInvalidRowIfPresent(): ?string
    {
        $row = DB::table('app_settings')
            ->where('setting_key', self::SETTING_KEY)
            ->first();

        if ($row === null) {
            return null;
        }

        $expectedRaw = $row->setting_value;

        if (is_string($expectedRaw) && $this->isUuid(strtolower(trim($expectedRaw)))) {
            return strtolower(trim($expectedRaw));
        }

        if ($this->hasPrivateBindings()) {
            throw new InstallationStorageIdentityMissingException(
                'Installation storage identity is missing while private artwork-photo bindings exist.',
            );
        }

        $uuid = (string) Str::uuid();
        $updated = $this->compareAndSwapSettingValue($expectedRaw, $uuid);

        if ($updated > 0) {
            return $uuid;
        }

        $winner = $this->existingValidUuid();

        if ($winner !== null) {
            return $winner;
        }

        throw new InstallationStorageIdentityMissingException(
            'Installation storage identity could not be established.',
        );
    }

    /**
     * Update the setting only when its stored value still equals $expectedRaw.
     * SQL NULL is matched with WHERE setting_value IS NULL, not "=".
     *
     * Protected so focused tests can invoke this production method and
     * deterministically interleave a competing write before the swap.
     */
    protected function compareAndSwapSettingValue(mixed $expectedRaw, string $newUuid): int
    {
        $query = DB::table('app_settings')
            ->where('setting_key', self::SETTING_KEY);

        if ($expectedRaw === null) {
            $query->whereNull('setting_value');
        } else {
            $query->where('setting_value', $expectedRaw);
        }

        return $query->update([
            'setting_value' => $newUuid,
            'updated_at' => now(),
        ]);
    }

    /**
     * Protected so focused tests can interleave a competing insert before this write.
     */
    protected function insertIdentity(string $uuid): void
    {
        DB::table('app_settings')->insert([
            'setting_key' => self::SETTING_KEY,
            'setting_value' => $uuid,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function hasPrivateBindings(): bool
    {
        return DB::table('artwork_photos')
            ->where(function ($query): void {
                $query->whereNotNull('storage_key')
                    ->orWhereNotNull('storage_config_target');
            })
            ->exists();
    }

    private function isUuid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $value,
        );
    }
}
