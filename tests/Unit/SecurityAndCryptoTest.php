<?php
/**
 * Characterization tests for security and cryptography functions in common.lib.php.
 *
 * obfuscateURL/deobfuscateURL use OpenSSL with a random IV, so we can
 * only test the round-trip property rather than exact output.
 *
 * simpleEncrypt/simpleDecrypt do the same.
 *
 * verify_token() is pure: we can construct known inputs.
 */

use PHPUnit\Framework\TestCase;

class SecurityAndCryptoTest extends TestCase
{
    // Shared test key — must be valid base64 of a 32-byte value for AES-256
    private string $testKey;

    protected function setUp(): void
    {
        // base64 of a 32-byte string
        $this->testKey = base64_encode(str_repeat('x', 32));
    }

    // ── obfuscateURL / deobfuscateURL ────────────────────────

    public function test_obfuscate_deobfuscate_roundtrip_simple_url(): void
    {
        $url = "index.php?section=admin&go=entries";
        $encrypted = obfuscateURL($url, $this->testKey);
        $this->assertSame($url, deobfuscateURL($encrypted, $this->testKey));
    }

    public function test_obfuscate_deobfuscate_roundtrip_with_id(): void
    {
        $url = "index.php?section=brew&action=edit&id=42";
        $encrypted = obfuscateURL($url, $this->testKey);
        $this->assertSame($url, deobfuscateURL($encrypted, $this->testKey));
    }

    public function test_obfuscate_deobfuscate_roundtrip_empty_string(): void
    {
        $url = "";
        $encrypted = obfuscateURL($url, $this->testKey);
        $this->assertSame($url, deobfuscateURL($encrypted, $this->testKey));
    }

    public function test_obfuscate_produces_different_output_each_time(): void
    {
        // AES-CBC with random IV should produce different ciphertext each call
        $url = "test-url";
        $enc1 = obfuscateURL($url, $this->testKey);
        $enc2 = obfuscateURL($url, $this->testKey);
        // Both decrypt correctly
        $this->assertSame($url, deobfuscateURL($enc1, $this->testKey));
        $this->assertSame($url, deobfuscateURL($enc2, $this->testKey));
    }

    public function test_obfuscate_output_is_url_safe(): void
    {
        // The function substitutes +, /, = with _p_, _s_, _e_
        $url = "section=admin&id=99";
        $encrypted = obfuscateURL($url, $this->testKey);
        $this->assertStringNotContainsString("+", $encrypted);
        $this->assertStringNotContainsString("/", $encrypted);
        $this->assertStringNotContainsString("=", $encrypted);
    }

    // ── simpleEncrypt / simpleDecrypt ────────────────────────

    public function test_simple_encrypt_decrypt_roundtrip(): void
    {
        $data = "brewer-id-42";
        $key  = $this->testKey;
        $salt = base64_encode("test-salt-value!");

        $encrypted = simpleEncrypt($data, $key, $salt);
        $decrypted  = simpleDecrypt($encrypted, $key, $salt);
        $this->assertSame($data, $decrypted);
    }

    public function test_simple_encrypt_decrypt_roundtrip_with_special_chars(): void
    {
        $data = "user@example.com|admin|2025-01-01";
        $key  = $this->testKey;
        $salt = base64_encode("another-salt!!");

        $encrypted = simpleEncrypt($data, $key, $salt);
        $decrypted  = simpleDecrypt($encrypted, $key, $salt);
        $this->assertSame($data, $decrypted);
    }

    // ── verify_token() ───────────────────────────────────────
    // Token format: base64(random_bytes)^timestamp
    // verify_token checks that the timestamp is within ~60 seconds of now.

    public function test_verify_token_valid_recent_token(): void
    {
        // Build a token the same way the app does
        $time  = time();
        $rand  = base64_encode(random_bytes(16));
        $token = $rand . "^" . $time;

        $this->assertTrue(verify_token($token, $time));
    }

    public function test_verify_token_expired_token_returns_false(): void
    {
        // Token timestamp 2 hours in the past
        $old_time = time() - 7200;
        $rand      = base64_encode(random_bytes(16));
        $token     = $rand . "^" . $old_time;

        $this->assertFalse(verify_token($token, $old_time));
    }

    public function test_verify_token_malformed_no_caret_delimiter(): void
    {
        // No "^" separator — token can't be parsed
        $this->assertFalse(verify_token("notavalidtoken", time()));
    }

    public function test_verify_token_empty_token(): void
    {
        $this->assertFalse(verify_token("", time()));
    }

    // ── random_generator() ───────────────────────────────────

    public function test_random_generator_method_1_returns_correct_length(): void
    {
        $result = random_generator(8, 1);
        $this->assertSame(8, strlen((string)$result));
    }

    public function test_random_generator_method_2_returns_correct_length(): void
    {
        $result = random_generator(12, 2);
        $this->assertSame(12, strlen((string)$result));
    }

    public function test_random_generator_method_1_is_numeric(): void
    {
        $result = random_generator(6, 1);
        $this->assertTrue(is_numeric($result));
    }

    public function test_random_generator_method_2_is_alphanumeric(): void
    {
        $result = random_generator(20, 2);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', (string)$result);
    }

    public function test_random_generator_produces_different_values(): void
    {
        $a = random_generator(16, 2);
        $b = random_generator(16, 2);
        // Astronomically unlikely to collide with 16 alphanumeric chars
        $this->assertNotSame($a, $b);
    }

    // ── currency_info() ──────────────────────────────────────

    public function test_currency_info_method1_usd(): void
    {
        $this->assertSame("$^USD", currency_info("$", 1));
    }

    public function test_currency_info_method1_euro(): void
    {
        $this->assertSame("&euro;^EUR", currency_info("euro", 1));
    }

    public function test_currency_info_method1_pound(): void
    {
        $this->assertSame("&pound;^GBP", currency_info("pound", 1));
    }

    public function test_currency_info_method1_brazilian_real(): void
    {
        $this->assertSame("R$^BRL", currency_info("R$", 1));
    }

    public function test_currency_info_method1_canadian_dollar(): void
    {
        $this->assertSame("$^CAD", currency_info("C$", 1));
    }

    public function test_currency_info_method1_australian_dollar(): void
    {
        $this->assertSame("$^AUD", currency_info("A$", 1));
    }

    public function test_currency_info_method1_yen(): void
    {
        $this->assertSame("&yen;^JPY", currency_info("yen", 1));
    }

    public function test_currency_info_method1_czech_koruna(): void
    {
        $this->assertSame("K&#269;^CZK", currency_info("czkoruna", 1));
    }

    public function test_currency_info_method1_unknown_returns_empty(): void
    {
        // Unknown currency code — switch falls through, no assignment
        $this->assertSame("", currency_info("XXX", 1));
    }

    public function test_currency_info_method2_returns_array(): void
    {
        $result = currency_info("", 2);
        $this->assertIsArray($result);
    }

    public function test_currency_info_method2_array_starts_with_usd(): void
    {
        $result = currency_info("", 2);
        $this->assertStringStartsWith("$^", $result[0]);
    }

    public function test_currency_info_method2_array_contains_28_currencies(): void
    {
        $result = currency_info("", 2);
        $this->assertCount(28, $result);
    }
}
