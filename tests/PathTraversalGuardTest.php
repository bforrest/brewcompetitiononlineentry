<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib/security.lib.php';

/**
 * Unit tests for is_path_within_dir().
 *
 * The function takes pre-resolved (realpath'd) strings, so no filesystem
 * access is required and tests run without any application bootstrapping.
 */
class PathTraversalGuardTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_valid_file_inside_base_is_accepted(): void
    {
        $this->assertTrue(
            is_path_within_dir('/var/www/docs/abc123.pdf', '/var/www/docs')
        );
    }

    public function test_valid_file_in_subdirectory_is_accepted(): void
    {
        $this->assertTrue(
            is_path_within_dir('/var/www/docs/2024/scoresheet.pdf', '/var/www/docs')
        );
    }

    public function test_base_with_trailing_separator_is_accepted(): void
    {
        // Callers may pass the base with or without a trailing slash.
        $this->assertTrue(
            is_path_within_dir('/var/www/docs/entry.pdf', '/var/www/docs/')
        );
    }

    // -------------------------------------------------------------------------
    // Path-traversal attacks
    // -------------------------------------------------------------------------

    public function test_traversal_above_base_is_rejected(): void
    {
        // Simulates realpath('/var/www/docs/../../etc/passwd') => '/etc/passwd'
        $this->assertFalse(
            is_path_within_dir('/etc/passwd', '/var/www/docs')
        );
    }

    public function test_traversal_to_sibling_directory_is_rejected(): void
    {
        // realpath('/var/www/docs/../config/db.php') => '/var/www/config/db.php'
        $this->assertFalse(
            is_path_within_dir('/var/www/config/db.php', '/var/www/docs')
        );
    }

    public function test_traversal_to_parent_directory_is_rejected(): void
    {
        $this->assertFalse(
            is_path_within_dir('/var/www/docs', '/var/www/docs')
        );
        // The base directory itself is not a valid file target.
        // is_path_within_dir('/var/www/docs', '/var/www/docs') should be false
        // because the candidate equals the base (no filename component after separator).
    }

    // -------------------------------------------------------------------------
    // Prefix-collision edge case
    // -------------------------------------------------------------------------

    public function test_directory_name_prefix_match_is_rejected(): void
    {
        // /var/www/docs_evil must NOT match base /var/www/docs
        // Without the trailing-separator normalisation this would be a false positive.
        $this->assertFalse(
            is_path_within_dir('/var/www/docs_evil/attack.pdf', '/var/www/docs')
        );
    }

    public function test_directory_name_prefix_with_trailing_separator_is_rejected(): void
    {
        $this->assertFalse(
            is_path_within_dir('/var/www/docs_evil/attack.pdf', '/var/www/docs/')
        );
    }

    // -------------------------------------------------------------------------
    // Absolute path outside base entirely
    // -------------------------------------------------------------------------

    public function test_completely_unrelated_path_is_rejected(): void
    {
        $this->assertFalse(
            is_path_within_dir('/tmp/malicious.pdf', '/var/www/docs')
        );
    }

    public function test_root_level_path_is_rejected(): void
    {
        $this->assertFalse(
            is_path_within_dir('/etc/shadow', '/var/www/docs')
        );
    }
}
