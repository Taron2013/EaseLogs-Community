<?php

namespace Tests\Unit;

use RuntimeException;
use Tests\TestCase;

/**
 * Non-destructive verification of the pre-migration SQLite safety gate.
 * Rejection cases swap config() only. They never run artisan migrate/rollback
 * against a development database.
 */
class DisposableSqliteTestDatabaseSafetyTest extends TestCase
{
    public function test_in_memory_sqlite_configuration_passes(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertTrue(blank(config('database.connections.sqlite.url')));

        $this->assertDisposableSqliteTestDatabase();
    }

    public function test_community_development_sqlite_path_is_rejected(): void
    {
        config([
            'database.connections.sqlite.database' => database_path('database.sqlite'),
            'database.connections.sqlite.url' => null,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('refused development database');
        $this->assertDisposableSqliteTestDatabase();
    }

    public function test_professional_development_sqlite_path_is_rejected(): void
    {
        config([
            'database.connections.sqlite.database' => '/srv/projects/git/EaseLogs-Pro/database/database.sqlite',
            'database.connections.sqlite.url' => null,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not an approved disposable test database');
        $this->assertDisposableSqliteTestDatabase();
    }

    public function test_non_sqlite_driver_is_rejected(): void
    {
        config(['database.default' => 'mysql']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('only sqlite is permitted');
        $this->assertDisposableSqliteTestDatabase();
    }

    public function test_unexpected_database_url_is_rejected(): void
    {
        config(['database.connections.sqlite.url' => 'sqlite:///tmp/unexpected.sqlite']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unexpected database URL');
        $this->assertDisposableSqliteTestDatabase();
    }

    public function test_unapproved_sqlite_file_path_is_rejected(): void
    {
        config([
            'database.connections.sqlite.database' => storage_path('unapproved-test.sqlite'),
            'database.connections.sqlite.url' => null,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not an approved disposable test database');
        $this->assertDisposableSqliteTestDatabase();
    }
}
