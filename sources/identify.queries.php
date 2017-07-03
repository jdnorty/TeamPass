<?php
/**
 *
 * @file          identify.queries.php
 * @author        Josh Northrup
 * @version       2.1.27
 * @copyright     (c) 2009-2017 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */
require_once 'SecureHandler.php';
if( !session_id() || session_status() == PHP_SESSION_NONE )
{
  session_start();
}

if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
  die('Hacking attempt...');
}

if (!isset($_SESSION['settings']['cpassman_dir']) || $_SESSION['settings']['cpassman_dir'] === "" || $_SESSION['settings']['cpassman_dir'] === ".") {
  $_SESSION['settings']['cpassman_dir'] = "..";
}

function checkUserRoles($roles, $user_login = "") {
  $ret_val = false;
  $error_code = 0;
  if (!is_array($roles)) { return array ( "result" => $ret_val, "error_code" => 999); }
  /* Init */
  $debugit = 0; //Can be used in order to debug
  $dbg = "";
  $role_complexity = 60;

  include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
  error_reporting(E_ERROR);
  require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';
  require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

  // connect to the server
  require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
  try {
    if ($debugit == 1) {
      $dbg = fopen($_SESSION['settings']['path_to_files_folder']."/groups.debug.txt", "w");
    }
    $db_table = prefix_table("roles_title");
    DB::$host = $server;
    DB::$user = $user;
    DB::$password = $pass;
    DB::$dbName = $database;
    DB::$port = $port;
    DB::$encoding = $encoding;
    DB::$error_handler = true;
    $link = mysqli_connect($server, $user, $pass, $database, $port);
    $link->set_charset($encoding);
    foreach ($roles as $role_name) {
      $tmp = DB::query("SELECT * FROM ".$db_table." WHERE title = %s", stripslashes($role_name));
      $counter = DB::count();
      if ($debugit == 1) {
        fputs($dbg, "Found ".$counter." # of roles created already.\n\n\n");
      }
      if ($counter == 0) {
        try {
          $user_id = $_SESSION['user_id'];
          /* Insert new role into DB */
          DB::debugmode(false);
          DB::startTransaction();
          DB::insert(
            $db_table,
            array(
              'title' => ucwords($role_name),
              'complexity' => $role_complexity,
              'creator_id' => 1
            )
          );

          $role_id = DB::insertId();
          if ($debugit == 1) {
            $c_user = $user_login || $user_id || "Null";
            fputs(
              $dbg,
              "User: ".$c_user."\n\n\n".
              "Role Id: ".$role_id."\n\n\n"
            );
          }
          if ($role_id !== 0) {
            $db_table = prefix_table("users");
            //Actualize the variable
            $_SESSION['nb_roles']++;
            // get some data
            if ($user_login && $user_login !== "") {
              $data_tmp = DB::queryfirstrow("SELECT fonction_id FROM ".$db_table." WHERE login = %s", $user_login);
            } elseif ($user_id && $user_id !== "") {
              $data_tmp = DB::queryfirstrow("SELECT fonction_id FROM ".$db_table." WHERE id = %s", $user_id);
            } else {
              $error_code = 1000;
              fputs($dbg, "Exiting, Missing User Information. Error Code: " . $error_code . "\n\n\n");
              DB::rollback();
              return array ( "result" => $ret_val, "error_code" => $error_code);
            }
            // Add new role to user
            $fonction_id = $data_tmp['fonction_id'];
            if ($debugit == 1) {
              fputs($dbg, "User's Current Fonction Id: ".$fonction_id."\n\n\n");
            }
            $tmp = str_replace(";;", ";", $fonction_id);
            if (substr($tmp, -1) == ";") {
              $fonction_id = str_replace(";;", ";", $fonction_id . $role_id);
            } else {
              $fonction_id = str_replace(";;", ";", $fonction_id . ";" . $role_id);
            }
            $_SESSION['fonction_id'] = $fonction_id;
            if ($debugit == 1) {
              fputs($dbg, "User's Revised Fonction Id: ".$fonction_id."\n\n\n");
            }
            /* Update user's Roles */
            if ($user_login && $user_login !== "") {
              DB::update(
                $db_table,
                array(
                  'fonction_id' => $fonction_id
                ),
                "login = %i",
                $user_login
              );
            } else {
              DB::update(
                $db_table,
                array(
                  'fonction_id' => $fonction_id
                ),
                "id = %i",
                $user_id
              );
            }
            $counter = DB::affectedRows();
            if ($counter != 0 ) {
              $_SESSION['user_roles'] = explode(";", $fonction_id);
              $ret_val = true;
              if ($debugit == 1) {
                fputs($dbg, "Committing changes to database.\n\n\n");
              }
              /* Commit all changes */
              DB::commit();
              continue;
            }
          }
          $error_code = 2;
          fputs($dbg, "Rolling back changes to database.\n\n\n");
          DB::rollback();
        } catch (Exception $e) {
          $error_code = 1;
          $error = $e->getMessage();
          if ($debugit == 1) {
            fputs($dbg, "General Error: " . $error . "\n\n\n");
          }
          $ret_val = false;
          DB::rollback();
        }
      }
      continue;
    }
} catch (Exception $e) {
    $error_code = 3;
    $error = $e->getMessage();
    if ($debugit == 1) {
      fputs($dbg, "Rolling back changes to database: " . $error . "\n\n\n");
    }
    $ret_val = false;
    DB::rollback();
}
if ($debugit == 1) {
  fputs(
    $dbg,
    "Returned Value: \n    result: " .$ret_val. "\n    error_code: " . $error_code . "\n\n\n".
    "Finished: True\n\n\n"
  );
  fclose($dbg);
}
return array ( "result" => $ret_val, "error_code" => $error_code);
}
?>
