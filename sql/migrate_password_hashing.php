<?php
/**
 * P1-1 Password Hashing Migration Script
 *
 * Identifies users whose stored password hash was created using the legacy
 * MD5-wrapped phpass scheme ($2a$ prefix) and sends them a password-reset
 * email so they can establish a new bcrypt-only hash ($2y$ prefix).
 *
 * Run this script ONCE from the command line (or via a protected admin URL)
 * immediately before — or at the same time as — deploying the P1-1 code change.
 *
 * Usage (CLI):
 *   php sql/migrate_password_hashing.php
 *
 * After all users have reset their passwords the legacy md5() fallback blocks
 * in logincheck.inc.php and process_users.inc.php can be safely removed.
 */

define('BCOEM_MIGRATION', true);

// Bootstrap the application so $connection, $prefix, session prefs, etc. are available.
// Adjust the path if this script is run from a different working directory.
$base_path = dirname(__DIR__) . '/';
require $base_path . 'paths.php';
require $base_path . 'site/config.php';
require $base_path . 'site/bootstrap.php';
require $base_path . 'includes/db_tables.inc.php';

use PHPMailer\PHPMailer\PHPMailer;
require LIB . 'email.lib.php';

// --- Find all users with legacy MD5-wrapped bcrypt hashes ($2a$ prefix) ---

$query = sprintf(
    "SELECT id, user_name FROM %s WHERE password LIKE '\$2a\$%%'",
    $prefix . 'users'
);
$result = mysqli_query($connection, $query) or die(mysqli_error($connection));

$total   = mysqli_num_rows($result);
$flagged = 0;
$errors  = 0;

echo "Found {$total} user(s) with legacy MD5-wrapped password hashes.\n\n";

if ($total === 0) {
    echo "Nothing to do. Exiting.\n";
    exit(0);
}

$url      = str_replace('www.', '', $_SERVER['SERVER_NAME'] ?? gethostname());
$from_email = (!isset($mail_default_from) || trim($mail_default_from) === '')
    ? 'noreply@' . $url
    : $mail_default_from;

while ($row = mysqli_fetch_assoc($result)) {
    $uid       = (int) $row['id'];
    $user_name = $row['user_name'];

    // Generate a cryptographically-secure reset token.
    $token     = bin2hex(random_bytes(16));
    $token_time = time();

    // Store the token in the users table.
    $update = sprintf(
        "UPDATE %s SET userToken='%s', userTokenTime='%s' WHERE id='%d'",
        $prefix . 'users',
        mysqli_real_escape_string($connection, $token),
        $token_time,
        $uid
    );
    if (!mysqli_query($connection, $update)) {
        echo "ERROR: Could not set token for user {$user_name}: " . mysqli_error($connection) . "\n";
        $errors++;
        continue;
    }

    // Build the reset URL.
    $reset_url = $base_url . 'index.php?section=login&go=password&action=reset-password&token=' . $token;

    // Send the reset email.
    $contest_name = isset($_SESSION['contestName']) ? $_SESSION['contestName'] : 'Brew Competition';

    $subject = $contest_name . ': Action Required — Please Reset Your Password';
    $message = "<html><body>\n"
        . "<p>Hello,</p>\n"
        . "<p>We have upgraded our password security. As part of this upgrade your password "
        . "must be reset before you can log in again.</p>\n"
        . "<p>Please click the link below to choose a new password:</p>\n"
        . "<p><a href=\"{$reset_url}\">{$reset_url}</a></p>\n"
        . "<p>If you did not expect this email, please contact the competition administrator.</p>\n"
        . "<p>{$contest_name}</p>\n"
        . "</body></html>";

    $sent = false;
    if (isset($mail_use_smtp) && $mail_use_smtp) {
        try {
            $mail = new PHPMailer(true);
            $mail->CharSet  = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->addAddress($user_name);
            $mail->setFrom($from_email, $contest_name);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $message;
            sendPHPMailerMessage($mail);
            $sent = true;
        } catch (Exception $e) {
            echo "ERROR: Could not send email to {$user_name}: " . $e->getMessage() . "\n";
            $errors++;
        }
    } else {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=utf-8\r\n";
        $headers .= "From: {$contest_name} <{$from_email}>\r\n";
        $sent = mail($user_name, $subject, $message, $headers);
        if (!$sent) {
            echo "ERROR: mail() failed for {$user_name}\n";
            $errors++;
        }
    }

    if ($sent) {
        echo "Flagged + emailed: {$user_name}\n";
        $flagged++;
    }
}

echo "\nDone. {$flagged}/{$total} user(s) flagged and emailed. {$errors} error(s).\n";
