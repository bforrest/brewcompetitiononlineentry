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

$loginUsername = strtolower($loginUsername);

$query_login = sprintf("SELECT * FROM %s WHERE user_name = '%s'", $prefix."users", $loginUsername);
$login = mysqli_query($connection, $query_login) or die (mysqli_error($connection));
$row_login = mysqli_fetch_assoc($login);
$totalRows_login = mysqli_num_rows($login);

$stored_hash = $row_login['password'];
$check = 0;

if ($totalRows_login > 0) {
	if (password_verify($entered_password, $stored_hash)) {
		$check = 1;
	} elseif (strpos($stored_hash, '$2a$') === 0 && password_verify(md5($entered_password), $stored_hash)) {
		// Legacy MD5-wrapped phpass bcrypt hash — transparently rehash on successful login.
		// Remove this branch once all users have logged in post-migration (P1-1).
		$new_hash = password_hash($entered_password, PASSWORD_BCRYPT);
		mysqli_query($connection, sprintf("UPDATE %s SET password='%s' WHERE user_name='%s'", $prefix."users", mysqli_real_escape_string($connection, $new_hash), $loginUsername));
		$check = 1;
	}
}

/**
 * If the username/password combo is valid, register a session, 
 * register a session cookie perform certain tasks and redirect
 */

if ($check == 1) {
	
	// Register the loginUsername but first update the db record to make sure the the user name is stored as all lowercase.
	$updateSQL = sprintf("UPDATE %s SET user_name='%s' WHERE id='%s'",$prefix."users",$loginUsername, $row_login['id']);
	mysqli_real_escape_string($connection,$updateSQL);
	$result = mysqli_query($connection,$updateSQL) or die (mysqli_error($connection));

	// Convert email address in the user's accociated record in the "brewer" table
	$updateSQL = sprintf("UPDATE %s SET brewerEmail='%s' WHERE uid='%s'",$prefix."brewer",$loginUsername, $row_login['id']);
	mysqli_real_escape_string($connection,$updateSQL);
	$result = mysqli_query($connection,$updateSQL) or die (mysqli_error($connection));
	
	// Register the session variable
	$_SESSION['loginUsername'] = $loginUsername;
	
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