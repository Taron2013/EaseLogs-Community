<?php

namespace Tests\Support;

use RuntimeException;

/**
 * Fail closed unless the resolved default connection is disposable SQLite.
 *
 * Call from Tests\TestCase::setUpTraits() so this runs after the application
 * is created and before RefreshDatabase / DatabaseMigrations can migrate:fresh.
 */
trait RequiresDisposableSqliteTestDatabase
{
    protected function assertDisposableSqliteTestDatabase(): void
    {
        $default = (string) config('database.default');
        $connection = config('database.connections.'.$default);

        if (! is_array($connection)) {
            throw new RuntimeException(
                "B4.1 test database safety: missing connection [{$default}].",
            );
        }

        $driver = $connection['driver'] ?? null;
        if ($driver !== 'sqlite') {
            throw new RuntimeException(
                'B4.1 test database safety: only sqlite is permitted, got '.var_export($driver, true).'.',
            );
        }

        $url = $connection['url'] ?? null;
        if (filled($url)) {
            throw new RuntimeException(
                'B4.1 test database safety: unexpected database URL '.var_export($url, true).'.',
            );
        }

        $database = $connection['database'] ?? null;
        if ($database === ':memory:') {
            return;
        }

        if (! is_string($database) || $database === '') {
            throw new RuntimeException(
                'B4.1 test database safety: sqlite database must be :memory: or a disposable temp file.',
            );
        }

        foreach ($this->forbiddenDevelopmentSqlitePaths() as $forbidden) {
            if ($this->sqlitePathsReferToSameFile($database, $forbidden)) {
                throw new RuntimeException(
                    "B4.1 test database safety: refused development database {$database}.",
                );
            }
        }

        if (! $this->sqlitePathIsUnderTempDirectory($database)) {
            throw new RuntimeException(
                "B4.1 test database safety: {$database} is not an approved disposable test database.",
            );
        }
    }

    /**
     * @return list<string>
     */
    private function forbiddenDevelopmentSqlitePaths(): array
    {
        return [
            database_path('database.sqlite'),
            base_path('database/database.sqlite'),
        ];
    }

    private function sqlitePathsReferToSameFile(string $left, string $right): bool
    {
        $normalizedLeft = $this->normalizeSqlitePath($left);
        $normalizedRight = $this->normalizeSqlitePath($right);

        if ($normalizedLeft === $normalizedRight) {
            return true;
        }

        $realLeft = realpath($left);
        $realRight = realpath($right);

        return is_string($realLeft) && is_string($realRight) && $realLeft === $realRight;
    }

    private function sqlitePathIsUnderTempDirectory(string $database): bool
    {
        $temp = realpath(sys_get_temp_dir());
        if (! is_string($temp) || $temp === '') {
            return false;
        }

        $parent = realpath(dirname($database));
        if (! is_string($parent)) {
            $parent = $this->normalizeSqlitePath(dirname($database));
        }

        $tempPrefix = rtrim($this->normalizeSqlitePath($temp), '/').'/';
        $parentPath = rtrim($this->normalizeSqlitePath($parent), '/').'/';

        return str_starts_with($parentPath, $tempPrefix);
    }

    private function normalizeSqlitePath(string $path): string
    {
        return strtolower(str_replace('\\', '/', $path));
    }
}
