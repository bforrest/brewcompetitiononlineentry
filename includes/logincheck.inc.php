<?php
ob_start();
require (CLASSES.'phpass/PasswordHash.php');

$section = "default";
if (isset($_GET['section'])) $section = sterilize($_GET['section']);

header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); 
header('Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT'); 
header('Cache-Control: no-store, no-cache, must-revalidate'); 
header('Cache-Control: post-check=0, pre-check=0', false); 
header('Pragma: no-cache'); 

$hasher = new PasswordHash(8, false);
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

$entered_password = md5($entered_password);

$user_id = null;
$stored_hash = null;
$totalRows_login = 0;

if ($stmt_login = mysqli_prepare($connection, "SELECT id, password, userLevel FROM {$prefix}users WHERE user_name = ? LIMIT 1")) {
	mysqli_stmt_bind_param($stmt_login, 's', $loginUsername);
	mysqli_stmt_execute($stmt_login);
	mysqli_stmt_bind_result($stmt_login, $user_id, $stored_hash, $user_level);
	if (mysqli_stmt_fetch($stmt_login)) {
		$totalRows_login = 1;
		$row_login = array(
			'id' => $user_id,
			'password' => $stored_hash,
			'userLevel' => $user_level
		);
	}
	mysqli_stmt_close($stmt_login);
}

/**
 * ONLY for 1.3.0.0 release; evaluate for deletion in future releases
 * Has to do with the hashing of passwords introduced in 1.3.0.0
 */

if ($section == "update") {
	
	$loginUsername = strtolower($loginUsername);	
	
	$check = 0;
	
	if ($totalRows_login > 0) {
		$check = $hasher->CheckPassword($entered_password, $stored_hash);
		$check = 1;
	}
	
	else $check = 0;
	
}

if ($section != "update") {
	
	$loginUsername = strtolower($loginUsername);	
	$check = 0;
	
	if ($totalRows_login > 0) $check = $hasher->CheckPassword($entered_password, $stored_hash);

}

/**
 * If the username/password combo is valid, register a session, 
 * register a session cookie perform certain tasks and redirect
 */

if ($check == 1) {
	
	// Register the loginUsername but first update the db record to make sure the the user name is stored as all lowercase.
	$updateSQL = "UPDATE {$prefix}users SET user_name=? WHERE id=?";
	if ($stmt_update_user = mysqli_prepare($connection, $updateSQL)) {
		mysqli_stmt_bind_param($stmt_update_user, 'si', $loginUsername, $row_login['id']);
		mysqli_stmt_execute($stmt_update_user);
		mysqli_stmt_close($stmt_update_user);
	}

	// Convert email address in the user's accociated record in the "brewer" table
	$updateSQL = "UPDATE {$prefix}brewer SET brewerEmail=? WHERE uid=?";
	if ($stmt_update_brewer = mysqli_prepare($connection, $updateSQL)) {
		mysqli_stmt_bind_param($stmt_update_brewer, 'si', $loginUsername, $row_login['id']);
		mysqli_stmt_execute($stmt_update_brewer);
		mysqli_stmt_close($stmt_update_brewer);
	}
	
	// Rotate the session ID to invalidate any pre-login session (session fixation defence)
	session_regenerate_id(true);

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