<?php

namespace Tests\Unit;

use App\Support\AcceptedArtworkImage;
use App\Support\ArtworkImageQualifier;
use App\Support\ArtworkImageRejectedException;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ArtworkImageQualifierTest extends TestCase
{
    private ArtworkImageQualifier $qualifier;

    private string $scratchDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->qualifier = new ArtworkImageQualifier;
        $this->scratchDir = sys_get_temp_dir().'/elp-b1-qualifier-'.uniqid('', true);
        mkdir($this->scratchDir, 0700, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->scratchDir)) {
            foreach (glob($this->scratchDir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->scratchDir);
        }

        parent::tearDown();
    }

    public function test_accepts_valid_jpeg_png_and_webp(): void
    {
        foreach (['jpeg' => 'jpg', 'png' => 'png', 'webp' => 'webp'] as $format => $ext) {
            if ($format === 'webp' && ! function_exists('imagewebp')) {
                $this->markTestSkipped('Cannot write a WebP fixture in this runtime');
            }

            $path = $this->writeValidImage($format, $ext);
            $accepted = $this->qualifier->qualifyPath($path);

            $this->assertInstanceOf(AcceptedArtworkImage::class, $accepted);
            $this->assertSame($format, $accepted->format);
            $this->assertSame('image/'.$format, $accepted->mimeType);
            $this->assertGreaterThanOrEqual(1, $accepted->width);
            $this->assertGreaterThanOrEqual(1, $accepted->height);
            $this->assertGreaterThanOrEqual(1, $accepted->sizeBytes);
        }
    }

    public function test_rejects_jpg_extension_with_non_image_bytes(): void
    {
        $path = $this->scratchDir.'/spoof.jpg';
        file_put_contents($path, 'definitely not an image');

        try {
            $this->qualifier->qualifyPath($path);
            $this->fail('Expected ArtworkImageRejectedException');
        } catch (ArtworkImageRejectedException $exception) {
            $this->assertSame(ArtworkImageRejectedException::REASON_UNSUPPORTED_TYPE, $exception->reason);
        }
    }

    public function test_rejects_truncated_jpeg_when_type_and_dimensions_cannot_be_established(): void
    {
        $path = $this->scratchDir.'/truncated.jpg';
        file_put_contents($path, "\xFF\xD8\xFF");

        try {
            $this->qualifier->qualifyPath($path);
            $this->fail('Expected ArtworkImageRejectedException');
        } catch (ArtworkImageRejectedException $exception) {
            $this->assertContains($exception->reason, [
                ArtworkImageRejectedException::REASON_UNSUPPORTED_TYPE,
                ArtworkImageRejectedException::REASON_INVALID_DIMENSIONS,
            ]);
        }
    }

    public function test_rejects_missing_file(): void
    {
        try {
            $this->qualifier->qualifyPath($this->scratchDir.'/missing.jpg');
            $this->fail('Expected ArtworkImageRejectedException');
        } catch (ArtworkImageRejectedException $exception) {
            $this->assertSame(ArtworkImageRejectedException::REASON_UNREADABLE, $exception->reason);
        }
    }

    public function test_accepts_header_valid_image_that_gd_cannot_fully_decode(): void
    {
        $valid = $this->scratchDir.'/source.png';
        $image = imagecreatetruecolor(8, 6);
        imagepng($image, $valid);
        imagedestroy($image);

        $path = $this->scratchDir.'/header-only.png';
        $bytes = (string) file_get_contents($valid);
        file_put_contents($path, substr($bytes, 0, max(8, strlen($bytes) - 20)));

        $this->assertFalse(@imagecreatefrompng($path));

        $accepted = $this->qualifier->qualifyPath($path);

        $this->assertSame('png', $accepted->format);
        $this->assertSame(8, $accepted->width);
        $this->assertSame(6, $accepted->height);
        $this->assertStringNotContainsString(
            'imagecreatefrom',
            (string) file_get_contents((new \ReflectionClass(ArtworkImageQualifier::class))->getFileName()),
        );
    }

    public function test_rejects_unsupported_gif_type(): void
    {
        $path = $this->scratchDir.'/frame.gif';
        $image = imagecreatetruecolor(2, 2);
        imagegif($image, $path);
        imagedestroy($image);

        try {
            $this->qualifier->qualifyPath($path);
            $this->fail('Expected ArtworkImageRejectedException');
        } catch (ArtworkImageRejectedException $exception) {
            $this->assertSame(ArtworkImageRejectedException::REASON_UNSUPPORTED_TYPE, $exception->reason);
        }
    }

    public function test_rejects_file_over_photo_max_kb(): void
    {
        config(['easelogs.photo_max_kb' => 1]);

        $path = $this->scratchDir.'/padded.png';
        $image = imagecreatetruecolor(2, 2);
        imagepng($image, $path);
        imagedestroy($image);

        // Trailing padding keeps the PNG header identifiable without a large bitmap.
        file_put_contents($path, (string) file_get_contents($path).str_repeat("\0", 2048));

        $this->assertGreaterThan(1024, filesize($path));
        $info = getimagesize($path);
        $this->assertIsArray($info);
        $this->assertSame('image/png', $info['mime']);

        try {
            $this->qualifier->qualifyPath($path);
            $this->fail('Expected ArtworkImageRejectedException');
        } catch (ArtworkImageRejectedException $exception) {
            $this->assertSame(ArtworkImageRejectedException::REASON_OVERSIZE, $exception->reason);
        }
    }

    public function test_qualify_uploaded_file_accepts_valid_image(): void
    {
        $upload = UploadedFile::fake()->image('studio.jpg', 12, 8);
        $accepted = $this->qualifier->qualifyUploadedFile($upload);

        $this->assertSame('jpeg', $accepted->format);
        $this->assertSame(12, $accepted->width);
        $this->assertSame(8, $accepted->height);
    }

    /**
     * @param  'jpeg'|'png'|'webp'  $format
     */
    private function writeValidImage(string $format, string $ext): string
    {
        $path = $this->scratchDir.'/valid.'.$ext;
        $image = imagecreatetruecolor(4, 3);

        match ($format) {
            'jpeg' => imagejpeg($image, $path, 90),
            'png' => imagepng($image, $path),
            'webp' => imagewebp($image, $path),
        };

        imagedestroy($image);

        return $path;
    }
}
