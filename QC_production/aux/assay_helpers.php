<?php
function h($s)
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
function post_esc($key)
{
    return mysql_real_escape_string(isset($_POST[$key]) ? $_POST[$key] : "");
}
function slugify($s)
{
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '_', $s);
    $s = trim($s, '_');
    if ($s === "") $s = "material";
    if (strlen($s) > 40) $s = substr($s, 0, 40);
    return $s;
}

// Recommended: stable per-assay table name
function detail_table_name_from_row($assay_id, $material)
{
    $id = (int)$assay_id;
    return "assay_results_" . $id; // stable even if Material changes
    // Alternative (NOT recommended due to collisions):
    // return "assay_results_" . slugify($material);
}


function ensure_detail_table($table_name)
{
    $table_name_esc = mysql_real_escape_string($table_name);

    // Allowlist: only [a-z0-9_] and must start with assay_results_
    if (!preg_match('/^assay_results_[a-z0-9_]+$/', $table_name_esc)) {
        die("Invalid detail table name.");
    }

    $q = "
      CREATE TABLE IF NOT EXISTS `{$table_name_esc}` (
        `ID` INT(11) NOT NULL AUTO_INCREMENT,
        `Nuclide_1` VARCHAR(8) DEFAULT NULL,
        `Type` VARCHAR(20) DEFAULT NULL,
        `Result` DOUBLE DEFAULT NULL,
        `Uncertainty` DOUBLE DEFAULT NULL,
        `Used_in_simulation` TINYINT(1) DEFAULT 0,
        `Note` TEXT,
        PRIMARY KEY (`ID`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1
    ";
    $r = mysql_query($q);
    if (!$r) die("Could not create detail table: " . mysql_error() . "<BR>" . h($q));
}

function fmt_sci($v, $precision = 3)
{
    if ($v === null || $v === '') return '';
    $v = (float)$v;
    if ($v == 0.0) return '0';
    return sprintf('%.' . $precision . 'e', $v);
}
