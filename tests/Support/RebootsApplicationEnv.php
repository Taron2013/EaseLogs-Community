<?php

namespace Tests\Support;

trait RebootsApplicationEnv
{
    protected function rebootApplicationEnv(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        } else {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        $this->app = null;
        $this->refreshApplication();
        $this->artisan('migrate', ['--force' => true]);
    }
}
