<?php
/**
 * Unit tests file for CSRF token generation and validation patterns.
 *
 * Wallos uses random_bytes(32) for token generation and hash_equals() for timing-safe comparison, as implemented in libs/csrf.php.
 * These tests verify the cryptographic properties of this approach.
 *
 * Related categories: ISO 25010: Security (Authenticity, Integrity)
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;


class CsrfTokenTest extends TestCase
{
    // Standard token generation tests (happy path)
    #[Test]
    public function tokenGenerationProducesSufficientEntropy(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->assertEquals(64, strlen($token),
            'CSRF token should be 64 hex characters (32 bytes of entropy)');
    }


    // Token validation tests
    #[Test]
    public function consecutiveTokensAreUnique(): void
    {
        $tokens = [];
        for ($i = 0; $i < 100; $i++) {
            $tokens[] = bin2hex(random_bytes(32));
        }
        $this->assertCount(100, array_unique($tokens),
            'All 100 generated tokens should be unique');
    }

    // Security properties of hash_equals() for token comparison
    #[Test]
    public function tokenContainsOnlyHexCharacters(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    // Testing hash_equals() for timing-safe comparison of tokens
    #[Test]
    public function hashEqualsComparesTokensCorrectly(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->assertTrue(hash_equals($token, $token));
        $this->assertFalse(hash_equals($token, bin2hex(random_bytes(32))));
    }

    // Edge case tests for token validation
    #[Test]
    public function hashEqualsRejectsPartialMatch(): void
    {
        $known = bin2hex(random_bytes(32));
        $partial = substr($known, 0, 32) . bin2hex(random_bytes(16));
        $this->assertFalse(hash_equals($known, $partial),
            'Partially matching tokens must be rejected');
    }

    // Additional edge cases for token validation
    #[Test]
    public function emptyTokenIsRejected(): void
    {
        $validToken = bin2hex(random_bytes(32));
        $this->assertFalse(hash_equals($validToken, ''),
            'Empty token must be rejected');
    }

   
    #[Test]
    public function wrongLengthTokenIsRejected(): void
    {
        $validToken = bin2hex(random_bytes(32));
        $shortToken = bin2hex(random_bytes(16));
        $this->assertFalse(hash_equals($validToken, $shortToken),
            'Wrong length token must be rejected');
    }
}
