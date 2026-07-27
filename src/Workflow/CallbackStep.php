<?php

declare(strict_types=1);

namespace SquirrelForge\Workflow;

use Closure;
use SquirrelForge\Contracts\StepInterface;

final class CallbackStep implements StepInterface
{
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly Closure $run,
        private readonly ?Closure $onRollback = null
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function run(array $context): array
    {
        return ($this->run)($context);
    }

    public function rollback(array $context): void
    {
        if ($this->onRollback !== null) {
            ($this->onRollback)($context);
        }
    }
}
