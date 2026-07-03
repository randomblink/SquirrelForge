<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Container\Container;
use SquirrelForge\Contracts\ContainerInterface;
use SquirrelForge\Modules\AbstractModule;
use SquirrelForge\Modules\ModuleLoader;
use SquirrelForge\Modules\ModuleRegistry;

final class RecordingTestModule extends AbstractModule
{
    public bool $registered = false;
    public bool $booted = false;

    public function __construct(private readonly string $id)
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return "Module {$this->id}";
    }

    public function register(ContainerInterface $container): void
    {
        $this->registered = true;
    }

    public function boot(ContainerInterface $container): void
    {
        $this->booted = true;
    }
}

final class ModuleTest extends TestCase
{
    public function testRegistryRegistersAndRetrievesByIdentifier(): void
    {
        $registry = new ModuleRegistry();
        $module = new RecordingTestModule('alpha');

        $registry->register($module);

        $this->assertTrue($registry->has('alpha'));
        $this->assertSame($module, $registry->get('alpha'));
        $this->assertNull($registry->get('missing'));
    }

    public function testRegistryCountAndAll(): void
    {
        $registry = new ModuleRegistry();
        $registry->register(new RecordingTestModule('a'));
        $registry->register(new RecordingTestModule('b'));

        $this->assertSame(2, $registry->count());
        $this->assertCount(2, $registry->all());
    }

    public function testRegistryUnregisterReturnsFalseWhenMissing(): void
    {
        $registry = new ModuleRegistry();

        $this->assertFalse($registry->unregister('nonexistent'));
    }

    public function testRegistryUnregisterRemovesAModule(): void
    {
        $registry = new ModuleRegistry();
        $registry->register(new RecordingTestModule('alpha'));

        $this->assertTrue($registry->unregister('alpha'));
        $this->assertFalse($registry->has('alpha'));
    }

    public function testAbstractModuleProvidesSensibleDefaults(): void
    {
        $module = new RecordingTestModule('alpha');

        $this->assertSame('1.0.0', $module->getVersion());
        $this->assertSame('', $module->getDescription());
    }

    public function testLoaderRegistersThenBootsAllModulesInTwoPasses(): void
    {
        $registry = new ModuleRegistry();
        $loader = new ModuleLoader($registry);
        $container = new Container();

        $moduleA = new RecordingTestModule('a');
        $moduleB = new RecordingTestModule('b');

        $loader->load([$moduleA, $moduleB], $container);

        $this->assertTrue($registry->has('a'));
        $this->assertTrue($registry->has('b'));
        $this->assertTrue($moduleA->registered);
        $this->assertTrue($moduleA->booted);
        $this->assertTrue($moduleB->registered);
        $this->assertTrue($moduleB->booted);
    }
}
