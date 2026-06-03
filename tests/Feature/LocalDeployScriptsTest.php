<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocalDeployScriptsTest extends TestCase
{
    public function test_community_redeploy_script_targets_easelogs_local_path(): void
    {
        $path = base_path('scripts/redeploy-local.sh');

        $this->assertFileExists($path);
        $this->assertFileIsReadable($path);

        $contents = file_get_contents($path);

        $this->assertStringContainsString('/var/www/projects/easelogs', $contents);
        $this->assertStringContainsString('easelogs.local', $contents);
        $this->assertStringNotContainsString('easelog-pro', $contents);
        $this->assertStringNotContainsString('easelogs.pro', $contents);
        $this->assertStringContainsString('storage:link', $contents);
        $this->assertStringContainsString(
            'resources/views/vendor/pagination/easelogs.blade.php',
            $contents,
        );
        $this->assertStringContainsString('--exclude="/vendor/"', $contents);
        $this->assertStringNotContainsString('--exclude="vendor"', $contents);
    }

    public function test_verify_script_checks_community_deploy_only(): void
    {
        $contents = file_get_contents(base_path('scripts/verify-local-deployments.sh'));

        $this->assertStringContainsString('/var/www/projects/easelogs', $contents);
        $this->assertStringContainsString('easelogs.local', $contents);
        $this->assertStringNotContainsString('easelog-pro', $contents);
        $this->assertStringNotContainsString('easelogs.pro', $contents);
    }
}
