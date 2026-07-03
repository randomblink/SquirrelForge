<?php

declare(strict_types=1);

namespace SquirrelForge\Modules;

use FilesystemIterator;
use ReflectionClass;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Scans a directory tree for classes that look like modules -- file name
 * ending in "Module.php" -- and returns an instance of every concrete class
 * found there that implements `ModuleInterface` with a constructor that
 * requires no arguments.
 *
 * This lets `Kernel::loadModules()` pick up new modules anywhere under
 * `src/` without editing `Kernel.php`: filesystem auto-discovery, not a
 * hardcoded list.
 *
 * This intentionally does not eval or `require` files directly. It only
 * computes the fully-qualified class name a file would define under PSR-4
 * and lets Composer's autoloader resolve and load it via `class_exists()`.
 * A discovered module gets exactly the same trust and container access a
 * manually-registered module would -- discovery changes how a module is
 * found, not what it's allowed to do once found. Anything placed under the
 * scanned directory ending in "Module.php" that implements `ModuleInterface`
 * will have its `register()`/`boot()` invoked with full container access,
 * so the scanned directory must only ever contain trusted, reviewed code
 * (this is true of `src/` in general, same as any other class here).
 */
final class ModuleDiscovery
{
    /**
     * @var array<int, string>
     */
    private array $errors = [];

    /**
     * @param string $directory Absolute path to scan (e.g. the src/ root).
     * @param string $namespacePrefix The PSR-4 namespace root mapped to $directory (e.g. "SquirrelForge").
     * @param array<int, class-string> $exclude Fully-qualified class names to never instantiate, even if discovered.
     * @return array<int, ModuleInterface>
     */
    public function discover(string $directory, string $namespacePrefix, array $exclude = []): array
    {
        $this->errors = [];
        $modules = [];

        foreach ($this->findCandidateFiles($directory) as $file) {
            $class = $this->classNameFor($file, $directory, $namespacePrefix);

            if ($class === null || in_array($class, $exclude, true)) {
                continue;
            }

            try {
                if (!class_exists($class)) {
                    continue;
                }

                $reflection = new ReflectionClass($class);

                if (!$this->isDiscoverableModule($reflection)) {
                    continue;
                }

                /** @var ModuleInterface $instance */
                $instance = $reflection->newInstance();
                $modules[] = $instance;
            } catch (Throwable $e) {
                // One broken candidate must not stop discovery or boot.
                $this->errors[] = sprintf('%s: %s', $class, $e->getMessage());
            }
        }

        return $modules;
    }

    /**
     * Errors encountered while resolving candidates from the most recent
     * `discover()` call. Never thrown -- callers may log them.
     *
     * @return array<int, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    private function isDiscoverableModule(ReflectionClass $reflection): bool
    {
        if ($reflection->isAbstract() || $reflection->isInterface()) {
            return false;
        }

        if (!$reflection->implementsInterface(ModuleInterface::class)) {
            return false;
        }

        $constructor = $reflection->getConstructor();

        // Discovery has no way to supply constructor arguments; modules
        // that need them must be wired manually instead of discovered.
        return $constructor === null || $constructor->getNumberOfRequiredParameters() === 0;
    }

    /**
     * @return array<int, string>
     */
    private function findCandidateFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        $files = [];

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && str_ends_with($fileInfo->getFilename(), 'Module.php')) {
                $files[] = $fileInfo->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function classNameFor(string $file, string $baseDirectory, string $namespacePrefix): ?string
    {
        $realBase = realpath($baseDirectory);
        $realFile = realpath($file);

        if ($realBase === false || $realFile === false || !str_starts_with($realFile, $realBase)) {
            return null;
        }

        $relative = ltrim(substr($realFile, strlen($realBase)), DIRECTORY_SEPARATOR);
        $relative = substr($relative, 0, -4); // strip ".php"
        $relative = str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

        return rtrim($namespacePrefix, '\\') . '\\' . $relative;
    }
}
