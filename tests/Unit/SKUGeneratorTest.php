<?php

namespace Tests\Unit;

use App\Models\AppSetting;
use App\Models\Artwork;
use App\Models\User;
use App\Services\SKUGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SKUGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private SKUGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new SKUGenerator;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_date_token_replacement(): void
    {
        $result = $this->generator->parsePattern('ART-{YYYY}-{YY}-{MM}-{DD}-{####}', 7, [
            'reference_date' => '2026-05-23',
        ]);

        $this->assertSame('ART-2026-26-05-23-0007', $result);
    }

    public function test_inventory_sequence_padding(): void
    {
        $this->assertSame(
            'ART-2026-0001',
            $this->generator->parsePattern('ART-{YYYY}-{####}', 1, [
                'reference_date' => '2026-01-01',
            ])
        );

        $this->assertSame(
            'ART-2026-00001',
            $this->generator->parsePattern('ART-{YYYY}-{#####}', 1, [
                'reference_date' => '2026-01-01',
            ])
        );
    }

    public function test_sku_n_token_is_unpadded(): void
    {
        $result = $this->generator->parsePattern('{YYYY}#[N]', 128, [
            'finished_date' => '2026-08-01',
        ]);

        $this->assertSame('2026#128', $result);
    }

    public function test_sku_padded_token_variants(): void
    {
        $this->assertSame(
            '2026#01',
            $this->generator->parsePattern('{YYYY}#[NN]', 1, [
                'finished_date' => '2026-01-01',
            ])
        );

        $this->assertSame(
            '2026#128',
            $this->generator->parsePattern('{YYYY}#[NN]', 128, [
                'finished_date' => '2026-01-01',
            ])
        );

        $this->assertSame(
            '2026#012',
            $this->generator->parsePattern('{YYYY}#[NNN]', 12, [
                'finished_date' => '2026-01-01',
            ])
        );

        $this->assertSame(
            '2026#0128',
            $this->generator->parsePattern('{YYYY}#[NNNN]', 128, [
                'finished_date' => '2026-01-01',
            ])
        );
    }

    public function test_yearly_sku_reset(): void
    {
        Carbon::setTestNow('2027-01-15');

        $user = User::factory()->create();

        $this->createArtwork($user, [
            'inventory_code' => 'ART-2026-0001',
            'sku' => '2026#128',
            'finished_date' => '2026-12-31',
        ]);

        $sku = $this->generator->generateArtworkSKU([
            'finished_date' => '2027-01-15',
        ]);

        $this->assertSame('2027#1', $sku);
    }

    public function test_uniqueness_collision_retry(): void
    {
        Carbon::setTestNow('2026-03-01');

        $user = User::factory()->create();

        $this->createArtwork($user, [
            'inventory_code' => 'ART-2026-0099',
            'sku' => '2026#1',
            'finished_date' => '2026-03-01',
        ]);

        $sku = $this->generator->generateArtworkSKU([
            'finished_date' => '2026-03-01',
        ]);

        $this->assertSame('2026#2', $sku);
    }

    public function test_metadata_replacement_and_sanitization(): void
    {
        $result = $this->generator->parsePattern('{MEDIUM}-{CATEGORY}-{USER}-{####}', 4, [
            'medium' => 'oil on canvas',
            'category' => 'landscape works',
            'user_name' => 'Jane Doe',
            'reference_date' => '2026-01-01',
        ]);

        $this->assertSame('OIL-ON-CANVAS-LANDSCAPE-WORKS-JANE-DOE-0004', $result);
    }

    public function test_metadata_falls_back_when_blank(): void
    {
        $result = $this->generator->parsePattern('{MEDIUM}-{CATEGORY}-{USER}', 1, []);

        $this->assertSame('UNK-GEN-USER', $result);
    }

    public function test_default_yyyy_hash_n_behavior(): void
    {
        Carbon::setTestNow('2026-05-23');

        $user = User::factory()->create();

        $this->createArtwork($user, [
            'inventory_code' => 'ART-2026-0001',
            'sku' => '2026#1',
            'finished_date' => '2026-01-10',
        ]);

        $sku = $this->generator->generateArtworkSKU();

        $this->assertSame('2026#2', $sku);
    }

    public function test_generate_inventory_code_uses_default_pattern(): void
    {
        Carbon::setTestNow('2026-05-23');

        $code = $this->generator->generateInventoryCode([
            'reference_date' => '2026-05-23',
        ]);

        $this->assertSame('ART-2026-0001', $code);
    }

    public function test_generate_inventory_code_increments_per_year(): void
    {
        Carbon::setTestNow('2026-05-23');

        $user = User::factory()->create();

        $this->createArtwork($user, [
            'inventory_code' => 'ART-2026-0001',
            'sku' => '2026#1',
        ]);

        $code = $this->generator->generateInventoryCode([
            'reference_date' => '2026-05-23',
        ]);

        $this->assertSame('ART-2026-0002', $code);
    }

    public function test_sku_uses_finished_date_year_when_null_finished_date_uses_current_year(): void
    {
        Carbon::setTestNow('2026-06-01');

        $sku = $this->generator->generateArtworkSKU([
            'finished_date' => null,
        ]);

        $this->assertSame('2026#1', $sku);
    }

    public function test_custom_patterns_from_app_settings(): void
    {
        AppSetting::create([
            'setting_key' => 'default_sku_pattern',
            'setting_value' => '{YYYY}-SKU-[NN]',
        ]);

        Carbon::setTestNow('2026-01-01');

        $sku = $this->generator->generateArtworkSKU([
            'finished_date' => '2026-04-01',
        ]);

        $this->assertSame('2026-SKU-01', $sku);
    }

    public function test_starting_sequence_setting(): void
    {
        AppSetting::create([
            'setting_key' => 'starting_sequence',
            'setting_value' => '100',
        ]);

        Carbon::setTestNow('2026-01-01');

        $sequence = $this->generator->nextSequence('inventory_code', 'ART-{YYYY}-{####}', [
            'reference_date' => '2026-01-01',
        ]);

        $this->assertSame(100, $sequence);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createArtwork(User $user, array $overrides = []): Artwork
    {
        return Artwork::create(array_merge([
            'user_id' => $user->id,
            'inventory_code' => 'ART-TEST-'.uniqid(),
            'title' => 'Test Artwork',
            'sku' => null,
        ], $overrides));
    }
}
