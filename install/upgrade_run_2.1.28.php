<?php
/**
 * @file          upgrade.ajax.php
 * @author        Nils Laumaillé
 * @version       2.1.28
 * @copyright     (c) 2009-2017 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

/*
** Upgrade script for release 2.1.28
*/
require_once('../sources/SecureHandler.php');
session_start();
error_reporting(E_ERROR | E_PARSE);
$_SESSION['db_encoding'] = "utf8";
$_SESSION['CPM'] = 1;

require_once '../includes/language/english.php';
require_once '../includes/config/include.php';
require_once '../includes/config/settings.php';
require_once '../sources/main.functions.php';

$_SESSION['settings']['loaded'] = "";

################
## Function permits to get the value from a line
################
function getSettingValue($val)
{
    $val = trim(strstr($val, "="));
    return trim(str_replace('"', '', substr($val, 1, strpos($val, ";")-1)));
}

################
## Function permits to check if a column exists, and if not to add it
################
function addColumnIfNotExist($db, $column, $columnAttr = "VARCHAR(255) NULL")
{
    global $dbTmp;
    $exists = false;
    $columns = mysqli_query($dbTmp, "show columns from $db");
    while ($c = mysqli_fetch_assoc( $columns)) {
        if ($c['Field'] == $column) {
            $exists = true;
            return true;
        }
    }
    if (!$exists) {
        return mysqli_query($dbTmp, "ALTER TABLE `$db` ADD `$column`  $columnAttr");
    } else {
        return false;
    }
}

function addIndexIfNotExist($table, $index, $sql ) {
    global $dbTmp;

    $mysqli_result = mysqli_query($dbTmp, "SHOW INDEX FROM $table WHERE key_name LIKE \"$index\"");
    $res = mysqli_fetch_row($mysqli_result);

    // if index does not exist, then add it
    if (!$res) {
        $res = mysqli_query($dbTmp, "ALTER TABLE `$table` " . $sql);
    }

    return $res;
}

function tableExists($tablename, $database = false)
{
    global $dbTmp;

    $res = mysqli_query($dbTmp,
        "SELECT COUNT(*) as count
        FROM information_schema.tables
        WHERE table_schema = '".$_SESSION['db_bdd']."'
        AND table_name = '$tablename'"
    );

    if ($res > 0) return true;
    else return false;
}

function cleanFields($txt) {
    $tmp = str_replace(",", ";", trim($txt));
    if (empty($tmp)) return $tmp;
    if ($tmp === ";") return "";
    if (strpos($tmp, ';') === 0) $tmp = substr($tmp, 1);
    if (substr($tmp, -1) !== ";") $tmp = $tmp.";";
    return $tmp;
}

//define pbkdf2 iteration count
@define('ITCOUNT', '2072');

$return_error = "";

// do initial upgrade

//include librairies
require_once '../includes/libraries/Tree/NestedTree/NestedTree.php';

//Build tree
$tree = new Tree\NestedTree\NestedTree(
    $_SESSION['pre'].'nested_tree',
    'id',
    'parent_id',
    'title'
);

// dataBase
$res = "";
$dbTmp = mysqli_connect(
    $_SESSION['server'],
    $_SESSION['user'],
    $_SESSION['pass'],
    $_SESSION['database'],
    $_SESSION['port']
);


// add field u2f_id to USERS table
$res = addColumnIfNotExist(
    $_SESSION['pre']."users",
    "u2f_id",
    "varchar(10) NOT null DEFAULT 'none'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field u2f_id to table Users! '.mysqli_error($dbTmp).'!"}]';
    mysqli_close($dbTmp);
    exit();
}

// add field u2f_key to USERS table
$res = addColumnIfNotExist(
    $_SESSION['pre']."users",
    "u2f_key",
    "varchar(60) NOT null DEFAULT 'none'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field u2f_key to table Users! '.mysqli_error($dbTmp).'!"}]';
    mysqli_close($dbTmp);
    exit();
}



// add new admin setting "u2f"
$tmp = mysqli_num_rows(mysqli_query($dbTmp, "SELECT * FROM `".$_SESSION['pre']."misc` WHERE type = 'admin' AND intitule = 'u2f'"));
if ($tmp === "0") {
    mysqli_query($dbTmp,
        "INSERT INTO `".$_SESSION['pre']."misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'u2f', '0')"
    );
}

// add new admin setting "u2f_https"
$tmp = mysqli_num_rows(mysqli_query($dbTmp, "SELECT * FROM `".$_SESSION['pre']."misc` WHERE type = 'admin' AND intitule = 'u2f_https'"));
if ($tmp === "0") {
    mysqli_query($dbTmp,
        "INSERT INTO `".$_SESSION['pre']."misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'u2f_https', '0')"
    );
}

// add new admin setting "u2f_httpsverify"
$tmp = mysqli_num_rows(mysqli_query($dbTmp, "SELECT * FROM `".$_SESSION['pre']."misc` WHERE type = 'admin' AND intitule = 'u2f_httpsverify'"));
if ($tmp === "0") {
    mysqli_query($dbTmp,
        "INSERT INTO `".$_SESSION['pre']."misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'u2f_httpsverify', '0')"
    );
}

// add new admin setting "u2f_waitforall"
$tmp = mysqli_num_rows(mysqli_query($dbTmp, "SELECT * FROM `".$_SESSION['pre']."misc` WHERE type = 'admin' AND intitule = 'u2f_waitforall'"));
if ($tmp === "0") {
    mysqli_query($dbTmp,
        "INSERT INTO `".$_SESSION['pre']."misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'u2f_waitforall', '0')"
    );
}

// add new admin setting "u2f_sl"
$tmp = mysqli_num_rows(mysqli_query($dbTmp, "SELECT * FROM `".$_SESSION['pre']."misc` WHERE type = 'admin' AND intitule = 'u2f_sl'"));
if ($tmp === "0") {
    mysqli_query($dbTmp,
        "INSERT INTO `".$_SESSION['pre']."misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'u2f_sl', '0')"
    );
}

// add new admin setting "u2f_timeout"
$tmp = mysqli_num_rows(mysqli_query($dbTmp, "SELECT * FROM `".$_SESSION['pre']."misc` WHERE type = 'admin' AND intitule = 'u2f_timeout'"));
if ($tmp === "0") {
    mysqli_query($dbTmp,
        "INSERT INTO `".$_SESSION['pre']."misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'u2f_timeout', '0')"
    );
}

// add new admin setting "u2f_url"
$tmp = mysqli_num_rows(mysqli_query($dbTmp, "SELECT * FROM `".$_SESSION['pre']."misc` WHERE type = 'admin' AND intitule = 'u2f_url'"));
if ($tmp === "0") {
    mysqli_query($dbTmp,
        "INSERT INTO `".$_SESSION['pre']."misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'u2f_url', 'api.yubico.com/wsapi/2.0/verify,api2.yubico.com/wsapi/2.0/verify,api3.yubico.com/wsapi/2.0/verify,api4.yubico.com/wsapi/2.0/verify,api5.yubico.com/wsapi/2.0/verify')"
    );
}

// Finished
echo '[{"finish":"1" , "next":"", "error":""}]';