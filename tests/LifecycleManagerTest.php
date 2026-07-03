<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Core\LifecycleManager;

final class LifecycleManagerTest extends TestCase
{
    public function testStartsNotRunning(): void
    {
        $manager = new LifecycleManager();

        $this->assertFalse($manager->isRunning());
    }

    public function testStartMarksItAsRunning(): void
    {
        $manager = new LifecycleManager();
        $manager->start();

        $this->assertTrue($manager->isRunning());
    }

    public function testStopMarksItAsNotRunning(): void
    {
        $manager = new LifecycleManager();
        $manager->start();
        $manager->stop();

        $this->assertFalse($manager->isRunning());
    }

    public function testRestartLeavesItRunning(): void
    {
        $manager = new LifecycleManager();
        $manager->restart();

        $this->assertTrue($manager->isRunning());
    }

    public function testRestartWhileRunningStaysRunning(): void
    {
        $manager = new LifecycleManager();
        $manager->start();
        $manager->restart();

        $this->assertTrue($manager->isRunning());
    }
}
