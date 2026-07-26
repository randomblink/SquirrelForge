<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

interface EngineRuntimeInterface
{
    public function submit(
        array $request,
        string $projectRef,
        string $identityRef,
        string $permissionRef,
        string $idempotencyKey,
        string $correlationId
    ): array;

    public function inspect(string $executionRef): array;

    public function provideInput(
        string $executionRef,
        array $input,
        string $identityRef,
        string $permissionRef,
        string $correlationId
    ): array;

    public function cancel(
        string $executionRef,
        string $reason,
        string $identityRef,
        string $permissionRef,
        string $correlationId
    ): array;

    public function result(string $executionRef): array;

    public function validation(string $executionRef): array;
}
