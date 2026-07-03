<?php

declare(strict_types=1);

namespace SquirrelForge\Modules;

use SquirrelForge\Contracts\ServiceProviderInterface;

interface ModuleInterface extends ServiceProviderInterface
{
    public function getId(): string;

    public function getName(): string;

    public function getVersion(): string;

    public function getDescription(): string;
}
