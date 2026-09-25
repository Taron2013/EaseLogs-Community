<?php

namespace Tests\Unit;

use App\Support\ArtworkPhotoBulkImport\PhotoImportTempContainment;
use Tests\TestCase;

class PhotoImportTempContainmentTest extends TestCase
{
    private string $runId;

    private string $root;

    private string $siblingDirectory;

    private string $outsideDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runId = 'b12-'.bin2hex(random_bytes(8));
        $this->root = PhotoImportTempContainment::root();

        if (! is_dir($this->root) && ! mkdir($this->root, 0700, true) && ! is_dir($this->root)) {
            $this->fail('Could not create the photo-import temporary root for this test.');
        }

        $tempParent = dirname($this->root);
        $this->siblingDirectory = $tempParent.DIRECTORY_SEPARATOR.'photo-imports-other'.DIRECTORY_SEPARATOR.$this->runId;
        $this->outsideDirectory = $tempParent.DIRECTORY_SEPARATOR.'b12-outside-'.$this->runId;

        $this->makeDirectory($this->siblingDirectory);
        $this->makeDirectory($this->outsideDirectory);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root) && ! is_link($this->root)) {
            foreach (scandir($this->root) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..' || ! str_starts_with($entry, $this->runId)) {
                    continue;
                }

                $this->removeOwnedFixture($this->root.DIRECTORY_SEPARATOR.$entry);
            }
        } elseif (! is_dir($this->root)) {
            mkdir($this->root, 0700, true);
        }

        $this->removeOwnedFixture($this->siblingDirectory);
        $this->removeOwnedFixture($this->outsideDirectory);

        $siblingParent = dirname($this->siblingDirectory);

        if (is_dir($siblingParent) && ! is_link($siblingParent) && $this->directoryIsEmpty($siblingParent)) {
            rmdir($siblingParent);
        }

        parent::tearDown();
    }

    public function test_only_a_direct_child_extraction_directory_is_allowed(): void
    {
        $allowed = $this->directChild('allowed');
        $nested = $allowed.DIRECTORY_SEPARATOR.'nested';
        $this->makeDirectory($nested);

        $resolvedAllowed = realpath($allowed);
        $this->assertIsString($resolvedAllowed);

        $this->assertTrue(PhotoImportTempContainment::isAllowedExtractionDirectory($allowed));
        $this->assertTrue(PhotoImportTempContainment::isAllowedExtractionDirectory($resolvedAllowed));
        $this->assertFalse(PhotoImportTempContainment::isAllowedExtractionDirectory($this->root));
        $this->assertFalse(PhotoImportTempContainment::isAllowedExtractionDirectory($nested));
        $this->assertFalse(PhotoImportTempContainment::isAllowedExtractionDirectory($this->siblingDirectory));

        $traversal = $this->traversalToOutside($allowed);
        $this->assertSame(realpath($this->outsideDirectory), realpath($traversal));
        $this->assertFalse(PhotoImportTempContainment::isAllowedExtractionDirectory($traversal));
    }

    public function test_only_a_regular_file_inside_an_extraction_workspace_is_contained(): void
    {
        $workspace = $this->directChild('contained');
        $contained = $workspace.DIRECTORY_SEPARATOR.'nested'.DIRECTORY_SEPARATOR.'photo.jpg';
        $this->makeDirectory(dirname($contained));
        file_put_contents($contained, 'contained');

        $outsideFile = $this->outsideDirectory.DIRECTORY_SEPARATOR.'outside.jpg';
        $siblingFile = $this->siblingDirectory.DIRECTORY_SEPARATOR.'sibling.jpg';
        file_put_contents($outsideFile, 'outside');
        file_put_contents($siblingFile, 'sibling');

        $this->assertTrue(PhotoImportTempContainment::isContainedFile($contained));
        $this->assertFalse(PhotoImportTempContainment::isContainedFile($outsideFile));
        $this->assertFalse(PhotoImportTempContainment::isContainedFile($siblingFile));

        $traversal = $this->traversalToOutside($workspace).DIRECTORY_SEPARATOR.'outside.jpg';
        $this->assertSame(realpath($outsideFile), realpath($traversal));
        $this->assertFalse(PhotoImportTempContainment::isContainedFile($traversal));
    }

    public function test_destructive_calls_remove_allowed_targets_and_leave_protected_objects(): void
    {
        $allowed = $this->directChild('deletable');
        file_put_contents($allowed.DIRECTORY_SEPARATOR.'photo.jpg', 'delete-me');
        $nested = $this->directChild('nested-parent').DIRECTORY_SEPARATOR.'nested';
        $this->makeDirectory($nested);
        file_put_contents($nested.DIRECTORY_SEPARATOR.'keep.txt', 'nested');

        $rootSentinel = $this->root.DIRECTORY_SEPARATOR.$this->runId.'-root-sentinel';
        file_put_contents($rootSentinel, 'root');
        $siblingMarker = $this->siblingDirectory.DIRECTORY_SEPARATOR.'keep.txt';
        $outsideMarker = $this->outsideDirectory.DIRECTORY_SEPARATOR.'keep.txt';
        file_put_contents($siblingMarker, 'sibling');
        file_put_contents($outsideMarker, 'outside');

        $this->assertTrue(PhotoImportTempContainment::deleteExtractionDirectory($allowed));
        $this->assertDirectoryDoesNotExist($allowed);
        $this->assertDirectoryExists($this->root);
        $this->assertFileExists($rootSentinel);

        $this->assertFalse(PhotoImportTempContainment::deleteExtractionDirectory($this->root));
        $this->assertDirectoryExists($this->root);
        $this->assertFileExists($rootSentinel);

        $this->assertFalse(PhotoImportTempContainment::deleteExtractionDirectory($nested));
        $this->assertDirectoryExists($nested);
        $this->assertFileExists($nested.DIRECTORY_SEPARATOR.'keep.txt');

        $this->assertFalse(PhotoImportTempContainment::deleteExtractionDirectory($this->siblingDirectory));
        $this->assertDirectoryExists($this->siblingDirectory);
        $this->assertFileExists($siblingMarker);

        $this->assertFalse(PhotoImportTempContainment::deleteExtractionDirectory($this->outsideDirectory));
        $this->assertDirectoryExists($this->outsideDirectory);
        $this->assertFileExists($outsideMarker);

        $traversal = $this->traversalToOutside($this->directChild('trav'));
        $this->assertFalse(PhotoImportTempContainment::deleteExtractionDirectory($traversal));
        $this->assertDirectoryExists($this->outsideDirectory);
        $this->assertFileExists($outsideMarker);

        $contained = $this->directChild('unlink').DIRECTORY_SEPARATOR.'photo.jpg';
        file_put_contents($contained, 'unlink-me');
        $this->assertTrue(PhotoImportTempContainment::unlinkContainedFile($contained));
        $this->assertFileDoesNotExist($contained);

        $this->assertFalse(PhotoImportTempContainment::unlinkContainedFile($outsideMarker));
        $this->assertFileExists($outsideMarker);
        $this->assertSame('outside', file_get_contents($outsideMarker));
    }

    public function test_symlink_candidates_are_refused_without_destroying_their_targets(): void
    {
        $outsideFile = $this->outsideDirectory.DIRECTORY_SEPARATOR.'target.jpg';
        file_put_contents($outsideFile, 'protected-target');

        $directoryLink = $this->root.DIRECTORY_SEPARATOR.$this->runId.'-dir-link';
        $this->requireSymlink($this->outsideDirectory, $directoryLink);

        $this->assertFalse(PhotoImportTempContainment::isAllowedExtractionDirectory($directoryLink));
        $this->assertFalse(PhotoImportTempContainment::deleteExtractionDirectory($directoryLink));
        $this->assertTrue(is_link($directoryLink));
        $this->assertDirectoryExists($this->outsideDirectory);
        $this->assertFileExists($outsideFile);

        $workspace = $this->directChild('symlink-file');
        $fileLink = $workspace.DIRECTORY_SEPARATOR.'linked.jpg';
        $this->requireSymlink($outsideFile, $fileLink);

        $this->assertFalse(PhotoImportTempContainment::isContainedFile($fileLink));
        $this->assertFalse(PhotoImportTempContainment::unlinkContainedFile($fileLink));
        $this->assertTrue(is_link($fileLink));
        $this->assertFileExists($outsideFile);
        $this->assertSame('protected-target', file_get_contents($outsideFile));

        $escape = $workspace.DIRECTORY_SEPARATOR.'escape';
        $this->requireSymlink($this->outsideDirectory, $escape);
        $throughSymlink = $escape.DIRECTORY_SEPARATOR.'target.jpg';

        $this->assertSame(realpath($outsideFile), realpath($throughSymlink));
        $this->assertFalse(PhotoImportTempContainment::isContainedFile($throughSymlink));
        $this->assertFalse(PhotoImportTempContainment::unlinkContainedFile($throughSymlink));
        $this->assertFileExists($outsideFile);
        $this->assertSame('protected-target', file_get_contents($outsideFile));

        $this->assertNull(PhotoImportTempContainment::extractionDirectoryFor($fileLink));
        $this->assertNull(PhotoImportTempContainment::extractionDirectoryFor($throughSymlink));
    }

    public function test_extraction_directory_for_returns_the_resolved_direct_child(): void
    {
        $token = $this->directChild('discover');
        $member = $token.DIRECTORY_SEPARATOR.'nested'.DIRECTORY_SEPARATOR.'photo.jpg';
        $this->makeDirectory(dirname($member));
        file_put_contents($member, 'member');

        $resolvedToken = realpath($token);
        $this->assertIsString($resolvedToken);
        $this->assertSame(
            $resolvedToken,
            PhotoImportTempContainment::extractionDirectoryFor($member),
            'Expected the resolved filesystem path of the direct-child extraction directory.',
        );

        $outsideFile = $this->outsideDirectory.DIRECTORY_SEPARATOR.'outside.jpg';
        $siblingFile = $this->siblingDirectory.DIRECTORY_SEPARATOR.'sibling.jpg';
        file_put_contents($outsideFile, 'outside');
        file_put_contents($siblingFile, 'sibling');

        $this->assertNull(PhotoImportTempContainment::extractionDirectoryFor($outsideFile));
        $this->assertNull(PhotoImportTempContainment::extractionDirectoryFor($siblingFile));
        $this->assertNull(PhotoImportTempContainment::extractionDirectoryFor(
            $this->traversalToOutside($token).DIRECTORY_SEPARATOR.'outside.jpg',
        ));
    }

    private function directChild(string $suffix): string
    {
        $path = $this->root.DIRECTORY_SEPARATOR.$this->runId.'-'.$suffix;
        $this->makeDirectory($path);

        return $path;
    }

    private function traversalToOutside(string $fromDirectory): string
    {
        return $fromDirectory
            .DIRECTORY_SEPARATOR.'..'
            .DIRECTORY_SEPARATOR.'..'
            .DIRECTORY_SEPARATOR.basename($this->outsideDirectory);
    }

    private function makeDirectory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0700, true) && ! is_dir($path)) {
            $this->fail('Could not create test fixture directory: '.$path);
        }
    }

    private function requireSymlink(string $target, string $link): void
    {
        if (@symlink($target, $link)) {
            return;
        }

        $error = error_get_last();
        $this->markTestSkipped(
            'Cannot create symlink from '.$link.' to '.$target.': '.($error['message'] ?? 'symlink() returned false'),
        );
    }

    private function directoryIsEmpty(string $path): bool
    {
        $entries = scandir($path) ?: [];

        return count(array_diff($entries, ['.', '..'])) === 0;
    }

    private function removeOwnedFixture(string $path): void
    {
        if ($path === $this->root || (is_dir($this->root) && realpath($path) === realpath($this->root))) {
            return;
        }

        if (is_link($path)) {
            unlink($path);

            return;
        }

        if (is_file($path)) {
            unlink($path);

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $this->removeOwnedFixture($path.DIRECTORY_SEPARATOR.$entry);
        }

        rmdir($path);
    }
}
