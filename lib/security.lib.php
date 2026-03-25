<?php
/**
 * Module:      security.lib.php
 * Description: Security utility functions.
 */

/**
 * Execute a mysqli query and terminate safely on failure.
 *
 * Replaces the pattern:
 *   mysqli_query($connection, $sql) or die(mysqli_error($connection));
 *
 * On failure, the DB error is written to the error log (never shown to the
 * browser) and execution stops with a generic 500 response.
 *
 * @param  mysqli         $connection  Active database connection.
 * @param  string         $sql         SQL string to execute.
 * @return mysqli_result|bool          Query result on success.
 */
function db_query(mysqli $connection, string $sql): mysqli_result|bool
{
    $result = mysqli_query($connection, $sql);
    if ($result === false) {
        error_log('DB query error: ' . mysqli_error($connection));
        http_response_code(500);
        exit('A database error occurred.');
    }
    return $result;
}
