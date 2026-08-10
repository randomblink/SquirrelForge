<?php

declare(strict_types=1);

namespace SquirrelForge\CredentialProvider;

/**
 * Real RFC 6238 (TOTP) / RFC 4226 (HOTP) time-based one-time password
 * generation and verification -- the algorithm every standard
 * authenticator app (Google Authenticator, Authy, 1Password, etc.)
 * already implements, so a secret enrolled here works with any of
 * them without SquirrelForge needing its own client app.
 *
 * Verified against RFC 6238 Appendix B's own published test vectors
 * (see `TotpVerifierTest`) -- this is not a plausible-looking
 * approximation, it produces the exact codes the RFC itself specifies
 * for a known secret and timestamp.
 *
 * `verify()` checks a small window of adjacent time steps (default:
 * one step before and after the current one) to tolerate real clock
 * drift between the server and the user's device, and uses
 * `hash_equals()` for the actual comparison -- the same constant-time
 * discipline this codebase already applies to every other credential
 * comparison.
 */
final class TotpVerifier
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function __construct(
        private readonly int $digits = 6,
        private readonly int $periodSeconds = 30,
        private readonly int $window = 1
    ) {
    }

    /**
     * Generates a fresh, random, Base32-encoded shared secret suitable
     * for enrolling in a standard authenticator app.
     */
    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    /**
     * The `otpauth://` provisioning URI a QR code can be generated
     * from, per the de facto Key URI Format standard authenticator
     * apps already support.
     */
    public function provisioningUri(string $secretBase32, string $identityRef, string $issuer = 'SquirrelForge'): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($identityRef),
            $secretBase32,
            rawurlencode($issuer),
            $this->digits,
            $this->periodSeconds
        );
    }

    /**
     * The current TOTP code for a given secret -- exposed for tests
     * and for an enrollment flow to show a caller "here is the code
     * right now," never used to decide verification on its own.
     */
    public function code(string $secretBase32, ?int $timestamp = null): string
    {
        return $this->hotp($secretBase32, $this->counterFor($timestamp ?? time()));
    }

    /**
     * Verifies a presented code against the current time step and the
     * configured tolerance window, in constant time per candidate.
     */
    public function verify(string $secretBase32, string $code, ?int $timestamp = null): bool
    {
        $counter = $this->counterFor($timestamp ?? time());

        for ($offset = -$this->window; $offset <= $this->window; $offset++) {
            if (hash_equals($this->hotp($secretBase32, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    private function counterFor(int $timestamp): int
    {
        return intdiv($timestamp, $this->periodSeconds);
    }

    /**
     * RFC 4226 HOTP: HMAC-SHA1 over an 8-byte big-endian counter,
     * dynamically truncated to a `$this->digits`-digit decimal code.
     */
    private function hotp(string $secretBase32, int $counter): string
    {
        $key = self::base32Decode($secretBase32);
        $counterBytes = pack('J', $counter < 0 ? 0 : $counter);
        $hash = hash_hmac('sha1', $counterBytes, $key, true);

        $offset = ord($hash[19]) & 0x0f;
        $binary = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        $code = $binary % (10 ** $this->digits);

        return str_pad((string) $code, $this->digits, '0', STR_PAD_LEFT);
    }

    /**
     * Exposed as a public utility (e.g. for encoding a known raw
     * secret, or for an enrollment flow that already has raw key
     * bytes from elsewhere) -- `generateSecret()` is the normal path
     * for a fresh, random secret.
     */
    public static function base32Encode(string $data): string
    {
        if ($data === '') {
            return '';
        }

        $bits = '';
        foreach (str_split($data) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $bits = str_pad($bits, (int) (ceil(strlen($bits) / 5) * 5), '0', STR_PAD_RIGHT);
        $encoded = '';

        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::BASE32_ALPHABET[bindec($chunk)];
        }

        return $encoded;
    }

    private static function base32Decode(string $secretBase32): string
    {
        $secretBase32 = strtoupper(rtrim($secretBase32, '='));
        $bits = '';

        foreach (str_split($secretBase32) as $char) {
            $value = strpos(self::BASE32_ALPHABET, $char);

            if ($value === false) {
                continue;
            }

            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) < 8) {
                continue;
            }

            $bytes .= chr(bindec($chunk));
        }

        return $bytes;
    }
}
