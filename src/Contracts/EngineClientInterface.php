<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

interface EngineClientInterface
{
    public function submit(
        array $request,
        string $projectRef,
        string $identityRef,
        string $permissionRef,
        string $idempotencyKey,
        string $correlationId
    ): array;

    public function inspect(
        string $executionRef,
        string $identityRef,
        string $permissionRef,
        string $correlationId
    ): array;

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

    public function result(
        string $executionRef,
        string $identityRef,
        string $permissionRef,
        string $correlationId
    ): array;
}
