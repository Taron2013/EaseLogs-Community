<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocalDeployScriptsTest extends TestCase
{
    private function communityRedeployScript(): string
    {
        return file_get_contents(base_path('scripts/redeploy-local.sh'));
    }

    private function validationLib(): string
    {
        return file_get_contents(base_path('scripts/lib/redeploy-validation.sh'));
    }

    public function test_community_redeploy_script_targets_easelogs_local_path(): void
    {
        $path = base_path('scripts/redeploy-local.sh');

        $this->assertFileExists($path);
        $this->assertFileIsReadable($path);

        $contents = $this->communityRedeployScript();

        $this->assertStringContainsString('/var/www/projects/easelogs', $contents);
        $this->assertStringContainsString('easelogs.local', $contents);
        $this->assertStringNotContainsString('easelog-pro', $contents);
        $this->assertStringNotContainsString('easelogs.pro', $contents);
        $this->assertStringContainsString(
            'resources/views/vendor/pagination/easelogs.blade.php',
            $contents,
        );
        $this->assertStringContainsString('--exclude="/vendor/"', $contents);
        $this->assertStringContainsString('rsync -av --delete', $contents);
        $this->assertStringContainsString('verify_community_deploy_boundary', $contents);
        $this->assertStringContainsString('assert_community_source_tree', $contents);
        $this->assertStringContainsString('artworks/bulk-update', $contents);
        $this->assertStringContainsString('redeploy_ensure_public_storage_symlink', $this->validationLib());
        $this->assertDoesNotMatchRegularExpression(
            '/--exclude="vendor"/',
            $contents,
        );
    }

    public function test_community_redeploy_uses_shared_post_deploy_validation(): void
    {
        $contents = $this->communityRedeployScript();

        $this->assertStringContainsString('scripts/lib/redeploy-validation.sh', $contents);
        $this->assertStringContainsString('redeploy_run_post_validation', $contents);
        $this->assertStringContainsString('redeploy_sync_scripts_with_lib', $contents);
        $this->assertStringContainsString('DEPLOYMENT REPORT', $this->validationLib());
    }

    public function test_validation_lib_covers_required_checks(): void
    {
        $contents = $this->validationLib();

        $this->assertStringContainsString('redeploy_verify_required_paths', $contents);
        $this->assertStringContainsString('redeploy_verify_storage_symlink', $contents);
        $this->assertStringContainsString('redeploy_verify_filesystem_permissions', $contents);
        $this->assertStringContainsString('redeploy_verify_laravel_database_rw', $contents);
        $this->assertStringContainsString('redeploy_verify_laravel_health', $contents);
        $this->assertStringContainsString('redeploy_verify_http_endpoints', $contents);
        $this->assertStringContainsString('redeploy_detect_web_server_group', $contents);
        $this->assertStringContainsString('database/database.sqlite', $contents);
        $this->assertStringContainsString('CREATE TEMP TABLE _deploy_write_check', $contents);
        $this->assertStringContainsString('php artisan about', $contents);
        $this->assertStringContainsString('php artisan migrate:status', $contents);
    }

    public function test_validation_lib_sync_includes_lib_directory(): void
    {
        $contents = $this->validationLib();

        $this->assertStringContainsString("--include='lib/'", $contents);
        $this->assertStringContainsString("--include='lib/**'", $contents);
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
