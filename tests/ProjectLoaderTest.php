<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Engine\ProjectLoader;

final class ProjectLoaderTest extends TestCase
{
    /** @var array<int, string> */
    private array $tempDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirectories as $directory) {
            $this->removeDirectory($directory);
        }
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

            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }

    private function makeTempRepository(string $label): string
    {
        $directory = sys_get_temp_dir() . '/sfpl-fixture-' . $label . '-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $this->tempDirectories[] = $directory;

        shell_exec(sprintf('git -C %s init -q 2>/dev/null', escapeshellarg($directory)));
        shell_exec(sprintf('git -C %s config user.email "test@example.com" 2>/dev/null', escapeshellarg($directory)));
        shell_exec(sprintf('git -C %s config user.name "Test" 2>/dev/null', escapeshellarg($directory)));
        file_put_contents($directory . '/README.md', "# {$label}\n");
        shell_exec(sprintf('git -C %s add README.md 2>/dev/null', escapeshellarg($directory)));
        shell_exec(sprintf('git -C %s commit -q -m "initial" 2>/dev/null', escapeshellarg($directory)));

        return $directory;
    }

    public function testVerifyRepositoryIdentityMatchesTheRequestedProject(): void
    {
        $loader = new ProjectLoader();
        $repository = $this->makeTempRepository('squirrelforge-work');

        $result = $loader->verifyRepositoryIdentity(basename($repository), $repository);

        $this->assertSame('matched', $result['status']);
        $this->assertNotNull($result['repository_root']);
        $this->assertNull($result['error']);
    }

    public function testVerifyRepositoryIdentityReportsAMismatch(): void
    {
        $loader = new ProjectLoader();
        $repository = $this->makeTempRepository('hospital-cshd');

        $result = $loader->verifyRepositoryIdentity('SquirrelForge', $repository);

        $this->assertSame('mismatch', $result['status']);
        $this->assertStringContainsString('SquirrelForge', $result['error']);
    }

    public function testVerifyRepositoryIdentityWithANonRepositoryReportsThatOutcome(): void
    {
        $loader = new ProjectLoader();
        $directory = sys_get_temp_dir() . '/sfpl-fixture-not-a-repo-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $this->tempDirectories[] = $directory;

        $result = $loader->verifyRepositoryIdentity('anything', $directory);

        $this->assertSame('not_a_repository', $result['status']);
    }

    public function testWorkedExampleMismatchUsesAnInjectedShellForDeterminism(): void
    {
        $loader = new ProjectLoader(function (string $command): string {
            return match (true) {
                str_contains($command, 'rev-parse --show-toplevel') => "/Users/randomblink/Local Sites/hospital/app/public/wp-content/plugins/cshd\n",
                str_contains($command, 'rev-parse --abbrev-ref HEAD') => "main\n",
                str_contains($command, 'remote -v') => "origin\tgit@example.com:hospital/cshd.git (fetch)\n",
                default => '',
            };
        });

        $result = $loader->verifyRepositoryIdentity('SquirrelForge', '/Users/randomblink/Local Sites/hospital/app/public/wp-content/plugins/cshd');

        $this->assertSame('mismatch', $result['status']);
        $this->assertStringContainsString('hospital', $result['remotes']);
    }

    public function testInspectWorkingTreeReportsACleanRepository(): void
    {
        $loader = new ProjectLoader();
        $repository = $this->makeTempRepository('clean');

        $result = $loader->inspectWorkingTree($repository);

        $this->assertTrue($result['clean']);
        $this->assertSame([], $result['modified']);
        $this->assertSame([], $result['untracked']);
    }

    public function testInspectWorkingTreeReportsUntrackedAndModifiedFiles(): void
    {
        $loader = new ProjectLoader();
        $repository = $this->makeTempRepository('dirty');
        file_put_contents($repository . '/README.md', "# changed\n");
        file_put_contents($repository . '/new-file.txt', "new\n");

        $result = $loader->inspectWorkingTree($repository);

        $this->assertFalse($result['clean']);
        $this->assertContains('README.md', $result['modified']);
        $this->assertContains('new-file.txt', $result['untracked']);
    }

    public function testDetectProjectTypeIdentifiesAPhpLibrary(): void
    {
        $loader = new ProjectLoader();
        $repository = $this->makeTempRepository('php-lib');
        file_put_contents($repository . '/composer.json', '{}');

        $result = $loader->detectProjectType($repository);

        $this->assertSame('php_library', $result['project_type']);
        $this->assertContains('php', $result['active_domains']);
    }

    public function testDetectProjectTypeIdentifiesAWordPressPlugin(): void
    {
        $loader = new ProjectLoader();
        $repository = $this->makeTempRepository('wp-plugin');
        file_put_contents($repository . '/my-plugin.php', "<?php\n/**\n * Plugin Name: My Plugin\n */\n");

        $result = $loader->detectProjectType($repository);

        $this->assertSame('wordpress_plugin', $result['project_type']);
    }

    public function testDetectProjectTypeWithNoRecognizedSignalsIsUnknown(): void
    {
        $loader = new ProjectLoader();
        $repository = $this->makeTempRepository('unknown-type');

        $result = $loader->detectProjectType($repository);

        $this->assertSame('unknown', $result['project_type']);
        $this->assertSame([], $result['active_domains']);
    }

    public function testLoadWithAMismatchIsBlocked(): void
    {
        $loader = new ProjectLoader();
        $repository = $this->makeTempRepository('other-project');

        $result = $loader->load('SquirrelForge', $repository);

        $this->assertSame('BLOCKED', $result['readiness_state']);
        $this->assertSame(['repository_identity'], $result['missing_context']);
    }

    public function testLoadWithAMatchingCleanKnownProjectIsReady(): void
    {
        $loader = new ProjectLoader();
        $repository = $this->makeTempRepository('ready-project');
        file_put_contents($repository . '/composer.json', '{}');
        shell_exec(sprintf('git -C %s add composer.json 2>/dev/null', escapeshellarg($repository)));
        shell_exec(sprintf('git -C %s commit -q -m "add composer" 2>/dev/null', escapeshellarg($repository)));

        $result = $loader->load(basename($repository), $repository);

        $this->assertSame('READY', $result['readiness_state']);
        $this->assertSame('php_library', $result['project_type']);
        $this->assertSame([], $result['missing_context']);
    }

    public function testLoadWithUncommittedChangesIsReadyWithLimitations(): void
    {
        $loader = new ProjectLoader();
        $repository = $this->makeTempRepository('dirty-project');
        file_put_contents($repository . '/composer.json', '{}');
        shell_exec(sprintf('git -C %s add composer.json 2>/dev/null', escapeshellarg($repository)));
        shell_exec(sprintf('git -C %s commit -q -m "add composer" 2>/dev/null', escapeshellarg($repository)));
        file_put_contents($repository . '/scratch.txt', 'wip');

        $result = $loader->load(basename($repository), $repository);

        $this->assertSame('READY_WITH_LIMITATIONS', $result['readiness_state']);
        $this->assertContains('working_tree_has_uncommitted_changes', $result['missing_context']);
    }
}
