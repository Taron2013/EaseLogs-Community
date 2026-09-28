<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\RequiresDisposableSqliteTestDatabase;

abstract class TestCase extends BaseTestCase
{
    use RequiresDisposableSqliteTestDatabase;

    /**
     * RefreshDatabase is imported on child classes, so its empty
     * beforeRefreshingDatabase() would shadow a parent hook. setUpTraits()
     * is a class method: it runs after the app exists and before
     * RefreshDatabase::refreshDatabase() can migrate:fresh.
     */
    protected function setUpTraits()
    {
        $this->assertDisposableSqliteTestDatabase();

        return parent::setUpTraits();
    }

    protected function signIn(?User $user = null): User
    {
        $user ??= User::factory()->create();
        $this->actingAs($user);

        return $user;
    }
}
