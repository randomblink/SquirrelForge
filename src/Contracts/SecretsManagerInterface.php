<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

use DateTimeImmutable;

interface SecretsManagerInterface
{
    public function registerApiKey(
        string $identityRef,
        string $apiKey,
        ?DateTimeImmutable $expiresAt = null
    ): string;

    public function verifyApiKey(string $identityRef, string $apiKey): array;

    public function rotateApiKey(
        string $secretRef,
        string $newApiKey,
        ?DateTimeImmutable $expiresAt = null
    ): string;

    public function revoke(string $secretRef): void;
}
