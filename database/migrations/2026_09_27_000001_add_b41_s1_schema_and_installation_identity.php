<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * B4.1 S1 — CE UUIDs, nullable private-storage binding columns, installation UUID.
 *
 * The installation UUID is inserted here with Str::uuid(). This migration must
 * not call InstallationStorageIdentity or any other application service.
 */
return new class extends Migration
{
    private const INSTALLATION_SETTING_KEY = 'installation_storage_tenant_uuid';

    /**
     * @var list<string>
     */
    private array $uuidTables = [
        'artworks',
        'artwork_photos',
    ];

    public function up(): void
    {
        foreach ($this->uuidTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->uuid('uuid')->nullable();
            });

            $this->backfillUuids($tableName);

            $missing = DB::table($tableName)->whereNull('uuid')->count();
            if ($missing > 0) {
                throw new RuntimeException(
                    "UUID backfill left {$missing} NULL row(s) on {$tableName}.",
                );
            }

            $duplicateUuids = DB::table($tableName)
                ->select('uuid', DB::raw('COUNT(*) as uuid_count'))
                ->groupBy('uuid')
                ->having('uuid_count', '>', 1)
                ->limit(1)
                ->get();

            if ($duplicateUuids->isNotEmpty()) {
                throw new RuntimeException(
                    "UUID backfill produced duplicate uuid values on {$tableName}.",
                );
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->unique('uuid');
            });

            Schema::table($tableName, function (Blueprint $table): void {
                $table->uuid('uuid')->nullable(false)->change();
            });
        }

        Schema::table('artwork_photos', function (Blueprint $table): void {
            $table->string('file_path')->nullable()->change();
            $table->string('storage_key')->nullable()->after('file_path');
            $table->string('storage_config_target')->nullable()->after('storage_key');
            $table->index('storage_key');
        });

        $nullPaths = DB::table('artwork_photos')->whereNull('file_path')->count();
        if ($nullPaths > 0) {
            throw new RuntimeException(
                "B4.1 S1 expected existing artwork_photos.file_path values to remain intact; found {$nullPaths} NULL path(s).",
            );
        }

        $boundRows = $this->privateBindingEvidenceCount();
        if ($boundRows > 0) {
            throw new RuntimeException(
                "B4.1 S1 must not backfill private storage bindings; found {$boundRows} pre-bound row(s).",
            );
        }

        $this->insertInstallationUuid();
    }

    public function down(): void
    {
        $nullPaths = DB::table('artwork_photos')->whereNull('file_path')->count();
        if ($nullPaths > 0) {
            throw new RuntimeException(
                "Cannot rollback B4.1 S1 while {$nullPaths} artwork_photos row(s) have a NULL file_path.",
            );
        }

        $boundRows = $this->privateBindingEvidenceCount();
        if ($boundRows > 0) {
            throw new RuntimeException(
                "Cannot rollback B4.1 S1 while {$boundRows} artwork_photos row(s) have private storage binding evidence.",
            );
        }

        Schema::table('artwork_photos', function (Blueprint $table): void {
            $table->dropIndex(['storage_key']);
            $table->dropColumn(['storage_config_target', 'storage_key']);
        });

        Schema::table('artwork_photos', function (Blueprint $table): void {
            $table->string('file_path')->nullable(false)->change();
        });

        foreach (array_reverse($this->uuidTables) as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropUnique(['uuid']);
            });

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('uuid');
            });
        }
    }

    private function backfillUuids(string $tableName): void
    {
        DB::table($tableName)
            ->whereNull('uuid')
            ->orderBy('id')
            ->select('id')
            ->chunkById(100, function ($rows) use ($tableName): void {
                foreach ($rows as $row) {
                    DB::table($tableName)
                        ->where('id', $row->id)
                        ->whereNull('uuid')
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            });
    }

    private function insertInstallationUuid(): void
    {
        $row = DB::table('app_settings')
            ->where('setting_key', self::INSTALLATION_SETTING_KEY)
            ->first();

        if ($row !== null && is_string($row->setting_value)
            && $this->isUuid(strtolower(trim($row->setting_value)))) {
            return;
        }

        if ($this->privateBindingEvidenceCount() > 0) {
            throw new RuntimeException(
                'Cannot create or replace installation_storage_tenant_uuid while private artwork-photo bindings exist.',
            );
        }

        $now = now();
        $uuid = (string) Str::uuid();

        if ($row !== null) {
            DB::table('app_settings')
                ->where('setting_key', self::INSTALLATION_SETTING_KEY)
                ->update([
                    'setting_value' => $uuid,
                    'updated_at' => $now,
                ]);

            return;
        }

        DB::table('app_settings')->insert([
            'setting_key' => self::INSTALLATION_SETTING_KEY,
            'setting_value' => $uuid,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Any non-null storage_key or storage_config_target is binding evidence,
     * including empty strings.
     */
    private function privateBindingEvidenceCount(): int
    {
        return DB::table('artwork_photos')
            ->where(function ($query): void {
                $query->whereNotNull('storage_key')
                    ->orWhereNotNull('storage_config_target');
            })
            ->count();
    }

    private function isUuid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $value,
        );
    }
};
