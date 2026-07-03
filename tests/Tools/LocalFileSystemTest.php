<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Tools;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Tools\LocalFileSystem;

/**
 * Exercises LocalFileSystem against a real, disposable temp directory --
 * this is the containment boundary that makes it safe to give a role agent
 * write access, so it's worth testing against the real filesystem rather
 * than a fake.
 */
final class LocalFileSystemTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/sf_lfs_test_' . uniqid('', true);
        mkdir($this->root, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testWritesAndReadsAFileCreatingParentDirectories(): void
    {
        $fs = new LocalFileSystem($this->root);

        $fs->write('src/Example/Widget.php', "<?php\n// widget\n");

        $this->assertTrue($fs->exists('src/Example/Widget.php'));
        $this->assertSame("<?php\n// widget\n", $fs->read('src/Example/Widget.php'));
        $this->assertFileExists($this->root . '/src/Example/Widget.php');
    }

    public function testDeleteIsANoOpWhenFileDoesNotExist(): void
    {
        $fs = new LocalFileSystem($this->root);

        $fs->delete('never/existed.txt');

        $this->assertFalse($fs->exists('never/existed.txt'));
    }

    public function testDeleteRemovesAnExistingFile(): void
    {
        $fs = new LocalFileSystem($this->root);
        $fs->write('to-remove.txt', 'bye');

        $fs->delete('to-remove.txt');

        $this->assertFalse($fs->exists('to-remove.txt'));
    }

    public function testRefusesPathTraversalOnWrite(): void
    {
        $fs = new LocalFileSystem($this->root);

        $this->expectException(\RuntimeException::class);

        $fs->write('../escape.txt', 'nope');
    }

    public function testRefusesAbsolutePathOnWrite(): void
    {
        $fs = new LocalFileSystem($this->root);

        $this->expectException(\RuntimeException::class);

        $fs->write('/etc/passwd', 'nope');
    }

    public function testRefusesPathTraversalBuriedInTheMiddleOfAPath(): void
    {
        $fs = new LocalFileSystem($this->root);

        $this->expectException(\RuntimeException::class);

        $fs->write('src/../../escape.txt', 'nope');
    }

    public function testAllowsFilenamesThatOnlyContainDotDotAsASubstring(): void
    {
        $fs = new LocalFileSystem($this->root);

        // "my..file.txt" is a legitimate filename -- ".." here is not a
        // traversal segment, so this must be allowed.
        $fs->write('my..file.txt', 'fine');

        $this->assertTrue($fs->exists('my..file.txt'));
    }

    public function testReadingAMissingFileThrows(): void
    {
        $fs = new LocalFileSystem($this->root);

        $this->expectException(\RuntimeException::class);

        $fs->read('does-not-exist.txt');
    }

    public function testRefusesWriteThroughASymlinkedDirectoryEscapingRoot(): void
    {
        $outside = sys_get_temp_dir() . '/sf_lfs_outside_' . uniqid('', true);
        mkdir($outside, 0775, true);

        try {
            symlink($outside, $this->root . '/escape-link');

            $fs = new LocalFileSystem($this->root);

            $this->expectException(\RuntimeException::class);

            // Textually this is contained (no ".." segment), but
            // "escape-link" resolves outside the root, so this must still
            // be refused.
            $fs->write('escape-link/payload.txt', 'nope');
        } finally {
            $this->assertFileDoesNotExist($outside . '/payload.txt');
            $this->removeDirectory($outside);
        }
    }

    public function testRefusesDeleteThroughASymlinkedFileEscapingRoot(): void
    {
        $outside = sys_get_temp_dir() . '/sf_lfs_outside_' . uniqid('', true);
        mkdir($outside, 0775, true);
        file_put_contents($outside . '/keep-me.txt', 'important');

        try {
            symlink($outside . '/keep-me.txt', $this->root . '/escape-link.txt');

            $fs = new LocalFileSystem($this->root);

            $this->expectException(\RuntimeException::class);

            $fs->delete('escape-link.txt');
        } finally {
            $this->assertFileExists($outside . '/keep-me.txt');
            $this->removeDirectory($outside);
        }
    }

    public function testStillCreatesNestedDirectoriesWithNoSymlinksInvolved(): void
    {
        $fs = new LocalFileSystem($this->root);

        // Guards against the ancestry check being over-eager and rejecting
        // ordinary multi-level directory creation that involves no symlink
        // at all.
        $fs->write('a/b/c/d/deep.txt', 'deep');

        $this->assertTrue($fs->exists('a/b/c/d/deep.txt'));
        $this->assertSame('deep', $fs->read('a/b/c/d/deep.txt'));
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
