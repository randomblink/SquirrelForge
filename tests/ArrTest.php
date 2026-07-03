<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Support\Arr;

final class ArrTest extends TestCase
{
    public function testGetReturnsTopLevelValue(): void
    {
        $this->assertSame('bar', Arr::get(['foo' => 'bar'], 'foo'));
    }

    public function testGetReturnsNestedValueViaDotNotation(): void
    {
        $array = ['a' => ['b' => ['c' => 'deep']]];

        $this->assertSame('deep', Arr::get($array, 'a.b.c'));
    }

    public function testGetReturnsDefaultWhenKeyMissing(): void
    {
        $this->assertSame('fallback', Arr::get(['a' => 1], 'missing', 'fallback'));
        $this->assertNull(Arr::get(['a' => 1], 'missing'));
    }

    public function testGetReturnsDefaultWhenIntermediateSegmentIsNotAnArray(): void
    {
        $array = ['a' => 'not-an-array'];

        $this->assertSame('fallback', Arr::get($array, 'a.b', 'fallback'));
    }

    public function testGetWithEmptyKeyReturnsWholeArray(): void
    {
        $array = ['a' => 1, 'b' => 2];

        $this->assertSame($array, Arr::get($array, ''));
    }

    public function testSetCreatesNestedStructure(): void
    {
        $array = [];
        Arr::set($array, 'a.b.c', 'value');

        $this->assertSame(['a' => ['b' => ['c' => 'value']]], $array);
    }

    public function testSetOverwritesANonArrayIntermediateSegment(): void
    {
        $array = ['a' => 'scalar'];
        Arr::set($array, 'a.b', 'value');

        $this->assertSame(['a' => ['b' => 'value']], $array);
    }

    public function testSetOverwritesTopLevelValue(): void
    {
        $array = ['a' => 1];
        Arr::set($array, 'a', 2);

        $this->assertSame(['a' => 2], $array);
    }

    public function testHasReturnsTrueForExistingNonNullValue(): void
    {
        $array = ['a' => ['b' => 'c']];

        $this->assertTrue(Arr::has($array, 'a.b'));
    }

    public function testHasReturnsFalseForMissingOrNullValue(): void
    {
        $array = ['a' => null];

        $this->assertFalse(Arr::has($array, 'a'));
        $this->assertFalse(Arr::has($array, 'missing'));
    }

    public function testRemoveDeletesANestedKey(): void
    {
        $array = ['a' => ['b' => 'c', 'd' => 'e']];
        Arr::remove($array, 'a.b');

        $this->assertSame(['a' => ['d' => 'e']], $array);
    }

    public function testRemoveIsANoOpWhenPathDoesNotExist(): void
    {
        $array = ['a' => ['b' => 'c']];
        Arr::remove($array, 'x.y.z');

        $this->assertSame(['a' => ['b' => 'c']], $array);
    }

    public function testRemoveTopLevelKey(): void
    {
        $array = ['a' => 1, 'b' => 2];
        Arr::remove($array, 'a');

        $this->assertSame(['b' => 2], $array);
    }
}
