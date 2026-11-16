<?php 
/**
 * Get all ids from db of BJCP 2021 and BJCP 2025 styles.
 * Map ids from 2021 to 2025.
 * 2025 update was cider only.
 */

$styles_db_table = $prefix."styles";

$db_conn->where('brewStyleVersion', array('BJCP2021','BJCP2025'), 'in');
$db_conn->orderBy('brewStyleVersion', 'ASC');
$db_conn->orderBy('id', 'ASC');
$rows_style_ids = $db_conn->get($styles_db_table, null, "id, brewStyleGroup, brewStyleNum, brewStyleVersion");

$styles_2021 = array();
$styles_2025 = array();
$mapped_style_ids = array();

if (!isset($output)) $output = "";

foreach ($rows_style_ids as $row_style_ids) {

	$style_num = $row_style_ids['brewStyleGroup'].$row_style_ids['brewStyleNum'];

	if ($row_style_ids['brewStyleVersion'] == "BJCP2021") {
		$styles_2021[$style_num] = $row_style_ids['id'];
	}

	if ($row_style_ids['brewStyleVersion'] == "BJCP2025") {
		$styles_2025[$style_num] = $row_style_ids['id'];
	}

}

foreach ($styles_2021 as $key => $id_2021) {
	// Convert the 2021 style to 2025
	$mapped_style_to_2025 = bjcp_map_2021_2025($key,1,$prefix,1);
	$mapped_style_ids[$id_2021] = $mapped_style_to_2025;
}

/*
print_r($styles_2021);
echo "<br><br>";

print_r($styles_2021);
echo "<br><br>";

print_r($mapped_style_ids);
echo "<br><br>";
*/

/**
 * Update judge likes and dislikes from 2021 to analogous 2025 styles
 */

$db_conn->where("(brewerJudgeLikes IS NOT NULL OR brewerJudgeDislikes IS NOT NULL) OR (brewerJudgeLikes !='' OR brewerJudgeDislikes !='')");
$db_conn->orderBy('id', 'ASC');
$rows_judge_likes = $db_conn->get($prefix."brewer");
$totalRows_judge_likes = $db_conn->count;

// Resolve a BJCP2025 code to the id of the row it now lives at. Cider codes live
// in the new BJCP2025 rows; beer and mead are unchanged by the 2025 update and
// stay on their BJCP2021 rows.
$resolve_2025_id = function ($code) use ($styles_2025, $styles_2021) {
    if (array_key_exists($code, $styles_2025)) return $styles_2025[$code];
    if (array_key_exists($code, $styles_2021)) return $styles_2021[$code];
    return NULL;
};

// Convert a comma-separated list of style ids. Ids that map to a style are
// replaced with that style's current id; ids that aren't a mapped style (e.g.
// custom styles) are kept as-is. Returns array(new list, whether it changed).
$convert_style_id_list = function ($csv) use ($mapped_style_ids, $resolve_2025_id) {
    $new = array();
    foreach (explode(",", (string) $csv) as $value) {
        $value = trim($value);
        if ($value === '') continue;
        if (array_key_exists($value, $mapped_style_ids)) {
            $new_id = $resolve_2025_id($mapped_style_ids[$value]);
            if ($new_id !== NULL) $new[] = $new_id;
        }
        else $new[] = $value;
    }
    $new_csv = implode(",", array_unique($new));
    return array($new_csv, ($new_csv !== (string) $csv));
};

if ($totalRows_judge_likes > 0) {

    foreach ($rows_judge_likes as $row_judge_likes) {

        list($likes_new, $likes_changed) = $convert_style_id_list($row_judge_likes['brewerJudgeLikes']);
        list($dislikes_new, $dislikes_changed) = $convert_style_id_list($row_judge_likes['brewerJudgeDislikes']);

        if (($likes_changed) || ($dislikes_changed)) {

            $update_table = $prefix."brewer";
            $data = array(
                'brewerJudgeLikes' => $likes_new,
                'brewerJudgeDislikes' => $dislikes_new
            );
            $db_conn->where ('id', $row_judge_likes['id']);
            if ($db_conn->update ($update_table, $data)) $output .= "<li>Judge likes/dislikes updated to BJCP 2025 for ".$row_judge_likes['brewerLastName'].", ".$row_judge_likes['brewerFirstName']."</li>";
            else $output .= "<li>Judge likes/dislikes NOT updated to BJCP 2025 for ".$row_judge_likes['brewerLastName'].", ".$row_judge_likes['brewerFirstName'].". Error: ".$db_conn->getLastError()."</li>";

        }

    }

} // end if ($totalRows_judge_likes > 0)

/**
 * Update defined 2021 styles for any table to 2025
 */

$db_conn->orderBy('id', 'ASC');
$rows_tables = $db_conn->get($prefix."judging_tables");
$totalRows_tables = $db_conn->count;

if ($totalRows_tables > 0) {

    foreach ($rows_tables as $row_tables) {

        if (!empty($row_tables['tableStyles'])) {

            list($table_styles_new, $table_styles_changed) = $convert_style_id_list($row_tables['tableStyles']);

            if (($table_styles_changed) && (!empty($table_styles_new))) {

                $update_table = $prefix."judging_tables";
                $data = array('tableStyles' => $table_styles_new);
                $db_conn->where ('id', $row_tables['id']);
                if ($db_conn->update ($update_table, $data)) $output .= "<li>Table styles updated to BJCP 2025 for ".$row_tables['tableName']."</li>";
                else $output .= "<li>Table styles NOT updated to BJCP 2025 for ".$row_tables['tableName'].". Error: ".$db_conn->getLastError()."</li>";

            }

        }

    }

} // end if ($totalRows_tables > 0)

/**
 * Update any 2025 styles in the styles table as active if
 * 2021 counterpart was active as well.
 */

$db_conn->where('brewStyleVersion', 'BJCP2021');
$db_conn->where('brewStyleType', '2');
$db_conn->where('brewStyleActive', 'Y');
$rows_styles_active = $db_conn->get($styles_db_table);
$totalRows_styles_active = $db_conn->count;

// Deselect only the BJCP2025 cider styles that are an actual mapping TARGET of
// a BJCP2021 code, then re-activate the ones whose 2021 counterpart was active,
// below. A 2025 style that nothing maps to (new in 2025, e.g. Heirloom Cider)
// is never touched and stays active as update/styles_bjcp_2025_update.php
// seeded it. Old rows are deactivated but never deleted so archived
// competitions that ran under BJCP2021 still resolve their cider style names.
$mapped_target_ids = array();
foreach (array_unique(array_values($mapped_style_ids)) as $mapped_target_code) {
    if (array_key_exists($mapped_target_code, $styles_2025)) $mapped_target_ids[] = $styles_2025[$mapped_target_code];
}

if (!empty($mapped_target_ids)) {
    $update_table = $prefix."styles";
    $data = array('brewStyleActive' => 'N');
    $db_conn->where ('id', $mapped_target_ids, 'IN');
    $db_conn->update ($update_table, $data);
}

if ($totalRows_styles_active > 0) {

    foreach ($rows_styles_active as $row_styles_active) {

        if (array_key_exists($row_styles_active['id'], $mapped_style_ids)) {

            $new_style_num = $mapped_style_ids[$row_styles_active['id']];

            if (array_key_exists($new_style_num, $styles_2025)) {

                $update_table = $prefix."styles";
                $data = array('brewStyleActive' => 'Y');
                $db_conn->where ('id', $styles_2025[$new_style_num]);
                $db_conn->update ($update_table, $data);

            }

        }

    }

    // Deactivate the old cider rows now that their BJCP2025 counterparts are active.
    $update_table = $prefix."styles";
    $data = array('brewStyleActive' => 'N');
    $db_conn->where ('brewStyleVersion', 'BJCP2021');
    $db_conn->where ('brewStyleType', '2');
    $db_conn->update ($update_table, $data);

} // end if ($totalRows_styles_active > 0)

/**
 * Update any entries in the brewing table to analogous 2025 styles
 */

$db_conn->orderBy('brewCategorySort', 'ASC');
$db_conn->orderBy('brewSubCategory', 'ASC');
$rows_brews = $db_conn->get($prefix."brewing", null, "id,brewName,brewCategory,brewCategorySort,brewSubCategory,brewStyle");
$totalRows_brews = $db_conn->count;

$current_active = array();

if ($totalRows_brews > 0) {

	foreach ($rows_brews as $row_brews) {

		$style = $row_brews['brewCategorySort'].$row_brews['brewSubCategory'];
        $sql = "";
        $sql .= bjcp_map_2021_2025($style,0,$prefix,$row_brews['id']);
        if (!empty($sql)) {
            $current_active[] = bjcp_map_2021_2025($style,2,$prefix,$row_brews['id']);
            $result = $db_conn->rawQuery($sql);
        }

	}

} // end if ($totalRows_brews > 0)

// Activate all styles that have been converted.
// Failsafe just in case comp converts during entry window.

if (!empty($current_active)) {

    $update_table = $prefix."styles";

    foreach($current_active as $value) {

        $style_parts = explode("-", $value);
        $data = array('brewStyleActive' => 'Y');
        $db_conn->where ('brewStyleGroup', $style_parts[0]);
        $db_conn->where ('brewStyleNum', $style_parts[1]);
        // Limit to BJCP2025: group/num codes are reused across style sets (BJCP2021,
        // AABC, NWCiderCup...), so matching on group/num alone re-activated old
        // BJCP2021 rows and switched on unrelated styles in other style sets.
        $db_conn->where ('brewStyleVersion', 'BJCP2025');
        $db_conn->update ($update_table, $data);

    }

}

$output .= "<ul>";

// Update all custom styles
$update_table = $prefix."styles";
$data = array('brewStyleVersion' => 'BJCP2025');
$db_conn->where ('brewStyleOwn', NULL, 'IS');
$db_conn->orWhere ('brewStyleOwn', 'custom');
if ($db_conn->update ($update_table, $data)) $output .= "<li>Custom styles updated to BJCP 2025.</li>";
else $output .= "Custom styles NOT updated to BJCP 2025. <li>Error: ".$db_conn->getLastError()."</li>";

$update_table = $prefix."preferences";
$data = array('prefsStyleSet' => 'BJCP2025');
$db_conn->where ('id', 1);
if ($db_conn->update ($update_table, $data)) $output .= "<li>Preferences set to BJCP 2025.</li>";
else $output .= "<li>Preferences NOT set to BJCP 2025. Error: ".$db_conn->getLastError()."</li>";

$output .= "</ul>";

unset($_SESSION['prefs'.$prefix_session]);
?>