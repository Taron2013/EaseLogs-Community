<?php

namespace Tests\Feature;

use App\Http\Requests\ArtworkPhotoBulkImportPreviewRequest;
use App\Models\Artwork;
use App\Models\ArtworkPhoto;
use App\Models\User;
use App\Support\ArtworkPhotoBulkImport\FilenameTitleParser;
use App\Support\ArtworkPhotoBulkImport\PhotoImportUploadLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ArtworkPhotoBulkImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_ce_artwork_schema_has_no_inventory_code_or_sku_columns(): void
    {
        $columns = Schema::getColumnListing('artworks');

        $this->assertNotContains('inventory_code', $columns);
        $this->assertNotContains('sku', $columns);
    }

    public function test_pro_only_inventory_code_column_is_rejected_in_mapping_csv(): void
    {
        $this->signIn();

        $zip = $this->makeImportZip([
            'mapping.csv' => "inventory_code,filename\nART-001,photos/one.jpg\n",
            'photos/one.jpg' => UploadedFile::fake()->image('one.jpg')->getContent(),
        ]);

        $this->post(route('artworks.photo-bulk-import.preview'), ['photo_zip' => $zip])
            ->assertRedirect(route('artworks.import-export'))
            ->assertSessionHas('error', 'Column "inventory_code" is a Pro-only field and is not supported in Community Edition.');
    }

    public function test_pro_only_sku_column_is_rejected_in_mapping_csv(): void
    {
        $this->signIn();

        $zip = $this->makeImportZip([
            'mapping.csv' => "sku,filename\nSKU-1,photos/one.jpg\n",
            'photos/one.jpg' => UploadedFile::fake()->image('one.jpg')->getContent(),
        ]);

        $this->post(route('artworks.photo-bulk-import.preview'), ['photo_zip' => $zip])
            ->assertRedirect(route('artworks.import-export'))
            ->assertSessionHas('error', 'Column "sku" is a Pro-only field and is not supported in Community Edition.');
    }

    public function test_successful_import_by_artwork_id(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create([
            'title' => 'Blue Study',
        ]);

        $zip = $this->makeImportZip([
            'mapping.csv' => "artwork_id,filename,set_as_current\n{$artwork->id},photos/blue.jpg,1\n",
            'photos/blue.jpg' => UploadedFile::fake()->image('blue.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);

        $this->get(route('artworks.photo-bulk-import.preview.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Selected to import:', false);

        $this->applyImport($token);

        $this->assertSame(1, ArtworkPhoto::query()->where('artwork_id', $artwork->id)->count());
        $this->assertTrue($artwork->fresh()->latestPhoto?->is_primary);
    }

    public function test_exact_title_match_appears_as_needs_confirmation(): void
    {
        $user = $this->signIn();
        Artwork::factory()->for($user)->create(['title' => 'Blue Heron']);

        $zip = $this->makeImportZip([
            'mapping.csv' => "title,filename\nBlue Heron,photos/heron.jpg\n",
            'photos/heron.jpg' => UploadedFile::fake()->image('heron.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);

        $this->get(route('artworks.photo-bulk-import.preview.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Needs review:', false)
            ->assertSee('Exact title match', false);
    }

    public function test_confirmed_title_match_imports_photo(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Winter Forest']);

        $zip = $this->makeImportZip([
            'mapping.csv' => "title,filename\nWinter Forest,photos/winter.jpg\n",
            'photos/winter.jpg' => UploadedFile::fake()->image('winter.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);
        $rowKey = $this->previewRowKey($user, $token, 'needs_confirmation');

        $this->post(route('artworks.photo-bulk-import.apply'), [
            'token' => $token,
            'confirm_rows' => [$rowKey],
        ])->assertRedirect(route('artworks.import-export'));

        $this->assertSame(1, $artwork->photos()->count());
    }

    public function test_unconfirmed_title_match_is_skipped_and_reported(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Studio Piece']);

        $zip = $this->makeImportZip([
            'mapping.csv' => "title,filename\nStudio Piece,photos/studio.jpg\n",
            'photos/studio.jpg' => UploadedFile::fake()->image('studio.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);

        $this->post(route('artworks.photo-bulk-import.apply'), [
            'token' => $token,
        ])->assertRedirect(route('artworks.import-export'))
            ->assertSessionHas('success', 'Imported 0 photo(s). Skipped 1 row(s). 1 unconfirmed title match(es) were not imported.');

        $this->assertSame(0, $artwork->photos()->count());
    }

    public function test_ambiguous_duplicate_title_is_not_imported(): void
    {
        $user = $this->signIn();
        Artwork::factory()->for($user)->create(['title' => 'Sunset']);
        Artwork::factory()->for($user)->create(['title' => 'Sunset']);

        $zip = $this->makeImportZip([
            'mapping.csv' => "title,filename\nSunset,photos/sunset.jpg\n",
            'photos/sunset.jpg' => UploadedFile::fake()->image('sunset.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);

        $this->get(route('artworks.photo-bulk-import.preview.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Ambiguous:', false)
            ->assertSee('Ambiguous title match', false);

        $this->applyImport($token);

        $this->assertSame(0, ArtworkPhoto::query()->count());
    }

    public function test_untitled_artwork_is_not_title_matched(): void
    {
        $user = $this->signIn();
        Artwork::factory()->for($user)->create(['title' => 'Untitled']);

        $zip = $this->makeImportZip([
            'mapping.csv' => "title,filename\nUntitled,photos/untitled.jpg\n",
            'photos/untitled.jpg' => UploadedFile::fake()->image('untitled.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);

        $this->get(route('artworks.photo-bulk-import.preview.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Untitled', false)
            ->assertDontSee('needs confirmation', false);
    }

    public function test_filename_title_parser_normalizes_common_patterns(): void
    {
        $parser = new FilenameTitleParser;

        $this->assertSame('Blue Heron', $parser->parse('photos/Blue-Heron-Final.jpg'));
        $this->assertSame('Winter Forest', $parser->parse('Winter_Forest_Edited.png'));
        $this->assertSame('The Red Barn', $parser->parse('The_Red_Barn_photo1.jpeg'));
        $this->assertSame('Aurora Borealis Waterfall', $parser->parse('Aurora Borealis Waterfall-2022#11.jpg'));
        $this->assertSame('A storm', $parser->parse('A storm-2023#101-11272023.jpg'));
        $this->assertSame('Abstract Key', $parser->parse('Abstract Key-2024#14.jpg'));
        $this->assertSame('Arizona Mountains', $parser->parse('Arizona Mountains-2022#13.jpg'));
        $this->assertSame('distortion', $parser->parse('distortion-2023#100-11232023.jpg'));
        $this->assertSame('deep purple sea', $parser->parse('deep purple sea-2024#44-12 x 16.jpg'));
        $this->assertSame('Dawn Time Twilight', $parser->parse('Dawn Time Twilight-2022#9B.jpg'));
        $this->assertSame('Dawn Time Twilight', $parser->parse('Dawn Time Twilight 2022 # 9B.jpg'));
        $this->assertNull($parser->parse('Untitled.jpg'));
    }

    public function test_alphanumeric_sku_suffix_filename_exact_matches_artwork_title(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Dawn Time Twilight']);

        $zip = $this->makeImportZip([
            'Dawn Time Twilight-2022#9B.jpg' => UploadedFile::fake()->image('dawn.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);
        $rowKey = $this->previewRowKey($user, $token, 'needs_confirmation', 'file-');

        $this->get(route('artworks.photo-bulk-import.preview.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Dawn Time Twilight', false)
            ->assertSee('photo-import-confirm-exact', false);

        $this->applyImport($token, [$rowKey]);

        $this->assertSame(1, $artwork->photos()->count());
    }

    public function test_exact_duplicate_of_existing_photo_is_flagged(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Existing Photo Artwork']);
        $contents = UploadedFile::fake()->image('duplicate.jpg')->getContent();
        $this->attachExistingPhoto($artwork, 'duplicate.jpg', $contents);

        $zip = $this->makeImportZip([
            'duplicate-copy.jpg' => $contents,
        ]);

        $token = $this->previewImport($zip);
        $row = $this->previewRow($user, $token, 'duplicate_existing_photo', 'file-');

        $this->assertSame('duplicate_existing_photo', $row['status']);
        $this->assertSame($artwork->id, $row['artwork_id']);
        $this->assertSame('Existing Photo Artwork', $row['artwork_title']);
    }

    public function test_exact_duplicate_is_not_selected_by_default(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Duplicate Default']);
        $contents = UploadedFile::fake()->image('dup-default.jpg')->getContent();
        $this->attachExistingPhoto($artwork, 'dup-default.jpg', $contents);

        $zip = $this->makeImportZip([
            'dup-default-copy.jpg' => $contents,
        ]);

        $token = $this->previewImport($zip);

        $preview = Cache::get('artwork_photo_bulk_preview:'.$user->id.':'.$token);
        $this->assertIsArray($preview);
        $this->assertSame(1, (int) ($preview['summary']['duplicate_existing_photo'] ?? 0));

        $this->get(route('artworks.photo-bulk-import.preview.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('id="photo-import-duplicates"', false)
            ->assertSee('Exact duplicates detected: 1', false)
            ->assertSee('<span id="count-duplicates">1</span>', false)
            ->assertSee('photo-import-card__filename', false)
            ->assertSee('data-status="duplicate_existing_photo"', false)
            ->assertSee('Exact duplicate of existing photo', false)
            ->assertSee('Will be skipped on apply', false)
            ->assertSee('<span id="count-selected">0</span>', false);

        $html = $this->get(route('artworks.photo-bulk-import.preview.show', ['token' => $token]))->getContent();
        preg_match('/id="photo-import-main-list"[^>]*>(.*?)<\/div>\s*<form/s', $html, $mainListMatch);
        $this->assertStringNotContainsString(
            'data-status="duplicate_existing_photo"',
            $mainListMatch[1] ?? $html,
        );
        preg_match('/id="photo-import-duplicates-list"[^>]*>(.*?)<\/div>\s*<\/details>/s', $html, $duplicateListMatch);
        $this->assertStringContainsString('dup-default-copy.jpg', $duplicateListMatch[1] ?? '');
        $this->assertStringNotContainsString('photo-import-confirm', $duplicateListMatch[1] ?? '');
    }

    public function test_apply_import_does_not_create_second_photo_for_exact_duplicate(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'No Second Copy']);
        $contents = UploadedFile::fake()->image('no-second.jpg')->getContent();
        $this->attachExistingPhoto($artwork, 'no-second.jpg', $contents);

        $zip = $this->makeImportZip([
            'no-second-copy.jpg' => $contents,
        ]);

        $this->applyImport($this->previewImport($zip));

        $this->assertSame(1, $artwork->photos()->count());
    }

    public function test_non_duplicate_image_still_imports_normally(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Unique Import']);
        $existingContents = UploadedFile::fake()->image('existing.jpg', 100, 100)->getContent();
        $uniqueContents = UploadedFile::fake()->image('unique.jpg', 200, 200)->getContent();
        $this->attachExistingPhoto($artwork, 'existing.jpg', $existingContents);

        $zip = $this->makeImportZip([
            'Unique Import.jpg' => $uniqueContents,
        ]);

        $token = $this->previewImport($zip);
        $rowKey = $this->previewRowKey($user, $token, 'needs_confirmation', 'file-');

        $this->applyImport($token, [$rowKey]);

        $this->assertSame(2, $artwork->photos()->count());
    }

    public function test_zip_only_import_matches_filenames_without_csv(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Arizona Mountains']);

        $zip = $this->makeImportZip([
            'Arizona Mountains-2022#13.jpg' => UploadedFile::fake()->image('Arizona Mountains-2022#13.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);

        $this->get(route('artworks.photo-bulk-import.preview.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Archive photos:', false)
            ->assertSee('Needs review:', false)
            ->assertSee('Filename match', false);

        $rowKey = $this->previewRowKey($user, $token, 'needs_confirmation', 'file-');

        $this->post(route('artworks.photo-bulk-import.apply'), [
            'token' => $token,
            'confirm_rows' => [$rowKey],
        ])->assertRedirect(route('artworks.import-export'));

        $this->assertSame(1, $artwork->photos()->count());
    }

    public function test_real_world_filename_matches_existing_artwork_title(): void
    {
        $user = $this->signIn();
        Artwork::factory()->for($user)->create(['title' => 'Aurora Borealis Waterfall']);

        $zip = $this->makeImportZip([
            'photos/Aurora Borealis Waterfall-2022#11.jpg' => UploadedFile::fake()->image('aurora.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);

        $this->get(route('artworks.photo-bulk-import.preview.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Aurora Borealis Waterfall', false)
            ->assertSee('Filename match', false)
            ->assertDontSee('No artwork found', false);
    }

    public function test_empty_mapping_csv_in_zip_falls_back_to_filename_matching(): void
    {
        $user = $this->signIn();
        Artwork::factory()->for($user)->create(['title' => 'Abstract Key']);

        $zip = $this->makeImportZip([
            'mapping.csv' => "filename,title\n",
            'Abstract Key-2024#14.jpg' => UploadedFile::fake()->image('abstract.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);

        $this->get(route('artworks.photo-bulk-import.preview.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Filename match', false);
    }

    public function test_orphan_zip_photo_uses_filename_title_candidate(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Blue Heron']);

        $zip = $this->makeImportZip([
            'mapping.csv' => "artwork_id,filename\n{$artwork->id},photos/mapped.jpg\n",
            'photos/mapped.jpg' => UploadedFile::fake()->image('mapped.jpg')->getContent(),
            'photos/Blue-Heron-Final.jpg' => UploadedFile::fake()->image('Blue-Heron-Final.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);

        $this->get(route('artworks.photo-bulk-import.preview.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Blue-Heron-Final.jpg', false)
            ->assertSee('Filename match', false);

        $rowKey = $this->previewRowKey($user, $token, 'needs_confirmation', 'file-');

        $this->post(route('artworks.photo-bulk-import.apply'), [
            'token' => $token,
            'confirm_rows' => [$rowKey],
        ])->assertRedirect(route('artworks.import-export'));

        $this->assertSame(2, $artwork->photos()->count());
    }

    public function test_mapping_csv_inside_zip_as_manifest(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create();

        $zip = $this->makeImportZip([
            'manifest.csv' => "artwork_id,filename\n{$artwork->id},photos/manifest.jpg\n",
            'photos/manifest.jpg' => UploadedFile::fake()->image('manifest.jpg')->getContent(),
        ]);

        $this->applyImport($this->previewImport($zip));

        $this->assertSame(1, $artwork->photos()->count());
    }

    public function test_separate_uploaded_mapping_csv(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create();

        $zip = $this->makeImportZip([
            'photos/separate.jpg' => UploadedFile::fake()->image('separate.jpg')->getContent(),
        ]);

        $mappingCsv = UploadedFile::fake()->createWithContent(
            'mapping.csv',
            "artwork_id,filename\n{$artwork->id},photos/separate.jpg\n",
        );

        $this->applyImport($this->previewImport($zip, $mappingCsv));

        $this->assertSame(1, $artwork->photos()->count());
    }

    public function test_preview_does_not_mutate_data(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create();

        $zip = $this->makeImportZip([
            'mapping.csv' => "artwork_id,filename\n{$artwork->id},photos/two.jpg\n",
            'photos/two.jpg' => UploadedFile::fake()->image('two.jpg')->getContent(),
        ]);

        $this->previewImport($zip);

        $this->assertSame(0, ArtworkPhoto::query()->count());
    }

    public function test_apply_creates_expected_photo_records_with_caption(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create();

        $zip = $this->makeImportZip([
            'mapping.csv' => "artwork_id,filename,caption\n{$artwork->id},photos/captioned.jpg,Studio view\n",
            'photos/captioned.jpg' => UploadedFile::fake()->image('captioned.jpg')->getContent(),
        ]);

        $this->applyImport($this->previewImport($zip));

        $photo = $artwork->photos()->first();
        $this->assertNotNull($photo);
        $this->assertSame('Studio view', $photo->caption);
        Storage::disk('public')->assertExists($photo->file_path);
    }

    public function test_missing_artwork_is_reported_in_preview(): void
    {
        $this->signIn();

        $zip = $this->makeImportZip([
            'mapping.csv' => "artwork_id,filename\n99999,photos/missing.jpg\n",
            'photos/missing.jpg' => UploadedFile::fake()->image('missing.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);

        $this->get(route('artworks.photo-bulk-import.preview.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('missing artwork', false);
    }

    public function test_missing_photo_is_reported_in_preview(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create();

        $zip = $this->makeImportZip([
            'mapping.csv' => "artwork_id,filename\n{$artwork->id},photos/not-in-zip.jpg\n",
        ]);

        $token = $this->previewImport($zip);

        $this->get(route('artworks.photo-bulk-import.preview.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('missing photo', false);
    }

    public function test_duplicate_photo_reference_is_reported(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create();

        $zip = $this->makeImportZip([
            'mapping.csv' => "artwork_id,filename\n{$artwork->id},photos/dup.jpg\n{$artwork->id},photos/dup.jpg\n",
            'photos/dup.jpg' => UploadedFile::fake()->image('dup.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);

        $this->get(route('artworks.photo-bulk-import.preview.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('duplicate', false);
    }

    public function test_invalid_file_is_reported_in_preview(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create();

        $zip = $this->makeImportZip([
            'mapping.csv' => "artwork_id,filename\n{$artwork->id},notes/readme.txt\n",
            'notes/readme.txt' => 'not an image',
        ]);

        $token = $this->previewImport($zip);

        $this->get(route('artworks.photo-bulk-import.preview.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Invalid file type', false);
    }

    public function test_completed_artwork_can_receive_imported_photo(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create([
            'completed_date' => '2026-01-15',
        ]);

        $zip = $this->makeImportZip([
            'mapping.csv' => "artwork_id,filename\n{$artwork->id},photos/final.jpg\n",
            'photos/final.jpg' => UploadedFile::fake()->image('final.jpg')->getContent(),
        ]);

        $this->applyImport($this->previewImport($zip));

        $this->assertSame(1, $artwork->photos()->count());
    }

    public function test_set_as_current_behavior(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create();
        ArtworkPhoto::query()->create([
            'artwork_id' => $artwork->id,
            'file_path' => 'artworks/'.$artwork->id.'/existing.jpg',
            'original_filename' => 'existing.jpg',
            'photo_type' => 'general',
            'is_primary' => true,
            'uploaded_at' => now(),
        ]);

        $zip = $this->makeImportZip([
            'mapping.csv' => "artwork_id,filename,set_as_current\n{$artwork->id},photos/new-current.jpg,1\n",
            'photos/new-current.jpg' => UploadedFile::fake()->image('new-current.jpg')->getContent(),
        ]);

        $this->applyImport($this->previewImport($zip));

        $photos = $artwork->photos()->orderBy('id')->get();
        $this->assertCount(2, $photos);
        $this->assertFalse($photos[0]->fresh()->is_primary);
        $this->assertTrue($photos[1]->fresh()->is_primary);
    }

    public function test_discard_cleans_cached_import_files(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create();

        $zip = $this->makeImportZip([
            'mapping.csv' => "artwork_id,filename\n{$artwork->id},photos/disc.jpg\n",
            'photos/disc.jpg' => UploadedFile::fake()->image('disc.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);

        $cacheKey = 'artwork_photo_bulk_preview:'.$user->id.':'.$token;
        $preview = Cache::get($cacheKey);
        $this->assertIsArray($preview);
        $extractPath = $preview['extract_path'];
        $this->assertDirectoryExists($extractPath);

        $this->get(route('artworks.photo-bulk-import.discard', ['token' => $token]))
            ->assertRedirect(route('artworks.import-export'));

        $this->assertNull(Cache::get($cacheKey));
        $this->assertDirectoryDoesNotExist($extractPath);
        $this->assertSame(0, ArtworkPhoto::query()->count());
    }

    public function test_photo_zip_validation_uses_four_gb_default(): void
    {
        $this->assertSame(4096, config('easelogs.photo_import_max_upload_mb'));
        $this->assertSame(4096, PhotoImportUploadLimit::maxMegabytes());

        $rules = (new ArtworkPhotoBulkImportPreviewRequest)->rules();

        $this->assertSame(
            ['required', 'file', 'mimes:zip', 'max:4194304'],
            $rules['photo_zip'],
        );
        $this->assertTrue(PhotoImportUploadLimit::appliesLaravelMaxRule());
    }

    public function test_zero_config_does_not_disable_ce_upload_limit(): void
    {
        config(['easelogs.photo_import_max_upload_mb' => 0]);

        $this->assertSame(4096, PhotoImportUploadLimit::maxMegabytes());

        $rules = (new ArtworkPhotoBulkImportPreviewRequest)->rules();

        $this->assertContains('max:4194304', $rules['photo_zip']);
    }

    public function test_photo_import_upload_health_endpoint_reports_limits(): void
    {
        $response = $this->getJson('/health/photo-import-upload');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'effective_max_mb',
                'app_max_mb',
                'php_post_max_mb',
                'php_upload_max_mb',
                'warnings',
                'notes',
            ])
            ->assertJsonPath('app_max_mb', 4096);
    }

    public function test_missing_photo_zip_redirects_to_import_export_with_validation_error(): void
    {
        $this->signIn();

        $this->from(route('artworks.import-export'))
            ->post(route('artworks.photo-bulk-import.preview'), [])
            ->assertRedirect(route('artworks.import-export'))
            ->assertSessionHasErrors(['photo_zip' => 'A photo ZIP file is required.']);
    }

    public function test_photo_bulk_import_partial_shows_field_error(): void
    {
        $errors = new \Illuminate\Support\ViewErrorBag;
        $errors->put('default', new \Illuminate\Support\MessageBag([
            'photo_zip' => ['The photo ZIP may not be larger than 4,096 MB (EaseLogs app limit).'],
        ]));

        $html = view('artworks._photo_bulk_import', [
            'easelogsDemo' => \App\Support\DemoMode::viewData(),
            'photoImportUpload' => \App\Support\ArtworkPhotoBulkImport\PhotoImportUploadEnvironment::viewData(),
            'errors' => $errors,
        ])->render();

        $this->assertStringContainsString('may not be larger than 4,096 MB', $html);
        $this->assertStringContainsString('field-error', $html);
    }

    public function test_oversized_photo_zip_is_rejected_by_app_limit(): void
    {
        $this->signIn();
        config(['easelogs.photo_import_max_upload_mb' => 1]);

        $path = tempnam(sys_get_temp_dir(), 'photo-import-');
        $zipPath = $path.'.zip';
        @unlink($path);

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('large.bin', str_repeat('x', 2 * 1024 * 1024));
        $zip->setCompressionName('large.bin', ZipArchive::CM_STORE);
        $zip->close();

        $zipFile = new UploadedFile($zipPath, 'photos.zip', 'application/zip', null, true);

        $response = $this->from(route('artworks.import-export'))
            ->post(route('artworks.photo-bulk-import.preview'), [
                'photo_zip' => $zipFile,
            ]);

        $response->assertRedirect(route('artworks.import-export'))
            ->assertSessionHasErrors(['photo_zip' => 'The photo ZIP may not be larger than 1 MB (EaseLogs app limit).']);
    }

    public function test_partial_title_match_suggested_when_no_exact_title_exists(): void
    {
        $user = $this->signIn();
        Artwork::factory()->for($user)->create(['title' => 'Hearts in Green Abstract']);
        Artwork::factory()->for($user)->create(['title' => 'Abstract Cubism Green Hearts']);

        $zip = $this->makeImportZip([
            '4 Hearts in Green.jpg' => UploadedFile::fake()->image('4 Hearts in Green.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);
        $row = $this->previewRow($user, $token, 'partial_title_match');

        $this->assertSame('partial_title_match', $row['status']);
        $this->assertSame('partial_title_match', $row['match_method']);
        $this->assertSame('Hearts in Green Abstract', $row['artwork_title']);
        $this->assertSame('4 Hearts in Green', $row['title_candidate']);

        $this->get(route('artworks.photo-bulk-import.preview.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('data-status="partial_title_match"', false)
            ->assertSee('Partial title match — review before importing.', false);
    }

    public function test_partial_title_match_is_not_checked_by_default(): void
    {
        $user = $this->signIn();
        Artwork::factory()->for($user)->create(['title' => 'Hearts in Green Abstract']);

        $zip = $this->makeImportZip([
            '4 Hearts in Green.jpg' => UploadedFile::fake()->image('4 Hearts in Green.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);

        $html = $this->get(route('artworks.photo-bulk-import.preview.show', ['token' => $token]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('photo-import-confirm-partial', $html);
        $this->assertMatchesRegularExpression(
            '/class="photo-import-confirm photo-import-confirm-partial"[^>]*value="[^"]+"[^>]*>/',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/class="photo-import-confirm photo-import-confirm-partial"[^>]*checked/',
            $html,
        );
    }

    public function test_confirmed_partial_title_match_imports_successfully(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Hearts in Green Abstract']);

        $zip = $this->makeImportZip([
            '4 Hearts in Green.jpg' => UploadedFile::fake()->image('4 Hearts in Green.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);
        $rowKey = $this->previewRowKey($user, $token, 'partial_title_match', 'file-');

        $this->applyImport($token, [$rowKey]);

        $this->assertSame(1, $artwork->photos()->count());
    }

    public function test_unchecked_partial_title_match_is_skipped_and_reported(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Hearts in Green Abstract']);

        $zip = $this->makeImportZip([
            '4 Hearts in Green.jpg' => UploadedFile::fake()->image('4 Hearts in Green.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);

        $this->post(route('artworks.photo-bulk-import.apply'), [
            'token' => $token,
        ])->assertRedirect(route('artworks.import-export'))
            ->assertSessionHas('success', 'Imported 0 photo(s). Skipped 1 row(s). 1 unconfirmed title match(es) were not imported.');

        $this->assertSame(0, $artwork->photos()->count());
    }

    public function test_untitled_is_never_partial_matched(): void
    {
        $user = $this->signIn();
        Artwork::factory()->for($user)->create(['title' => 'Untitled']);
        Artwork::factory()->for($user)->create(['title' => 'Green Landscape']);

        $zip = $this->makeImportZip([
            'Untitled.jpg' => UploadedFile::fake()->image('Untitled.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);
        $preview = Cache::get('artwork_photo_bulk_preview:'.$user->id.':'.$token);
        $this->assertIsArray($preview);
        $this->assertNull(collect($preview['rows'])->firstWhere('status', 'partial_title_match'));
    }

    public function test_near_tie_partial_matches_are_marked_ambiguous(): void
    {
        $user = $this->signIn();
        Artwork::factory()->for($user)->create(['title' => 'Hearts in Green']);
        Artwork::factory()->for($user)->create(['title' => 'Hearts on Green']);

        $zip = $this->makeImportZip([
            '4 Hearts in Green.jpg' => UploadedFile::fake()->image('4 Hearts in Green.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);
        $row = $this->previewRow($user, $token, 'ambiguous_match');

        $this->assertSame('ambiguous_match', $row['status']);
        $this->assertSame('partial_title_match', $row['match_method']);
    }

    public function test_low_confidence_partial_match_is_unmatched(): void
    {
        $user = $this->signIn();
        Artwork::factory()->for($user)->create(['title' => 'Mountain Lake Sunset']);

        $zip = $this->makeImportZip([
            '4 Hearts in Green.jpg' => UploadedFile::fake()->image('4 Hearts in Green.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);
        $row = $this->previewRow($user, $token, 'missing_artwork');

        $this->assertSame('missing_artwork', $row['status']);
        $this->assertNull(collect(Cache::get('artwork_photo_bulk_preview:'.$user->id.':'.$token)['rows'] ?? [])
            ->firstWhere('status', 'partial_title_match'));
    }

    public function test_best_partial_match_is_chosen_when_clearly_better(): void
    {
        $user = $this->signIn();
        $best = Artwork::factory()->for($user)->create(['title' => 'Hearts in Green Abstract']);
        Artwork::factory()->for($user)->create(['title' => 'Abstract Cubism Green Hearts']);

        $zip = $this->makeImportZip([
            '4 Hearts in Green.jpg' => UploadedFile::fake()->image('4 Hearts in Green.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);

        $preview = Cache::get('artwork_photo_bulk_preview:'.$user->id.':'.$token);
        $this->assertIsArray($preview);
        $row = collect($preview['rows'])->firstWhere('status', 'partial_title_match');
        $this->assertIsArray($row);
        $this->assertSame($best->id, $row['artwork_id']);
        $this->assertSame('Hearts in Green Abstract', $row['artwork_title']);
    }

    public function test_exact_title_match_remains_checked_by_default(): void
    {
        $user = $this->signIn();
        Artwork::factory()->for($user)->create(['title' => 'Blue Heron']);

        $zip = $this->makeImportZip([
            'mapping.csv' => "title,filename\nBlue Heron,photos/heron.jpg\n",
            'photos/heron.jpg' => UploadedFile::fake()->image('heron.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);

        $html = $this->get(route('artworks.photo-bulk-import.preview.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Exact title match', false)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/class="photo-import-confirm photo-import-confirm-exact"[^>]*checked/',
            $html,
        );
    }

    public function test_a_storm_suggests_long_artwork_title_as_partial_match(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create([
            'title' => 'A Storm - Teal Burnt Umber brown Acrylic Pour Painting',
        ]);

        $zip = $this->makeImportZip([
            'A storm-2023#101-11272023.jpg' => UploadedFile::fake()->image('A storm-2023#101-11272023.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);
        $row = $this->previewRow($user, $token, 'partial_title_match', 'file-');

        $this->assertSame('partial_title_match', $row['status']);
        $this->assertSame('partial_title_match', $row['match_method']);
        $this->assertSame($artwork->id, $row['artwork_id']);
        $this->assertSame('A storm', $row['title_candidate']);

        $html = $this->get(route('artworks.photo-bulk-import.preview.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Ambiguous:', false)
            ->assertSee('Partial title match — review before importing.', false)
            ->assertDontSee('ambigious', false)
            ->getContent();

        $this->assertStringContainsString('photo-import-confirm-partial', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/class="photo-import-confirm photo-import-confirm-partial"[^>]*checked/',
            $html,
        );
    }

    public function test_distortion_filename_suggests_distortion_artwork_as_partial_match(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create([
            'title' => 'Distortion - Blue Teal Silver Acrylic Pour Painting',
        ]);

        $zip = $this->makeImportZip([
            'distortion-2023#100-11232023.jpg' => UploadedFile::fake()->image('distortion-2023#100-11232023.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);
        $row = $this->previewRow($user, $token, 'partial_title_match', 'file-');

        $this->assertSame('partial_title_match', $row['status']);
        $this->assertSame($artwork->id, $row['artwork_id']);
        $this->assertSame('distortion', $row['title_candidate']);
    }

    public function test_deep_purple_sea_filename_suggests_artwork_as_partial_match(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create([
            'title' => 'Deep Purple Sea - Acrylic Pour Purple Black Florence Yellow',
        ]);

        $zip = $this->makeImportZip([
            'deep purple sea-2024#44-12 x 16.jpg' => UploadedFile::fake()->image('deep purple sea-2024#44-12 x 16.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);
        $row = $this->previewRow($user, $token, 'partial_title_match', 'file-');

        $this->assertSame('partial_title_match', $row['status']);
        $this->assertSame($artwork->id, $row['artwork_id']);
        $this->assertSame('deep purple sea', $row['title_candidate']);
    }

    public function test_multiple_prefix_partial_matches_are_ambiguous(): void
    {
        $user = $this->signIn();
        Artwork::factory()->for($user)->create(['title' => 'A Storm - Teal Burnt Umber']);
        Artwork::factory()->for($user)->create(['title' => 'A Storm - Blue Horizon']);

        $zip = $this->makeImportZip([
            'A storm-2023#101-11272023.jpg' => UploadedFile::fake()->image('A storm-2023#101-11272023.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);
        $row = $this->previewRow($user, $token, 'ambiguous_match', 'file-');

        $this->assertSame('ambiguous_match', $row['status']);
        $this->assertSame('partial_title_match', $row['match_method']);
    }

    public function test_generic_filename_img_can_be_manually_resolved(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Mystery Piece']);

        $zip = $this->makeImportZip([
            'IMG_019283.jpg' => UploadedFile::fake()->image('IMG_019283.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);
        $rowKey = $this->previewRowKey($user, $token, 'missing_artwork', 'file-');

        $this->resolveImportRow($token, $rowKey, $artwork->id);

        $this->get(route('artworks.photo-bulk-import.preview.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Change match', false);

        $row = $this->previewRow($user, $token, 'manually_resolved', 'file-');
        $this->assertSame('manual_resolution', $row['match_method']);
        $this->assertSame($artwork->id, $row['artwork_id']);
        $this->assertSame('Mystery Piece', $row['artwork_title']);

        $this->applyImport($token, [$rowKey]);

        $this->assertSame(1, $artwork->photos()->count());
    }

    public function test_unmatched_archive_photo_can_be_manually_resolved_and_imported(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Photo One Target']);

        $zip = $this->makeImportZip([
            'photo1.jpg' => UploadedFile::fake()->image('photo1.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);
        $rowKey = $this->previewRowKey($user, $token, 'unmatched', 'file-');

        $this->resolveImportRow($token, $rowKey, $artwork->id);
        $this->applyImport($token, [$rowKey]);

        $this->assertSame(1, $artwork->photos()->count());
    }

    public function test_ambiguous_row_can_be_manually_resolved_and_imported(): void
    {
        $user = $this->signIn();
        $first = Artwork::factory()->for($user)->create(['title' => 'Sunset']);
        Artwork::factory()->for($user)->create(['title' => 'Sunset']);

        $zip = $this->makeImportZip([
            'mapping.csv' => "title,filename\nSunset,photos/sunset.jpg\n",
            'photos/sunset.jpg' => UploadedFile::fake()->image('sunset.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);
        $rowKey = $this->previewRowKey($user, $token, 'ambiguous_match');

        $this->resolveImportRow($token, $rowKey, $first->id);
        $this->applyImport($token, [$rowKey]);

        $this->assertSame(1, $first->photos()->count());
        $this->assertSame(0, ArtworkPhoto::query()->where('artwork_id', '!=', $first->id)->count());
    }

    public function test_partial_title_match_can_be_changed_to_different_artwork(): void
    {
        $user = $this->signIn();
        Artwork::factory()->for($user)->create(['title' => 'Distortion Study']);
        $target = Artwork::factory()->for($user)->create(['title' => 'Different Piece']);

        $zip = $this->makeImportZip([
            'distortion.jpg' => UploadedFile::fake()->image('distortion.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);
        $rowKey = $this->previewRowKey($user, $token, 'partial_title_match', 'file-');

        $this->resolveImportRow($token, $rowKey, $target->id);

        $row = $this->previewRow($user, $token, 'manually_resolved', 'file-');
        $this->assertSame($target->id, $row['artwork_id']);

        $this->applyImport($token, [$rowKey]);

        $this->assertSame(1, $target->photos()->count());
        $this->assertSame(0, ArtworkPhoto::query()->where('artwork_id', '!=', $target->id)->count());
    }

    public function test_manual_resolution_can_be_undone_before_apply(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Undo Target']);

        $zip = $this->makeImportZip([
            'photo1.jpg' => UploadedFile::fake()->image('photo1.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);
        $rowKey = $this->previewRowKey($user, $token, 'unmatched', 'file-');

        $this->resolveImportRow($token, $rowKey, $artwork->id);
        $this->undoImportResolve($token, $rowKey);

        $this->previewRow($user, $token, 'unmatched', 'file-');
        $this->applyImport($token);

        $this->assertSame(0, $artwork->photos()->count());
    }

    public function test_unchecked_manually_resolved_row_is_skipped_on_apply(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Skipped Manual']);

        $zip = $this->makeImportZip([
            'scan0004.jpg' => UploadedFile::fake()->image('scan0004.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);
        $rowKey = $this->previewRowKey($user, $token, 'missing_artwork', 'file-');

        $this->resolveImportRow($token, $rowKey, $artwork->id);

        $this->post(route('artworks.photo-bulk-import.apply'), [
            'token' => $token,
        ])->assertRedirect(route('artworks.import-export'))
            ->assertSessionHas('success', 'Imported 0 photo(s). Skipped 1 row(s). 1 unconfirmed title match(es) were not imported.');

        $this->assertSame(0, $artwork->photos()->count());
    }

    public function test_generic_filename_can_be_manually_resolved(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Generic Assignee']);

        $zip = $this->makeImportZip([
            '019283.jpg' => UploadedFile::fake()->image('019283.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);
        $rowKey = $this->previewRowKey($user, $token, 'missing_artwork', 'file-');

        $this->get(route('artworks.photo-bulk-import.preview.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Resolve match', false);

        $this->resolveImportRow($token, $rowKey, $artwork->id);
        $this->applyImport($token, [$rowKey]);

        $this->assertSame(1, $artwork->photos()->count());
    }

    public function test_manual_resolver_searches_by_title(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Unique Manual Search Title']);

        $zip = $this->makeImportZip([
            'IMG_777.jpg' => UploadedFile::fake()->image('IMG_777.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);

        $response = $this->getJson(route('artworks.photo-bulk-import.preview.search', [
            'token' => $token,
            'q' => 'Unique Manual Search',
        ]));

        $response->assertOk();
        $ids = collect($response->json('artworks'))->pluck('id')->all();
        $this->assertContains($artwork->id, $ids);
    }

    public function test_manual_resolver_suggests_partial_title_candidates_for_color_mismatch(): void
    {
        $user = $this->signIn();

        $gold = Artwork::factory()->for($user)->create(['title' => 'Final Trumpet Gold']);
        $green = Artwork::factory()->for($user)->create(['title' => 'Final Trumpet Green']);
        $pink = Artwork::factory()->for($user)->create(['title' => 'Final Trumpet Pink']);
        $teal = Artwork::factory()->for($user)->create(['title' => 'Final Trumpet Teal']);
        Artwork::factory()->for($user)->create(['title' => 'Sunset Beach']);

        $zip = $this->makeImportZip([
            'IMG_001.jpg' => UploadedFile::fake()->image('IMG_001.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);

        $response = $this->getJson(route('artworks.photo-bulk-import.preview.search', [
            'token' => $token,
            'q' => 'Final Trumpet Blue',
        ]));

        $response->assertOk();
        $ids = collect($response->json('artworks'))->pluck('id')->all();

        $this->assertContains($gold->id, $ids);
        $this->assertContains($green->id, $ids);
        $this->assertContains($pink->id, $ids);
        $this->assertContains($teal->id, $ids);
        $this->assertCount(4, $ids);
    }

    public function test_unmatched_color_mismatch_filename_can_be_manually_resolved_without_editing_search(): void
    {
        $user = $this->signIn();
        $gold = Artwork::factory()->for($user)->create(['title' => 'Final Trumpet Gold']);
        Artwork::factory()->for($user)->create(['title' => 'Final Trumpet Green']);
        Artwork::factory()->for($user)->create(['title' => 'Final Trumpet Pink']);
        Artwork::factory()->for($user)->create(['title' => 'Final Trumpet Teal']);

        $zip = $this->makeImportZip([
            'Final Trumpet Blue-2024#51.jpg' => UploadedFile::fake()->image('trumpet-blue.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);
        $rowKey = $this->previewRowKey($user, $token, 'ambiguous_match', 'file-');

        $this->getJson(route('artworks.photo-bulk-import.preview.search', [
            'token' => $token,
            'q' => 'Final Trumpet Blue',
        ]))
            ->assertOk()
            ->assertJsonCount(4, 'artworks');

        $this->resolveImportRow($token, $rowKey, $gold->id);
        $this->applyImport($token, [$rowKey]);

        $this->assertSame(1, $gold->photos()->count());
    }

    private function previewImport(UploadedFile $zip, ?UploadedFile $mappingCsv = null): string
    {
        $payload = ['photo_zip' => $zip];

        if ($mappingCsv !== null) {
            $payload['mapping_csv'] = $mappingCsv;
        }

        $response = $this->post(route('artworks.photo-bulk-import.preview'), $payload);
        $response->assertRedirect();

        return basename(parse_url($response->headers->get('Location'), PHP_URL_PATH) ?: '');
    }

    private function applyImport(string $token, array $confirmRows = []): void
    {
        $payload = ['token' => $token];

        if ($confirmRows !== []) {
            $payload['confirm_rows'] = $confirmRows;
        }

        $this->post(route('artworks.photo-bulk-import.apply'), $payload)
            ->assertRedirect(route('artworks.import-export'));
    }

    public function test_preview_shows_thumbnail_and_bulk_controls(): void
    {
        $user = $this->signIn();
        $artwork = Artwork::factory()->for($user)->create(['title' => 'Preview Thumb']);

        $zip = $this->makeImportZip([
            'Preview Thumb-2024#1.jpg' => UploadedFile::fake()->image('preview.jpg')->getContent(),
        ]);

        $token = $this->previewImport($zip);

        $this->get(route('artworks.photo-bulk-import.preview.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Archive photos:', false)
            ->assertSee('Selected to import:', false)
            ->assertSee('Check all exact title matches', false)
            ->assertSee('photo-import-confirm', false)
            ->assertSee('photo-import-main-list', false)
            ->assertSee('photo-import-card__filename', false)
            ->assertSee('Preview Thumb-2024#1.jpg', false);

        $rowKey = $this->previewRowKey($user, $token, 'needs_confirmation', 'file-');

        $this->get(route('artworks.photo-bulk-import.preview.thumb', ['token' => $token, 'rowKey' => $rowKey]))
            ->assertOk();
    }

    private function previewRow(User $user, string $token, string $status, ?string $prefix = null): array
    {
        $preview = Cache::get('artwork_photo_bulk_preview:'.$user->id.':'.$token);
        $this->assertIsArray($preview);

        foreach ($preview['rows'] as $row) {
            if (($row['status'] ?? null) !== $status) {
                continue;
            }

            if ($prefix !== null && ! str_starts_with((string) ($row['row_key'] ?? ''), $prefix)) {
                continue;
            }

            return $row;
        }

        $this->fail('Expected preview row with status '.$status);
    }

    private function previewRowKey(User $user, string $token, string $status, ?string $prefix = 'line-'): string
    {
        $preview = Cache::get('artwork_photo_bulk_preview:'.$user->id.':'.$token);
        $this->assertIsArray($preview);

        foreach ($preview['rows'] as $row) {
            if (($row['status'] ?? null) !== $status) {
                continue;
            }

            if ($prefix !== null && ! str_starts_with((string) ($row['row_key'] ?? ''), $prefix)) {
                continue;
            }

            return (string) $row['row_key'];
        }

        $this->fail('Expected preview row with status '.$status);
    }

    private function resolveImportRow(string $token, string $rowKey, int $artworkId): void
    {
        $this->postJson(route('artworks.photo-bulk-import.preview.resolve', ['token' => $token]), [
            'row_key' => $rowKey,
            'artwork_id' => $artworkId,
        ])->assertOk()
            ->assertJsonPath('row.status', 'manually_resolved');
    }

    private function undoImportResolve(string $token, string $rowKey): void
    {
        $this->postJson(route('artworks.photo-bulk-import.preview.undo-resolve', ['token' => $token]), [
            'row_key' => $rowKey,
        ])->assertOk();
    }

    private function attachExistingPhoto(Artwork $artwork, string $filename, string $contents): ArtworkPhoto
    {
        $path = 'artworks/'.$artwork->id.'/'.$filename;
        Storage::disk('public')->put($path, $contents);

        return ArtworkPhoto::create([
            'artwork_id' => $artwork->id,
            'file_path' => $path,
            'original_filename' => $filename,
            'mime_type' => 'image/jpeg',
            'photo_type' => 'general',
            'is_primary' => true,
            'uploaded_at' => now(),
        ]);
    }

    /**
     * @param  array<string, string>  $files
     */
    private function makeImportZip(array $files): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'photo-import-');
        $zipPath = $path.'.zip';
        @unlink($path);

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        return new UploadedFile($zipPath, 'photos.zip', 'application/zip', null, true);
    }
}
