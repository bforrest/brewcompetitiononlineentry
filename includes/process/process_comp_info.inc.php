<?php 
/*
 * Module:      process_comp_info.inc.php
 * Description: This module does all the heavy lifting for adding/editing information in the 
 *              "contest_info" table.
 */
if (((isset($_SESSION['loginUsername'])) && ($_SESSION['userLevel'] == 0)) || ($section == "setup")) { 
	// Constants
	if ($go == "default") {
		$contestRegistrationOpen = strtotime($_POST['contestRegistrationOpen']);
		$contestRegistrationDeadline = strtotime($_POST['contestRegistrationDeadline']);
		$contestEntryOpen = strtotime($_POST['contestEntryOpen']);
		$contestEntryDeadline = strtotime($_POST['contestEntryDeadline']);
		$contestJudgeOpen = strtotime($_POST['contestJudgeOpen']);
		$contestJudgeDeadline = strtotime($_POST['contestJudgeDeadline']);
		$contestAwardsLocDate = strtotime($_POST['contestAwardsLocDate']);
		$contestShippingOpen = strtotime($_POST['contestShippingOpen']);
		$contestShippingDeadline = strtotime($_POST['contestShippingDeadline']);
		$contestDropoffOpen = strtotime($_POST['contestDropoffOpen']);
		$contestDropoffDeadline = strtotime($_POST['contestDropoffDeadline']);
		$contestHostWebsite = check_http($_POST['contestHostWebsite']);
	}
	
	//echo $contestRegistrationOpen."<br>"; echo $contestRegistrationDeadline."<br>"; echo $contestEntryOpen ."<br>"; echo $contestEntryDeadline."<br>"; echo $judgingDate."<br>"; 
	//echo "<br>".$tz; echo "<br>".$timezone_offset; echo "<br>".$_SESSION['prefsTimeZone'];
	if (NHC) {
		// Place NHC SQL calls below
		
		
	}
	// end if (NHC)
	
	else {
		
	
	// --------------------------------------- Adding (SETUP ONLY) ----------------------------------------
	
	if ($action == "add") {
		if (($_POST['contestEntryFee2'] == "") || ($_POST['contestEntryFeeDiscountNum'] == "")) $contestEntryFeeDiscount = "N"; 
		if (($_POST['contestEntryFee2'] != "") && ($_POST['contestEntryFeeDiscountNum'] != "")) $contestEntryFeeDiscount = "Y"; 
		
		if (isset($_POST['contestCheckInPassword'])) {
			require(CLASSES.'phpass/PasswordHash.php');
			$hasher = new PasswordHash(8, false);
			$password = md5($_POST['contestCheckInPassword']);
			$hash = $hasher->HashPassword($password);
		}
		
		$insertSQL = sprintf("INSERT INTO $contest_info_db_table (
		contestName,
		contestID,
		contestHost, 
		contestHostWebsite, 
		contestHostLocation,
		
		contestRegistrationOpen,
		contestRegistrationDeadline, 
		contestEntryOpen,
		contestEntryDeadline, 
		contestJudgeOpen,
		
		contestJudgeDeadline,
		contestRules,
		contestAwardsLocation,
		
		contestEntryFee,
		contestBottles, 
		contestShippingAddress, 
		contestShippingName, 
		contestAwards,
		contestDropoffOpen,
		contestDropoffDeadline,
		contestEntryCap,
		contestAwardsLocName,
		contestAwardsLocTime,
		
		contestEntryFee2,
		contestEntryFeeDiscount,
		contestEntryFeeDiscountNum,
		contestLogo,
		contestBOSAward,
		
		contestEntryFeePassword,
		contestEntryFeePasswordNum,
		contestCircuit,
		contestVolunteers,
		contestShippingOpen,
		contestShippingDeadline,
		contestCheckInPassword,
		id
		) 
		VALUES 
		(
		%s, %s, %s, %s, %s, 
		%s, %s, %s, %s, %s,
		%s, %s, %s, %s, %s, 
		%s, %s, %s, %s, %s, 
		%s, %s, %s, %s, %s,
		%s, %s, %s, %s, %s,
		%s, %s, %s, %s, %s,
		%s)",
							   GetSQLValueString($_POST['contestName'], "text"),
							   GetSQLValueString($_POST['contestID'], "text"),
							   GetSQLValueString($_POST['contestHost'], "text"),
							   GetSQLValueString($contestHostWebsite, "text"),
							   GetSQLValueString($_POST['contestHostLocation'], "text"),
							   GetSQLValueString($contestRegistrationOpen, "text"),
							   GetSQLValueString($contestRegistrationDeadline, "text"),
							   GetSQLValueString($contestEntryOpen, "text"),
							   GetSQLValueString($contestEntryDeadline, "text"),
							   GetSQLValueString($contestJudgeOpen, "text"),
							   GetSQLValueString($contestJudgeDeadline, "text"),
							   GetSQLValueString($_POST['contestRules'], "text"),
							   GetSQLValueString($_POST['contestAwardsLocation'], "text"),
							   GetSQLValueString($_POST['contestEntryFee'], "text"),
							   GetSQLValueString($_POST['contestBottles'], "text"),
							   GetSQLValueString($_POST['contestShippingAddress'], "text"),
							   GetSQLValueString($_POST['contestShippingName'], "text"),
							   GetSQLValueString($_POST['contestAwards'], "text"),
							   GetSQLValueString($contestDropoffOpen, "text"),
							   GetSQLValueString($contestDropoffDeadline, "text"),
							   GetSQLValueString($_POST['contestEntryCap'], "text"),
							   GetSQLValueString($_POST['contestAwardsLocName'], "text"),
							   GetSQLValueString($contestAwardsLocDate, "text"),
							   GetSQLValueString($_POST['contestEntryFee2'], "text"),
							   GetSQLValueString($contestEntryFeeDiscount, "text"),
							   GetSQLValueString($_POST['contestEntryFeeDiscountNum'], "text"),
							   GetSQLValueString($_POST['contestLogo'], "text"),
							   GetSQLValueString($_POST['contestBOSAward'], "text"),
							   GetSQLValueString($_POST['contestEntryFeePassword'], "text"),
							   GetSQLValueString($_POST['contestEntryFeePasswordNum'], "text"),
							   GetSQLValueString($_POST['contestCircuit'], "text"),
							   GetSQLValueString($_POST['contestVolunteers'], "text"),
							   GetSQLValueString($contestShippingOpen, "text"),
							   GetSQLValueString($contestShippingDeadline, "text"),
							   GetSQLValueString($hash, "text"),
							   GetSQLValueString($id, "int"));
		
		  	mysqli_real_escape_string($connection,$insertSQL);
			$result = mysqli_query($connection,$insertSQL) or die (mysqli_error($connection));
		  	//echo $insertSQL."<br>";
			
		  	$insertSQL = sprintf("INSERT INTO $contacts_db_table (
			contactFirstName, 
			contactLastName, 
			contactPosition, 
			contactEmail
			) 
			VALUES 
			(%s, %s, %s, %s)",
							   GetSQLValueString($_POST['contactFirstName'], "text"),
							   GetSQLValueString($_POST['contactLastName'], "text"),
							   GetSQLValueString($_POST['contactPosition'], "text"),
							   GetSQLValueString($_POST['contactEmail'], "text"));
							   
			mysqli_real_escape_string($connection,$insertSQL);
			$result = mysqli_query($connection,$insertSQL) or die (mysqli_error($connection));
			
			$insertGoTo = "../setup.php?section=step5";
			$pattern = array('\'', '"');
			$insertGoTo = str_replace($pattern, "", $insertGoTo); 
			header(sprintf("Location: %s", stripslashes($insertGoTo)));;
	}
	
	// --------------------------------------- Editing  ----------------------------------------
	if ($action == "edit") {
		
		
		
		
		
		
		if ($go == "qr") {
			
			if (isset($_POST['contestCheckInPassword'])) {
				require(CLASSES.'phpass/PasswordHash.php');
				$hasher = new PasswordHash(8, false);
				$password = md5($_POST['contestCheckInPassword']);
				$hash = $hasher->HashPassword($password);
			}
			
			else $hash = "";
			
			$updateSQL = sprintf("UPDATE $contest_info_db_table SET 	contestCheckInPassword=%s WHERE id=%s", GetSQLValueString($hash, "text"), GetSQLValueString($id, "int"));
			
		} 
		
		else {
			// Empty the contest_info_general session variable
			// Will trigger the session to reset the variables in common.db.php upon reload after redirect
			session_name($prefix_session);
			session_start();
			unset($_SESSION['contest_info_general'.$prefix_session]);
			
			if ((empty($_POST['contestEntryFee2'])) || (empty($_POST['contestEntryFeeDiscountNum']))) $contestEntryFeeDiscount = "N"; 
			if ((!empty($_POST['contestEntryFee2'])) && (!empty($_POST['contestEntryFeeDiscountNum']))) $contestEntryFeeDiscount = "Y"; 
		
			$updateSQL = sprintf("UPDATE $contest_info_db_table SET 
			contestName=%s,
			contestID=%s,
			contestHost=%s, 
			contestHostWebsite=%s, 
			contestHostLocation=%s,
			contestRegistrationOpen=%s, 
			contestRegistrationDeadline=%s, 
			contestEntryOpen=%s,
			contestEntryDeadline=%s, 
			contestJudgeOpen=%s,
			contestJudgeDeadline=%s, 
			contestRules=%s, 
			contestAwardsLocation=%s,
			
			contestEntryFee=%s, 
			contestBottles=%s, 
			contestShippingAddress=%s, 
			contestShippingName=%s, 
			
			contestAwards=%s,
			contestDropoffOpen=%s,
			contestDropoffDeadline=%s,
			contestEntryCap=%s,
			contestAwardsLocName=%s,
			
			contestAwardsLocTime=%s,
			contestEntryFee2=%s,
			contestEntryFeeDiscount=%s,
			contestEntryFeeDiscountNum=%s,
			contestLogo=%s,
			contestBOSAward=%s,
			contestEntryFeePassword=%s,
			contestEntryFeePasswordNum=%s,
			contestCircuit=%s,
			contestVolunteers=%s,
			contestShippingOpen=%s,
			contestShippingDeadline=%s
			WHERE id=%s",
								   GetSQLValueString($_POST['contestName'], "text"),
								   GetSQLValueString($_POST['contestID'], "text"),
								   GetSQLValueString($_POST['contestHost'], "text"),
								   GetSQLValueString($contestHostWebsite, "text"),
								   GetSQLValueString($_POST['contestHostLocation'], "text"),
								   GetSQLValueString($contestRegistrationOpen, "text"),
								   GetSQLValueString($contestRegistrationDeadline, "text"),
								   GetSQLValueString($contestEntryOpen, "text"),
								   GetSQLValueString($contestEntryDeadline, "text"),
								   GetSQLValueString($contestJudgeOpen, "text"),
								   GetSQLValueString($contestJudgeDeadline, "text"),
								   GetSQLValueString($_POST['contestRules'], "text"),
								   GetSQLValueString($_POST['contestAwardsLocation'], "text"),
								   GetSQLValueString($_POST['contestEntryFee'], "text"),
								   GetSQLValueString($_POST['contestBottles'], "text"),
								   GetSQLValueString($_POST['contestShippingAddress'], "text"),
								   GetSQLValueString($_POST['contestShippingName'], "text"),
								   GetSQLValueString($_POST['contestAwards'], "text"),
								   GetSQLValueString($contestDropoffOpen, "text"),
								   GetSQLValueString($contestDropoffDeadline, "text"),
								   GetSQLValueString($_POST['contestEntryCap'], "text"),
								   GetSQLValueString($_POST['contestAwardsLocName'], "text"),
								   GetSQLValueString($contestAwardsLocDate, "text"),
								   GetSQLValueString($_POST['contestEntryFee2'], "text"),
								   GetSQLValueString($contestEntryFeeDiscount, "text"),
								   GetSQLValueString($_POST['contestEntryFeeDiscountNum'], "text"),
								   GetSQLValueString($_POST['contestLogo'], "text"),
								   GetSQLValueString($_POST['contestBOSAward'], "text"),
								   GetSQLValueString($_POST['contestEntryFeePassword'], "text"),
								   GetSQLValueString($_POST['contestEntryFeePasswordNum'], "text"),
								   GetSQLValueString($_POST['contestCircuit'], "text"),
								   GetSQLValueString($_POST['contestVolunteers'], "text"),
								   GetSQLValueString($contestShippingOpen, "text"),
								   GetSQLValueString($contestShippingDeadline, "text"),
								   GetSQLValueString($id, "int"));
			
			//echo $updateSQL;
		
		}
		
		mysqli_real_escape_string($connection,$updateSQL);
		$result = mysqli_query($connection,$updateSQL) or die (mysqli_error($connection));
		
		$pattern = array('\'', '"');
		$updateGoTo = str_replace($pattern, "", $updateGoTo); 
		header(sprintf("Location: %s", stripslashes($updateGoTo)));
	
		}
	
	} // end else
} else echo "<p>Not available.</p>";
?>