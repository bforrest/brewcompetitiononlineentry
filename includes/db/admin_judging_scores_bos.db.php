<?php
$query_style_type = "SELECT * FROM $style_types_db_table"; 
if (($action == "edit") && ($filter != "default")) $query_style_type .= " WHERE id='$filter'";
if (($action == "enter") && ($filter != "default")) $query_style_type .= " WHERE id='$filter'";
if (($go != "styles") && ($id !="default")) $query_style_type .= " WHERE id='$id'";
if ((($go == "judging_tables") || ($go == "judging_scores_bos")) && ($action == "default") && ($id == "default")) $query_style_type .= " WHERE styleTypeBOS='Y'";
$style_type = mysql_query($query_style_type, $brewing) or die(mysql_error());
$row_style_type = mysql_fetch_assoc($style_type);
$totalRows_style_type = mysql_num_rows($style_type); 

if (NHC) {
	// Place NHC SQL calls below
	
	
}
// end if (NHC)
else {
	if ($action == "enter") {
		$query_enter_bos = "SELECT * FROM $judging_scores_db_table";
		if ($row_style_type['styleTypeBOSMethod'] == "1") $query_enter_bos .= " WHERE scoreType='$filter' AND scorePlace='1'";
		if ($row_style_type['styleTypeBOSMethod'] == "2") $query_enter_bos .= " WHERE scoreType='$filter' AND (scorePlace='1' OR scorePlace='2')";
		if ($row_style_type['styleTypeBOSMethod'] == "3") $query_enter_bos .= " WHERE (scoreType='$filter' AND scorePlace='1') OR (scoreType='$filter' AND scorePlace='2') OR (scoreType='$filter' AND scorePlace='3')";
		//if ($_SESSION['jPrefsBOSMethodBeer'] == "4") $query_enter_bos .= " WHERE scoreType='B' AND scorePlace='1'";
		$query_enter_bos .= " ORDER BY scoreTable ASC";
		$enter_bos = mysql_query($query_enter_bos, $brewing) or die(mysql_error());
		$row_enter_bos = mysql_fetch_assoc($enter_bos);
		$totalRows_enter_bos = mysql_num_rows($enter_bos);
		//echo $query_enter_bos."<br>";
		
	}
	
	else {
	
		$query_bos = "SELECT * FROM $judging_scores_db_table";
		if ($style_type_info[1] == "1") $query_bos .= " WHERE scoreType='$type' AND scorePlace='1'";
		if ($style_type_info[1] == "2") $query_bos .= " WHERE scoreType='$type' AND (scorePlace='1' OR scorePlace='2')";
		if ($style_type_info[1] == "3") $query_bos .= " WHERE (scoreType='$type' AND scorePlace='1') OR (scoreType='$type' AND scorePlace='2') OR (scoreType='$type' AND scorePlace='3')";
		$query_bos .= " ORDER BY scoreTable ASC";
		$bos = mysql_query($query_bos, $brewing) or die(mysql_error());
		$row_bos = mysql_fetch_assoc($bos);
		$totalRows_bos = mysql_num_rows($bos);
		//echo $query_bos;
	}
}
?>