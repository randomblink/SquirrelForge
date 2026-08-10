<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\CredentialProvider;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SquirrelForge\CredentialProvider\TotpVerifier;

final class TotpVerifierTest extends TestCase
{
    /**
     * The ASCII secret RFC 6238 Appendix B's own published test
     * vectors use for the SHA1 case, Base32-encoded since this
     * class's public surface always takes a Base32 secret (the same
     * form an authenticator app enrolls).
     */
    private function rfcSecret(): string
    {
        return TotpVerifier::base32Encode('12345678901234567890');
    }

    /**
     * RFC 6238 Appendix B, SHA1 column, verbatim: {unix_time, expected 8-digit TOTP}.
     *
     * @return array<int, array{0: int, 1: string}>
     */
    public static function rfcVectorProvider(): array
    {
        return [
            [59, '94287082'],
            [1111111109, '07081804'],
            [1111111111, '14050471'],
            [1234567890, '89005924'],
            [2000000000, '69279037'],
        ];
    }

    #[DataProvider('rfcVectorProvider')]
    public function testMatchesRfc6238AppendixBTestVectors(int $timestamp, string $expectedCode): void
    {
        $totp = new TotpVerifier(digits: 8, periodSeconds: 30, window: 0);

        $this->assertSame($expectedCode, $totp->code($this->rfcSecret(), $timestamp));
    }

    #[DataProvider('rfcVectorProvider')]
    public function testVerifyAcceptsTheRfcVectorAtItsExactTimestamp(int $timestamp, string $expectedCode): void
    {
        $totp = new TotpVerifier(digits: 8, periodSeconds: 30, window: 0);

        $this->assertTrue($totp->verify($this->rfcSecret(), $expectedCode, $timestamp));
    }

    // --- real-world default shape (6 digits, 30s period) ---

    public function testDefaultConfigurationProducesASixDigitCode(): void
    {
        $totp = new TotpVerifier();
        $secret = TotpVerifier::generateSecret();

        $code = $totp->code($secret, 1_700_000_000);

        $this->assertSame(6, strlen($code));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    public function testVerifyAcceptsTheCurrentCode(): void
    {
        $totp = new TotpVerifier();
        $secret = TotpVerifier::generateSecret();
        $code = $totp->code($secret, 1_700_000_000);

        $this->assertTrue($totp->verify($secret, $code, 1_700_000_000));
    }

    public function testVerifyRejectsAWrongCode(): void
    {
        $totp = new TotpVerifier();
        $secret = TotpVerifier::generateSecret();

        $this->assertFalse($totp->verify($secret, '000000', 1_700_000_000));
    }

    public function testVerifyRejectsACodeFromADifferentSecret(): void
    {
        $totp = new TotpVerifier();
        $secretA = TotpVerifier::generateSecret();
        $secretB = TotpVerifier::generateSecret();
        $code = $totp->code($secretA, 1_700_000_000);

        $this->assertFalse($totp->verify($secretB, $code, 1_700_000_000));
    }

    // --- clock-drift tolerance window ---

    public function testVerifyToleratesOneStepOfClockDrift(): void
    {
        $totp = new TotpVerifier(window: 1);
        $secret = TotpVerifier::generateSecret();
        $codeOneStepAgo = $totp->code($secret, 1_700_000_000 - 30);

        $this->assertTrue($totp->verify($secret, $codeOneStepAgo, 1_700_000_000));
    }

    public function testVerifyRejectsDriftBeyondTheConfiguredWindow(): void
    {
        $totp = new TotpVerifier(window: 1);
        $secret = TotpVerifier::generateSecret();
        $codeTwoStepsAgo = $totp->code($secret, 1_700_000_000 - 60);

        $this->assertFalse($totp->verify($secret, $codeTwoStepsAgo, 1_700_000_000));
    }

    // --- provisioning URI ---

    public function testProvisioningUriCarriesTheSecretAndIdentity(): void
    {
        $totp = new TotpVerifier();
        $secret = TotpVerifier::generateSecret();

        $uri = $totp->provisioningUri($secret, 'agent_1@example.test', 'SquirrelForge');

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=' . $secret, $uri);
        $this->assertStringContainsString(rawurlencode('agent_1@example.test'), $uri);
    }
}
