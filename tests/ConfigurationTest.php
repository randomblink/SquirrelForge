<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Core\Configuration;

final class ConfigurationTest extends TestCase
{
    public function testGetAndHasReflectInitialValues(): void
    {
        $config = new Configuration(['app' => ['name' => 'SquirrelForge']]);

        $this->assertTrue($config->has('app'));
        $this->assertSame(['name' => 'SquirrelForge'], $config->get('app'));
        $this->assertFalse($config->has('missing'));
        $this->assertSame('default', $config->get('missing', 'default'));
    }

    public function testSetAddsOrOverwritesAValue(): void
    {
        $config = new Configuration();
        $config->set('debug', true);

        $this->assertTrue($config->get('debug'));

        $config->set('debug', false);

        $this->assertFalse($config->get('debug'));
    }

    public function testRemoveDeletesAValue(): void
    {
        $config = new Configuration(['a' => 1]);
        $config->remove('a');

        $this->assertFalse($config->has('a'));
    }

    public function testAllReturnsEverything(): void
    {
        $values = ['a' => 1, 'b' => 2];
        $config = new Configuration($values);

        $this->assertSame($values, $config->all());
    }

    public function testMergeDeepMergesRecursively(): void
    {
        $config = new Configuration(['a' => ['x' => 1, 'y' => 2]]);
        $config->merge(['a' => ['y' => 20, 'z' => 3]]);

        $this->assertSame(['x' => 1, 'y' => 20, 'z' => 3], $config->get('a'));
    }

    public function testValidateReturnsNoErrorsForCleanConfiguration(): void
    {
        $config = new Configuration(['app' => ['name' => 'ok'], 'list' => [1, 2, 3]]);

        $this->assertSame([], $config->validate());
    }

    public function testValidateFlagsEmptyStringKeys(): void
    {
        $config = new Configuration([' ' => 'value']);

        $errors = $config->validate();

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('non-empty string', $errors[0]);
    }

    public function testValidateFlagsNestedResourceValues(): void
    {
        $resource = fopen('php://memory', 'r');
        $config = new Configuration(['a' => ['b' => $resource]]);

        $errors = $config->validate();

        fclose($resource);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('a.b', $errors[0]);
        $this->assertStringContainsString('cannot be a resource', $errors[0]);
    }

    public function testValidateAllowsListArraysWithoutStringKeys(): void
    {
        $config = new Configuration(['tags' => ['a', 'b', 'c']]);

        $this->assertSame([], $config->validate());
    }
}
