<?php
/**
 *
 * @file          logout.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2017 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */
// ini_set("display_errors", true);
require_once 'sources/SecureHandler.php';
if( !session_id() || session_status() == PHP_SESSION_NONE )
{
    session_start();
}

// Update table by deleting ID
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
    $user_id = $_GET['user_id'];
} else {
    $user_id = "";
}


if (!empty($user_id) && $user_id != "") {
    require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';

    // connect to the server

    require_once './includes/config/settings.php';
    require_once './includes/libraries/Database/Meekrodb/db.class.php';
    DB::$host = $server;
    DB::$user = $user;
    DB::$password = $pass;
    DB::$dbName = $database;
    DB::$port = $port;
    DB::$encoding = $encoding;
    DB::$error_handler = true;
    $link = mysqli_connect($server, $user, $pass, $database, $port);
    $link->set_charset($encoding);

    // clear in db
    DB::update(
        $pre."users",
        array(
            'key_tempo' => '',
            'timestamp' => '',
            'session_end' => ''
        ),
        "id=%i",
        $user_id
    );

    //Log into DB the user's disconnection
    if (
        (isset($_SESSION['settings']['log_connections']) && $_SESSION['settings']['log_connections'] == 1) &&
        (isset($_SESSION['login']) && $_SESSION['login'] != "")
    ) {
        logEvents('user_connection', 'disconnection', $user_id, $_SESSION['login']);
    }
}

// erase session table
session_destroy();
$_SESSION = array();
unset($_SESSION);

echo '
<script language="javascript" type="text/javascript">
<!--
sessionStorage.clear();
setTimeout(function(){document.location.href="index.php"}, 1);
    -->
    </script>';

?>
