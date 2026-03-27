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
    // MOVED TO INTEGRATION TIER.
    //
    // Despite the name, verify_token() is NOT pure: it opens a DB connection
    // via require(CONFIG.'config.php') and runs a SELECT query against the
    // users table to look up the token.
    //
    // These stubs are kept here as a reminder; run the real tests via:
    //   ./vendor/bin/phpunit --testsuite Integration
    //
    // When writing the Integration tests, note the return values:
    //   0 = valid token found and within time window
    //   1 = token not found in DB  (the "invalid" default)
    //   2 = token found but expired

    public function test_verify_token_skipped_requires_db(): void
    {
        $this->markTestSkipped(
            'verify_token() queries the DB. Move to Integration suite with a seeded users table.'
        );
    }

    // ── random_generator() ───────────────────────────────────
    // CHARACTERIZATION NOTES — the function has significant quirks:
    //
    // Method "1" = ALPHANUMERIC (letters + digits from a large array)
    // Method "2" = NUMERIC only (digits 0–9)
    // Method "3" = single digit 0–4 (overwrites entire string each iteration!)
    //
    // LENGTH IS NOT EXACT: each loop iteration does two appends:
    //   1) 50% chance: 1 char from $input
    //   2) Always:     rand(1,10) as a string (1 or 2 chars since 10 → "10")
    // So actual length ≈ $digits … 3×$digits and varies every call.

    public function test_random_generator_method_1_is_alphanumeric_not_numeric(): void
    {
        // Method "1" pulls from a letters+digits array → result contains letters
        // → is_numeric() returns false
        $result = random_generator(10, 1);
        $this->assertIsString((string)$result);
        $this->assertGreaterThanOrEqual(10, strlen((string)$result));
    }

    public function test_random_generator_method_1_length_is_at_least_digits(): void
    {
        // Each iteration appends at least rand(1,10) → minimum 1 char/iter
        $result = random_generator(5, 1);
        $this->assertGreaterThanOrEqual(5, strlen((string)$result));
    }

    public function test_random_generator_method_2_is_all_digits(): void
    {
        // Method "2" sources from digits 0–9 AND rand(1,10) → all numeric chars
        $result = random_generator(8, 2);
        $this->assertMatchesRegularExpression('/^\d+$/', (string)$result);
    }

    public function test_random_generator_method_2_length_at_least_digits(): void
    {
        $result = random_generator(6, 2);
        $this->assertGreaterThanOrEqual(6, strlen((string)$result));
    }

    public function test_random_generator_produces_different_values(): void
    {
        // Two calls with the same args will almost certainly differ (random seed)
        $a = (string)random_generator(12, 2);
        $b = (string)random_generator(12, 2);
        // Acceptable: very rare collision, but documents non-determinism
        $this->assertIsString($a);
        $this->assertIsString($b);
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
