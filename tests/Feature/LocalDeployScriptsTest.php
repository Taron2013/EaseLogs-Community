<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocalDeployScriptsTest extends TestCase
{
    private function proRedeployScript(): string
    {
        return file_get_contents(base_path('scripts/redeploy-pro-local.sh'));
    }

    public function test_pro_redeploy_script_exists_with_core_paths(): void
    {
        $path = base_path('scripts/redeploy-pro-local.sh');

        $this->assertFileExists($path);
        $this->assertFileIsReadable($path);

        $contents = $this->proRedeployScript();

        $this->assertStringContainsString('/var/www/projects/easelog-pro', $contents);
        $this->assertStringContainsString('easelogs.pro', $contents);
        $this->assertStringContainsString('/var/www/projects/easelogs', $contents);
        $this->assertStringContainsString('RESET PRO', $contents);
        $this->assertStringContainsString('storage:link', $contents);
    }

    public function test_pro_script_blocks_community_as_write_target(): void
    {
        $contents = $this->proRedeployScript();

        $this->assertStringContainsString('assert_pro_write_target', $contents);
        $this->assertStringContainsString('Refusing to write to Community deployment', $contents);
        $this->assertStringNotContainsString('assert_not_community_path', $contents);
    }

    public function test_pro_script_allows_community_as_read_only_seed_source(): void
    {
        $contents = $this->proRedeployScript();

        $this->assertStringContainsString('assert_community_seed_source', $contents);
        $this->assertStringContainsString('read-only', $contents, 'Script should document read-only seed');
        $this->assertStringContainsString(
            'cp -a "$COMMUNITY_PROD/database/database.sqlite" "$PROD/database/database.sqlite"',
            $contents
        );
        $this->assertStringContainsString(
            'rsync -a "$COMMUNITY_PROD/storage/app/public/" "$PROD/storage/app/public/"',
            $contents
        );
    }

    public function test_pro_seed_copies_from_community_to_pro_not_reverse(): void
    {
        $contents = $this->proRedeployScript();

        $this->assertStringNotContainsString(
            'cp -a "$PROD/database/database.sqlite" "$COMMUNITY_PROD/database/database.sqlite"',
            $contents
        );
        $this->assertStringNotContainsString(
            'rsync -a "$PROD/storage/app/public/" "$COMMUNITY_PROD/storage/app/public/"',
            $contents
        );
    }

    public function test_pro_script_does_not_mutate_community_deploy_tree(): void
    {
        $contents = $this->proRedeployScript();

        $this->assertDoesNotMatchRegularExpression(
            '/sudo chown[^\n]*\$COMMUNITY_PROD/',
            $contents
        );
        $this->assertDoesNotMatchRegularExpression(
            '/sudo chmod[^\n]*\$COMMUNITY_PROD/',
            $contents
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\brm\b[^\n]*\$COMMUNITY_PROD/',
            $contents
        );
        $this->assertDoesNotMatchRegularExpression(
            '/cd\s+"\$COMMUNITY_PROD"/',
            $contents
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\(cd\s+"\$COMMUNITY_PROD"/',
            $contents
        );
        $this->assertDoesNotMatchRegularExpression(
            '/php artisan[^\n]*\$COMMUNITY_PROD/',
            $contents
        );
        $this->assertStringContainsString('cd "$PROD"', $contents);
    }

    public function test_community_redeploy_script_is_unchanged_for_pro_paths(): void
    {
        $contents = file_get_contents(base_path('scripts/redeploy-local.sh'));

        $this->assertStringContainsString('/var/www/projects/easelogs', $contents);
        $this->assertStringNotContainsString('easelog-pro', $contents);
    }

    public function test_nginx_example_targets_pro_deploy_root(): void
    {
        $contents = file_get_contents(base_path('deploy/nginx/easelogs.pro.conf.example'));

        $this->assertStringContainsString('server_name easelogs.pro', $contents);
        $this->assertStringContainsString('root /var/www/projects/easelog-pro/public', $contents);
        $this->assertStringNotContainsString('easelogs.pro.local', $contents);
    }
}
