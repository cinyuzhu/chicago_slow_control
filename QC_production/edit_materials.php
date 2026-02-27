<?php
// Main table: assay_qc.assay_results
// Detail tables: assay_qc.assay_results_<id>  (recommended: stable & unique)

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

session_start();

$req_priv = "full";
include("db_login.php");   // legacy mysql_connect() etc.
include("page_setup.php"); // your header/menu
include("aux/assay_helpers.php"); // your header/menu
include("aux/table_navigation.php");

// Uncertainty: submitted value is in hidden input detail_uncertainty_ROWID; cell is display only
echo '<script>
function toggleUnc(cellId, val, rowId) {
  var cell = document.getElementById(cellId);
  var hidden = document.getElementById("detail_uncertainty_" + rowId);
  if (!cell || !hidden) return;
  var style = " width:100%; text-align:center; box-sizing:border-box; padding:2px 6px;";
  function esc(s) { return (s || "").replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;").replace(/>/g, "&gt;"); }
  if (val === "Upper Limit") {
    var inp = cell.querySelector("input[type=text]");
    if (inp && inp.value !== undefined) hidden.value = inp.value;
    cell.innerHTML = "<span style=\"color:#777;\">n/a</span>";
  } else {
    var v = hidden.value || "";
    cell.innerHTML = "<input type=\"text\" id=\"unc_display_" + rowId + "\" value=\"" + esc(v) + "\"" + style + " oninput=\"document.getElementById(\'detail_uncertainty_" + rowId + "\').value=this.value\">";
  }
}
</script>';

mysql_select_db('assay_qc');
$main_table = "assay_results";
include("edit_material_POST.php");
// --------------------
// RENDER PAGE
// --------------------
$qm = "
  SELECT `ID`,`Material`,`Type`,`Manufacturer`,`Description`,`Liaison`,`Date`,`Remarks`,`Docdb`
  FROM `$main_table`
  ORDER BY `ID` DESC
";
$rm = mysql_query($qm);
if (!$rm) die("Could not query assay_results: " . mysql_error() . "<BR>" . h($qm));

while ($row = mysql_fetch_assoc($rm)) {

    $assay_id = (int)$row['ID'];
    $material = $row['Material'];
    $detail_table = detail_table_name_from_row($assay_id, $material);
    ensure_detail_table($detail_table);

    echo '<br>';
    echo '<table border="1" cellpadding="4" width="100%">';
    echo '<tr style="background:#eee;"><th align="left">' . h($material) . '</th></tr>';

    echo '<tr><td>';  // ONE big cell wrapping everything

    /***********************
     * 1) UPDATE ASSAY FORM
     ***********************/
    echo '<form action="' . h($_SERVER['PHP_SELF']) . '" method="post">';
    echo '<input type="hidden" name="assay_action" value="update_assay">';
    echo '<input type="hidden" name="assay_id" value="' . h($assay_id) . '">';

    echo '<table border="0" cellpadding="3" width="100%">';

    echo '<tr>';
    echo '<td width="15%"><b>Material</b></td><td width="35%"><input type="text" name="assay_material" value="' . h($row['Material']) . '" size="40"></td>';
    echo '<td width="15%"><b>Type</b></td><td width="35%"><input type="text" name="assay_type" value="' . h($row['Type']) . '" size="40"></td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td><b>Liaison Person</b></td><td><input type="text" name="assay_liaison" value="' . h($row['Liaison']) . '" size="40"></td>';
    echo '<td><b>Manufacturer</b></td><td><input type="text" name="assay_manufacturer" value="' . h($row['Manufacturer']) . '" size="40"></td>';
    echo '<td><b>Date</b></td><td><input type="date" name="assay_date" value="' . h($row['Date']) . '"></td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td><b>Description</b></td>';
    echo '<td colspan="5"><textarea name="assay_description" rows="2" cols="100">' . h($row['Description']) . '</textarea></td>';
    echo '</tr>';

    // DocDB: allow multiple IDs (comma/space-separated), one link per ID
    echo '<tr>';
    echo '<td><b>DocDB</b></td>';
    echo '<td>';
    echo '<input type="text" name="assay_docdb" value="' . h($row['Docdb']) . '" size="20" placeholder="e.g. 12345, 67890">';
    $docdb_raw = trim((string)$row['Docdb']);
    if ($docdb_raw !== '') {
        $docdb_ids = array_filter(array_map('trim', preg_split('/[\s,]+/', $docdb_raw)));
        foreach ($docdb_ids as $id) {
            if ($id !== '' && is_numeric($id)) {
                $doc_url = 'https://gev.uchicago.edu/cgi-bin/DocDB/ShowDocument?docid=' . urlencode($id);
                echo ' <a href="' . h($doc_url) . '" target="_blank">[' . h($id) . ']</a>';
            }
        }
    }
    echo '</td>';
    echo '<td colspan="4"></td>';
    echo '</tr>';


    // Buttons row (Update center, Delete right)
    echo '<tr>';
    echo '<td colspan="3" style="text-align:center; padding-top:8px;">';

    // UPDATE form
    // echo '<form action="' . h($_SERVER['PHP_SELF']) . '" method="post" style="display:inline;">';
    echo '<input type="hidden" name="assay_action" value="update_assay">';
    echo '<input type="hidden" name="assay_id" value="' . h($assay_id) . '">';
    echo '<button type="submit">Update</button>';
    echo '</form>';

    echo '</td>';

    echo '<td colspan="3" style="text-align:right; padding-top:8px;">';

    // DELETE form
    echo '<form action="' . h($_SERVER['PHP_SELF']) . '" method="post" style="display:inline;">';
    echo '<input type="hidden" name="assay_action" value="delete_assay">';
    echo '<input type="hidden" name="assay_id" value="' . h($assay_id) . '">';
    echo '<button type="submit" onclick="return confirm(\'Delete assay row (detail table kept)?\');">Delete</button>';
    echo '</form>';

    echo '</td>';
    echo '</tr>';

    echo '</table>';


    /***********************
     * 3) Attached files (upload + list: preview, delete)
     ***********************/
    echo '<hr style="margin:12px 0;">';

    echo '<div style="margin:6px 0;">';
    echo '<form action="' . h($_SERVER['PHP_SELF']) . '" method="post" enctype="multipart/form-data">';
    echo '<input type="hidden" name="assay_action" value="upload_files">';
    echo '<input type="hidden" name="assay_id" value="' . h($assay_id) . '">';
    echo 'Upload file(s): <input type="file" name="assay_files[]" multiple> ';
    echo '<button type="submit">Upload</button>';
    echo '</form>';
    echo '</div>';

    $qf = "SELECT * FROM `assay_files` WHERE `Assay_ID` = " . (int)$assay_id . " ORDER BY `ID` DESC";
    $rf = mysql_query($qf);
    if (!$rf) die("Could not query assay_files: " . mysql_error() . "<br>" . h($qf));

    echo '<div style="margin-top:8px;"><b>Attached files</b></div>';

    if (mysql_num_rows($rf) == 0) {
        echo '<div style="color:#555;">(No files uploaded.)</div>';
    } else {
        echo '<ul>';
        while ($f = mysql_fetch_assoc($rf)) {
            $file_id = (int)$f['ID'];
            $label = $f['Orig_Name'] ? $f['Orig_Name'] : $f['Stored_Name'];
            $preview_url = "serve_assay_file.php?id=" . $file_id . "&disposition=inline";

            echo '<li>';
            echo h($label) . ' ';
            echo '[<a href="' . h($preview_url) . '" target="_blank">preview</a>] ';
            echo '[<form action="' . h($_SERVER['PHP_SELF']) . '" method="post" style="display:inline;">';
            echo '<input type="hidden" name="assay_action" value="delete_file">';
            echo '<input type="hidden" name="assay_id" value="' . h($assay_id) . '">';
            echo '<input type="hidden" name="file_id" value="' . h($file_id) . '">';
            echo '<button type="submit" '
                . 'style="background:none; border:none; padding:0; margin:0; color:#00f; text-decoration:underline; cursor:pointer; font:inherit;" '
                . 'onclick="return confirm(\'Delete this file? This cannot be undone.\');">'
                . 'delete</button>';
            echo '</form>]';
            echo '</li>';
        }
        echo '</ul>';
    }

    /***********************
     * 4) DETAIL TABLE (unchanged logic; already per-row forms)
     ***********************/
    echo '<hr style="margin:12px 0;">';

    echo '<div style="margin:6px 0;"><b>Nuclide activities</b> (table: ' . h($detail_table) . ')</div>';

    echo '<table border="1" cellpadding="3" width="100%">';
    echo '<tr style="background:#f3f3f3;">';
    echo '<th>Nuclide</th>
      <th>Limit Type</th>
      <th>Result(Bq/kg)</th>
      <th>Uncertainty(Bq/kg)</th>
      <th>used in G4</th>
      <th>Note</th>
      <th>Action</th>';
    echo '</tr>';

    $qd = "SELECT * FROM `{$detail_table}` ORDER BY `ID` DESC";
    $rd = mysql_query($qd);
    if (!$rd) die("Could not query detail table: " . mysql_error() . "<br>" . h($qd));

    while ($d = mysql_fetch_assoc($rd)) {
        echo '<tr>';
        echo '<form action="' . h($_SERVER['PHP_SELF']) . '" method="post">';
        echo '<input type="hidden" name="detail_table_name" value="' . h($detail_table) . '">';
        echo '<input type="hidden" name="detail_id" value="' . h($d['ID']) . '">';

        $cell_td = ' style="text-align:center; vertical-align:middle;"';
        $cell_in = ' style="width:100%; text-align:center; box-sizing:border-box; padding:2px 6px;"';

        $row_id = (int)$d['ID'];
        $is_upper = ($d['Type'] === 'Upper Limit');
        $unc_display = fmt_sci($d['Uncertainty'], 2);

        // Submitted value: always in a hidden input that is a direct child of the form (so it is always submitted)
        echo '<input type="hidden" name="detail_uncertainty" id="detail_uncertainty_' . h($row_id) . '" value="' . h($unc_display) . '">';

        // type selector
        $type_select =
            '<select name="detail_type" id="detail_type_' . h($row_id) . '"' . $cell_in
            . ' onchange="toggleUnc(\'unc_cell_' . h($row_id) . '\', this.value, ' . (int)$row_id . ')">'
            . '<option value="Upper Limit"' . ($is_upper ? ' selected' : '') . '>Upper Limit</option>'
            . '<option value="Value"' . (!$is_upper ? ' selected' : '') . '>Value</option>'
            . '</select>';

        echo '<td' . $cell_td . '><input type="text" name="detail_nuclide_1" value="' . h($d['Nuclide_1']) . '"' . $cell_in . '></td>';
        echo '<td' . $cell_td . '>' . $type_select . '</td>';

        echo '<td' . $cell_td . '><input type="text" name="detail_result" value="' . h(fmt_sci($d['Result'], 2)) . '"' . $cell_in . '></td>';

        // Uncertainty cell: display only; submitted value is in the hidden input above
        echo '<td' . $cell_td . ' id="unc_cell_' . h($row_id) . '">';
        if ($is_upper) {
            echo '<span style="color:#777;">n/a</span>';
        } else {
            echo '<input type="text" id="unc_display_' . h($row_id) . '" value="' . h($unc_display) . '"' . $cell_in
                . ' oninput="document.getElementById(\'detail_uncertainty_' . h($row_id) . '\').value=this.value">';
        }
        echo '</td>';

        // Checkbox
        $checked = (!empty($d['Used_in_simulation']) && $d['Used_in_simulation'] == 1) ? ' checked' : '';
        echo '<td style="text-align:center; vertical-align:middle; width:80px; white-space:nowrap;">'
            . '<input type="checkbox" name="detail_used_in_simulation" value="1"' . $checked . '>'
            . '</td>';        //note
        echo '<td' . $cell_td . '><input type="text" name="detail_note" value="' . h($d['Note']) . '"' . $cell_in . '></td>';

        // Action buttons
        echo '<td style="text-align:center; vertical-align:middle; white-space:nowrap;">'
            . '<button type="submit" name="detail_action" value="update">Update</button> '
            . '<button type="submit" name="detail_action" value="delete" onclick="return confirm(\'Delete this nuclide row?\');">Delete</button>'
            . '</td>';

        echo '</form>';
        echo '</tr>';
    }

    // add-new row (same as you had)
    echo '<tr>';
    echo '<form action="' . h($_SERVER['PHP_SELF']) . '" method="post">';
    echo '<input type="hidden" name="detail_table_name" value="' . h($detail_table) . '">';
    $cell_td = ' style="text-align:center; vertical-align:middle;"';
    $cell_in = ' style="width:100%; text-align:center; box-sizing:border-box; padding:2px 6px;"';
    $type_select_new =
        '<select name="detail_type"' . $cell_in . '>'
        . '<option value="" selected></option>'
        . '<option value="Upper Limit">Upper Limit</option>'
        . '<option value="Value">Value</option>'
        . '</select>';
    echo '<td' . $cell_td . '><input type="text" name="detail_nuclide_1" value=""' . $cell_in . '></td>';
    echo '<td' . $cell_td . '><input type="text" name="detail_nuclide_2" value=""' . $cell_in . '></td>';
    echo '<td' . $cell_td . '>' . $type_select_new . '</td>';
    echo '<td' . $cell_td . '><input type="text" name="detail_result" value=""' . $cell_in . '></td>';
    echo '<td' . $cell_td . '><input type="text" name="detail_uncertainty" value=""' . $cell_in . '></td>';
    echo '<td' . $cell_td . '><input type="text" name="detail_note" value=""' . $cell_in . '></td>';
    echo '<td style="text-align:center; vertical-align:middle; white-space:nowrap;"><button type="submit" name="detail_action" value="add">Add</button></td>';
    echo '</form>';
    echo '</tr>';

    echo '</table>'; // detail table

    echo '</td></tr>';  // close wrapper cell/row
    echo '</table>';    // close outer wrapper table
    echo '<br><br>';
}

/*******************************
 * ADD NEW ASSAY (blank form)
 *******************************/
echo '<br>';
echo '<table border="1" cellpadding="4" width="100%">';
echo '<tr style="background:#eee;"><th align="left">Add new assay</th></tr>';
echo '<tr><td>';

echo '<form action="' . h($_SERVER['PHP_SELF']) . '" method="post">';
echo '<input type="hidden" name="assay_action" value="add_assay">';   // <-- change to your handler name if different

echo '<table border="0" cellpadding="3" width="100%">';

// Row 1
echo '<tr>';
echo '<td width="15%"><b>Material</b></td>';
echo '<td width="35%"><input type="text" name="assay_material" value="" size="40"></td>';

echo '<td colspan="1" style="text-align:left; padding-top:8px;">';
echo '<button type="submit">Add assay</button>';
echo '</td>';
echo '</tr>';

echo '</table>';
echo '</form>';

echo '</td></tr>';
echo '</table>';
echo '<br><br>';
