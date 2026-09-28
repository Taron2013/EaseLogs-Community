<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class DisposableSqliteSafetyInitializationSequenceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<string>
     */
    private array $migrationHookCalls = [];

    /**
     * PHPUnit setUp must boot with safe :memory: and must not enter
     * RefreshDatabase / migrate:fresh. The production TestCase hook is
     * invoked explicitly in the test after an unsafe config swap.
     */
    protected function setUpTraits()
    {
        $this->assertDisposableSqliteTestDatabase();
    }

    /**
     * Spy only. Never call parent::migrateDatabases().
     */
    protected function migrateDatabases(): void
    {
        $this->migrationHookCalls[] = 'migrateDatabases';
    }

    public function test_unsafe_config_is_rejected_before_automatic_migration(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame([], $this->migrationHookCalls);

        $original = [
            'database.default' => config('database.default'),
            'database.connections.sqlite.database' => config('database.connections.sqlite.database'),
            'database.connections.sqlite.url' => config('database.connections.sqlite.url'),
        ];

        config([
            'database.connections.sqlite.database' => database_path('database.sqlite'),
            'database.connections.sqlite.url' => null,
        ]);

        try {
            (new ReflectionMethod(TestCase::class, 'setUpTraits'))->invoke($this);
            $this->fail('Expected the TestCase safety hook to reject the development database.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('refused development database', $e->getMessage());
            $this->assertSame([], $this->migrationHookCalls);
        } finally {
            config($original);
        }

        $this->assertSame($original['database.connections.sqlite.database'], config('database.connections.sqlite.database'));
        $this->assertSame([], $this->migrationHookCalls);
    }

    protected function tearDown(): void
    {
        $this->restoreSafeSqliteConfiguration();

        parent::tearDown();
    }

    private function restoreSafeSqliteConfiguration(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
        ]);
    }
}
