<?php
/**
 * Security utility functions.
 * No database, session, or framework dependencies — safe to require in isolation.
 */

/**
 * Returns true only if $resolved_file lies inside $resolved_base.
 *
 * Both arguments must already be resolved with realpath() (or equivalent)
 * so that symlinks and ".." segments have been fully collapsed.
 *
 * @param string $resolved_file  Absolute, symlink-free path to the candidate file.
 * @param string $resolved_base  Absolute, symlink-free path to the permitted base directory.
 * @return bool
 */
function is_path_within_dir(string $resolved_file, string $resolved_base): bool {
    // Normalise: base must end with exactly one directory separator so that a
    // directory whose name is a prefix of another cannot match.
    // e.g. /var/www/docs must not match /var/www/docs_extra/evil.pdf
    $base = rtrim($resolved_base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return str_starts_with($resolved_file, $base);
}
