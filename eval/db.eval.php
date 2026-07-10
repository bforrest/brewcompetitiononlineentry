<?php
/**
 * Code is executed when rendering a scoresheet.
 * Not used for any other purposes.
 */

$styles_db_table = $prefix."styles";
$query_eval = sprintf("SELECT * FROM %s WHERE id=%s", $dbTable, $id);
$eval = mysqli_query($connection,$query_eval) or die (mysqli_error($connection));
$row_eval = mysqli_fetch_assoc($eval);

/*
$db_conn->where ("id", $id);
$row_eval = $db_conn->getOne ($dbTable);
*/

$query_judge = sprintf("SELECT * FROM %s WHERE uid=%s", $prefix."brewer".$archive_suffix, $row_eval['evalJudgeInfo']);
$judge = mysqli_query($connection,$query_judge) or die (mysqli_error($connection));
$row_judge = mysqli_fetch_assoc($judge);

/*
$db_conn->where ("uid", $row_eval['evalJudgeInfo']);
$row_judge = $db_conn->getOne ($prefix."brewer".$archive_suffix);
*/

if (empty($row_eval['evalStyle'])) {
	
	$query_brewing = sprintf("SELECT * FROM %s WHERE id=%s", $prefix."brewing".$archive_suffix, $row_eval['eid']);
	$brewing = mysqli_query($connection,$query_brewing) or die (mysqli_error($connection));
	$row_brewing = mysqli_fetch_assoc($brewing);

	/*
	$db_conn->where ("id", $row_eval['eid']);
	$row_brewing = $db_conn->getOne ($prefix."brewing".$archive_suffix);
	*/

	if ($_SESSION['prefsStyleSet'] == "BJCP2025") {
		$style_set_in_query = "(brewStyleVersion='BJCP2025' OR brewStyleVersion='BJCP2021')";
	}

	else $style_set_in_query = "brewStyleVersion='".$_SESSION['prefsStyleSet']."'";

	$query_style = sprintf("SELECT id,brewStyle,brewStyleGroup,brewStyleNum,brewStyleType FROM %s WHERE brewStyle='%s' AND brewStyleGroup='%s' AND brewStyleNum='%s' AND %s", $styles_db_table, $row_brewing['brewStyle'], $row_brewing['brewCategorySort'], $row_brewing['brewSubCategory'], $style_set_in_query);
	$style = mysqli_query($connection,$query_style) or die (mysqli_error($connection));
	$row_style = mysqli_fetch_assoc($style);

	/*
	$cols = array("id","brewStyle","brewStyleGroup","brewStyleNum","brewStyleType");
	$db_conn->where ("brewStyle", $row_brewing['brewStyle']);
	$db_conn->where ("brewStyleGroup", $row_brewing['brewCategorySort']);
	$db_conn->where ("brewStyleNum", $row_brewing['brewSubCategory']);
	if ($_SESSION['prefsStyleSet'] == "BJCP2025") $db_conn->where ("brewStyleVersion = ? OR brewStyleVersion = ?", array("BJCP2025","BJCP2021"));
	else $db_conn->where ("brewStyleVersion", $_SESSION['prefsStyleSet']);
	$row_style = $db_conn->getOne ($styles_db_table);
	*/

}

else {
	
	
	$query_style = sprintf("SELECT brewStyle,brewStyleGroup,brewStyleNum,brewStyleType FROM %s WHERE id=%s", $styles_db_table, $row_eval['evalStyle']);
	$style = mysqli_query($connection,$query_style) or die (mysqli_error($connection));
	$row_style = mysqli_fetch_assoc($style);
	
	/*
	$cols = array("id","brewStyle","brewStyleGroup","brewStyleNum","brewStyleType");
	$db_conn->where ("id", $row_eval['evalStyle']);
	$row_style = $db_conn->getOne ($styles_db_table);
	*/

}

$query_entry_info = sprintf("SELECT * FROM %s WHERE id=%s",$prefix."brewing".$archive_suffix, $row_eval['eid']);
$entry_info = mysqli_query($connection,$query_entry_info) or die (mysqli_error($connection));
$row_entry_info = mysqli_fetch_assoc($entry_info);

/*
$db_conn->join($prefix."brewing".$archive_suffix." a", "b.uid=a.brewBrewerID", "LEFT");
$db_conn->where ("a.id", $row_eval['eid']);
$row_entry_info = $db_conn->get ($prefix."brewer".$archive_suffix." b");
*/
?>