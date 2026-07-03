<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Container\Container;
use SquirrelForge\Contracts\ContainerInterface;
use stdClass;

final class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testBindResolvesAClassString(): void
    {
        $this->container->bind('thing', stdClass::class);

        $this->assertInstanceOf(stdClass::class, $this->container->make('thing'));
    }

    public function testBindReturnsANewInstanceEveryTime(): void
    {
        $this->container->bind('thing', stdClass::class);

        $first = $this->container->make('thing');
        $second = $this->container->make('thing');

        $this->assertNotSame($first, $second);
    }

    public function testSingletonReturnsTheSameInstanceEveryTime(): void
    {
        $this->container->singleton('thing', stdClass::class);

        $first = $this->container->make('thing');
        $second = $this->container->make('thing');

        $this->assertSame($first, $second);
    }

    public function testSingletonAcceptsAClosure(): void
    {
        $this->container->singleton('thing', function (ContainerInterface $c): stdClass {
            $object = new stdClass();
            $object->built = true;

            return $object;
        });

        $resolved = $this->container->make('thing');

        $this->assertTrue($resolved->built);
        $this->assertSame($resolved, $this->container->make('thing'));
    }

    public function testInstanceRegistersAnExistingObject(): void
    {
        $object = new stdClass();
        $this->container->instance('thing', $object);

        $this->assertSame($object, $this->container->make('thing'));
    }

    public function testMakeResolvesAnUnregisteredExistingClassDirectly(): void
    {
        $resolved = $this->container->make(stdClass::class);

        $this->assertInstanceOf(stdClass::class, $resolved);
    }

    public function testMakeThrowsWhenServiceCannotBeFound(): void
    {
        $this->expectException(RuntimeException::class);

        $this->container->make('nonexistent-service-' . uniqid());
    }

    public function testHasReflectsRegisteredBindings(): void
    {
        $this->assertFalse($this->container->has('thing'));

        $this->container->bind('thing', stdClass::class);

        $this->assertTrue($this->container->has('thing'));
    }

    public function testForgetRemovesABinding(): void
    {
        $this->container->singleton('thing', stdClass::class);
        $this->container->make('thing');

        $this->container->forget('thing');

        $this->assertFalse($this->container->has('thing'));
    }

    public function testFlushClearsEverything(): void
    {
        $this->container->bind('a', stdClass::class);
        $this->container->singleton('b', stdClass::class);
        $this->container->instance('c', new stdClass());

        $this->container->flush();

        $this->assertFalse($this->container->has('a'));
        $this->assertFalse($this->container->has('b'));
        $this->assertFalse($this->container->has('c'));
    }

    public function testRebindingASingletonDropsThePreviouslyCachedInstance(): void
    {
        $this->container->singleton('thing', stdClass::class);
        $first = $this->container->make('thing');

        $this->container->singleton('thing', function (): stdClass {
            $object = new stdClass();
            $object->rebound = true;

            return $object;
        });

        $second = $this->container->make('thing');

        $this->assertNotSame($first, $second);
        $this->assertTrue($second->rebound);
    }
}
