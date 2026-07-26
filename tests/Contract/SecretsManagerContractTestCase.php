<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Contract;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\SecretsManagerInterface;

abstract class SecretsManagerContractTestCase extends TestCase
{
    abstract protected function secretsManager(): SecretsManagerInterface;

    public function testProviderRotatesAndRevokesWithoutReturningSecretValues(): void
    {
        $provider = $this->secretsManager();
        $identityRef = 'contract_identity_' . bin2hex(random_bytes(4));
        $oldKey = 'contract_old_' . bin2hex(random_bytes(24));
        $newKey = 'contract_new_' . bin2hex(random_bytes(24));
        $oldRef = $provider->registerApiKey($identityRef, $oldKey);
        $newRef = $provider->rotateApiKey($oldRef, $newKey);

        self::assertNotSame($oldRef, $newRef);
        self::assertFalse($provider->verifyApiKey($identityRef, $oldKey)['verified']);
        self::assertTrue($provider->verifyApiKey($identityRef, $newKey)['verified']);

        $provider->revoke($newRef);

        self::assertFalse($provider->verifyApiKey($identityRef, $newKey)['verified']);
        self::assertStringNotContainsString($oldKey, json_encode([$oldRef, $newRef], JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString($newKey, json_encode([$oldRef, $newRef], JSON_THROW_ON_ERROR));
    }
}
