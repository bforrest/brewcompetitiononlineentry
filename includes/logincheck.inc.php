<?php
ob_start();

$section = "default";
if (isset($_GET['section'])) $section = sterilize($_GET['section']);

header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

$loginUsername = sterilize($_POST['loginUsername']);
$entered_password = sterilize($_POST['loginPassword']);
$location = $base_url."index.php?section=login";

if (NHC) $base_url = "../";
else $base_url = $base_url;

if (strlen($entered_password) > 72) {
	session_destroy();
	header(sprintf("Location: %s", $base_url."index.php?msg=11"));
	exit;
}

/**
 * ONLY for 1.3.0.0 release; evaluate for deletion in future releases
 * Has to do with the hashing of passwords introduced in 1.3.0.0
 */

if ($section == "update") {

	$loginUsername = strtolower($loginUsername);

	$stmt_login = mysqli_prepare($connection, sprintf("SELECT * FROM %s WHERE user_name = ?",$prefix."users")) or die("A database error occurred.");
	mysqli_stmt_bind_param($stmt_login, "s", $loginUsername);
	mysqli_stmt_execute($stmt_login);
	$login = mysqli_stmt_get_result($stmt_login);
	$row_login = mysqli_fetch_assoc($login);
	$totalRows_login = mysqli_num_rows($login);

	$stored_hash = $row_login['password'];

	$check = 0;

	if ($totalRows_login > 0) {
		$check = password_verify_legacy($entered_password, $stored_hash);
		if (($check == 1) && (password_needs_legacy_upgrade($stored_hash))) upgrade_legacy_password_hash($connection, $prefix."users", "id", $row_login['id'], $entered_password);
	}

	else $check = 0;

}

if ($section != "update") {

	$loginUsername = strtolower($loginUsername);

	$stmt_login = mysqli_prepare($connection, sprintf("SELECT * FROM %s WHERE user_name = ?", $prefix."users")) or die("A database error occurred.");
	mysqli_stmt_bind_param($stmt_login, "s", $loginUsername);
	mysqli_stmt_execute($stmt_login);
	$login = mysqli_stmt_get_result($stmt_login);
	$row_login = mysqli_fetch_assoc($login);
	$totalRows_login = mysqli_num_rows($login);

	$stored_hash = $row_login['password'];
	$check = 0;

	if ($totalRows_login > 0) {
		$check = password_verify_legacy($entered_password, $stored_hash);
		if (($check == 1) && (password_needs_legacy_upgrade($stored_hash))) upgrade_legacy_password_hash($connection, $prefix."users", "id", $row_login['id'], $entered_password);
	}

}

/**
 * If the username/password combo is valid, register a session, 
 * register a session cookie perform certain tasks and redirect
 */

if ($check == 1) {

	// Regenerate the session id on privilege elevation to prevent session
	// fixation (P1-SEC-006) - must run before any session data is trusted.
	session_regenerate_id(true);

	// Register the loginUsername but first update the db record to make sure the the user name is stored as all lowercase.
	$stmt_update = mysqli_prepare($connection, sprintf("UPDATE %s SET user_name=? WHERE id=?",$prefix."users")) or die("A database error occurred.");
	mysqli_stmt_bind_param($stmt_update, "si", $loginUsername, $row_login['id']);
	mysqli_stmt_execute($stmt_update);

	// Convert email address in the user's accociated record in the "brewer" table
	$stmt_update = mysqli_prepare($connection, sprintf("UPDATE %s SET brewerEmail=? WHERE uid=?",$prefix."brewer")) or die("A database error occurred.");
	mysqli_stmt_bind_param($stmt_update, "si", $loginUsername, $row_login['id']);
	mysqli_stmt_execute($stmt_update);
	
	// Register the session variable
	$_SESSION['loginUsername'] = $loginUsername;

	// Also register userLevel here, not just loginUsername (Task 10 fix).
	// Historically userLevel was left for includes/db/common.db.php's own
	// lazy hydration block to populate on the NEXT page load (it copies
	// every column of the user's row into $_SESSION, guarded by a
	// "$_SESSION['user_info'.$prefix_session] already set" flag) - safe
	// pre-Phase-2 because that hydration ran (via site/bootstrap.php) BEFORE
	// index.legacy.php's own inline admin/account-page checks, in the same
	// request. Now that AuthorizationMiddleware gates EVERY request
	// centrally, BEFORE any legacy page code (including that hydration
	// block) ever runs, the very first request after login was being denied
	// on session data that legacy code hadn't had a chance to populate yet -
	// permanently locking out every freshly-logged-in user, including the
	// seeded super-admin, since a denied request never reaches the page
	// that would have hydrated it. Setting userLevel here (row_login is
	// already the freshly-queried source of truth) closes that gap without
	// changing what common.db.php still does moments later on the next
	// legacy page render - it re-copies the same value along with every
	// other user column, unconditionally, unchanged.
	$_SESSION['userLevel'] = $row_login['userLevel'];

	// Rotate CSRF token on successful login
	csrf_token_generate(true);
	
	// Set the relocation variables
	if ($section == "update") $location = $base_url."update.php";
	else {
		if ($row_login['userLevel'] <= 1) $location = $base_url."index.php?section=admin";
		else $location = $base_url."index.php?section=list";
	}
	
}

/**
 * If the username/password combo is incorrect or not found, 
 * destroy the session and relocate to the login error page.
 */

else {
	$location = $base_url."index.php?msg=11";
	session_destroy();
	// Works with standard fail2ban apache-auth module to prevent Brute Force login attempts
	trigger_error('user authentication failure', E_USER_WARNING);
}

// Relocate
header(sprintf("Location: %s", $location, true));
exit;
?>
