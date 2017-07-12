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
 /*
error_reporting(-1); // reports all errors
ini_set("display_errors", "1"); // shows all errors
ini_set("log_errors", 1);
ini_set("error_log", "php-error.log");

require_once 'SecureHandler.php';
session_start();
*/
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
  die('Hacking attempt...');
}

if (!isset($_SESSION['settings']['cpassman_dir']) || $_SESSION['settings']['cpassman_dir'] === "" || $_SESSION['settings']['cpassman_dir'] === ".") {
  $_SESSION['settings']['cpassman_dir'] = "..";
}

spl_autoload_register(
     function($class_name) {
      $class_name = strtolower($class_name);
      if ($class_name === "db")
        $file_name = $_SESSION['settings']['cpassman_dir']."/includes/libraries/Database/Meekrodb/" . $class_name . ".class.php";
      else
        $file_name = $class_name . ".php";
      if(file_exists($file_name))
        require $file_name;
   }
);

class IdentifyException extends CustomException {}

class Identify {

  protected static $debug = true;       //Can be used in order to debug

  function __construct()
  {
    Identify::setupDbConnection();
    $this->logger = new Logger(Identify::$debug);
  }

  function checkUserRoles ($roles, $user_login = "") {
    // Outer try block
    try {
      $debugit          = true;       //Can be used in order to debug
      $logger           = new Logger($debugit);
      if (!is_array($roles))
        throw new IdentifyException('Incorrect parameter type. roles require variable type array.', -1);

      require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';

      // Init
      $role_complexity  = 60;
      $ret_val          = array ( "result" => true, "error_code" => 1 );
      $user_id          = $_SESSION['user_id'];
      $found_user_login = $user_login && $user_login !== "";
      $found_user_id    = $user_id && $user_id !== "";
      $c_user           = $user_login ?: $user_id;

      $this->logger->log("*************************************************************************");
      $this->logger->log("Starting User Role Verification for => ". $c_user);

      if ( !( $found_user_login || $found_user_id ) )
        throw new IdentifyException("Exiting, Missing User Information for => " . $c_user . ". Role => ". $role_name .", Error Code => " . $ret_val['error_code'], -1);

      setupDbConnection();

      // Get user data
      ($found_user_login && $user_info = $this->getUserByLogin($user_login)) ||
      ($found_user_id && $user_info = $this->getUserById($user_id));
      if ( !$user_info )
        throw new IdentifyException("User Info is in an unnexceptable format. Id => " . $user_id . ", login => " . $user_login, -1);

      $c_fonction_ids = isset($user_info['result']) ? $user_info['result']['fonction_id'] : false;  // Role Ids for User, if false if no data is returned from query
      $c_user_id      = isset($user_info['result']) ? $user_info['result']['id'] : false;           // User Id, is false if no data is returned from query

      if ( !$c_fonction_ids && !$c_user_id )
        throw new IdentifyException("User info couldn't be retrieved from database.", -2);

      $user_role_ids = array_filter(explode(";", $c_fonction_ids));
      $this->logger->log("User's Current Fonction Ids: " . $c_fonction_ids);

      // Loop through array of groups the user belongs to
      foreach ( $roles as $role_name ) {
        $role_name = ucwords(strtolower(stripslashes($role_name)));                     // Convert to Role naming standard
        $this->logger->log("Checking if User is already in role => ". $role_name);

        $role_info = $this->getRole($role_name);                                               // Check if Group already exists
        $role_id   = isset($role_info['result']) ? $role_info['result']['id'] : false;  // Role Id from DB query, if false if no role found

        // Inner try block
        try {
          // Start DB Transaction
          DB::startTransaction();

          if ( $role_id ) {
            // Group Exists
            $this->logger->log("Found role id #" . $role_id . " in the database that matches the query criteria: Title => " . $role_name);
          } else {
            // Group does not exists - Insert new role into DB
            $this->logger->log("Attempting to add new Role => ". $role_name);
            $role_info = $this->addRole($role_name, $role_complexity);
            $role_id   = isset($role_info['result']) ? $role_info['result'] : false;  // Role Id from DB insert, if false if no role created
            $this->logger->log("Created role id #" . $role_id . " in the database because no matches were found based on the query criteria: Title => " . $role_name);
            unset($role_info); // Clear variable
          }

          if ( !$role_id )
            throw new IdentifyException("Could not retrieve the Role's Id value.", -2);

          if ( in_array($role_id, $user_role_ids) ) {
            $this->logger->log("User is already a memeber of Role => " . $role_name . ", Role Id => " . $role_id);
            DB::rollback();
            continue;
          }

          // Add new role to user
          array_push($user_role_ids, $role_id);
          $n_fonction_ids = implode(";", $user_role_ids);

          // Update user's Roles
          $update = $this->updateUserRoles($c_user_id, $n_fonction_ids);
          $this->logger->log("Adding Role => " . $role_name . " to user profile");

          if ( $update['result']) {
            $ret_val['result'] = true;
            $_SESSION['user_roles'] = explode(";", $fonction_id);
            $this->logger->log("Committing changes to database.");
            DB::commit();                                                       // Commit all changes
            $this->logger->log("User's Revised Fonction Id: ".$n_fonction_ids.".");
            $_SESSION['fonction_id'] = $n_fonction_ids;                         // Set Session Role ids
            $_SESSION['nb_roles']++;                                            // Set Session Role Number
            continue;
          } else {
            throw new IdentifyException("Failed to update user roles => " . $n_fonction_ids.", User Id => " . $c_user_id, -2);
          }
        } catch (Exception $e) {
          $ret_val['result']     = false;
          $ret_val['error_code'] = $e->getCode();
          $ret_val['message']    = $e->getMessage();
          $this->logger->log("General Error: " . $ret_val['message'] . ".");
          DB::rollback();
        } // End Inner Try Block
        continue;
      } // End Foreach
    } catch (Exception $e) {
      $ret_val['result']     = false;
      $ret_val['error_code'] = $e->getCode();
      $ret_val['message']    = $e->getMessage();
      $this->logger->log("Exception: " . $ret_val['message'] . ".");
    } // End Outer Try Block

    $this->logger->log("Sucessfully Finished User Role Verification. result => " . ($ret_val['result'] ? "True" : "False") . ", error_code => " . $ret_val['error_code'] . " ]");
    $this->logger->log("*************************************************************************");
    unset($this->logger);
    return $ret_val;
  }

  function addRole ($role_name, $role_complexity) {
    $ret_val                   = array( 'result' => false, 'error_code' => -1, 'message' => 'Unsuccessfully created new Role => ' . $role);
    try {
      $db_table                = prefix_table("roles_title");
      DB::insert( $db_table, array( 'title' => $role_name, 'complexity' => $role_complexity, 'creator_id' => 1 )  );
      $id                      = DB::insertId();
      if ( $id ) {
        $ret_val['result']     = $id;
        $ret_val['error_code'] = 1;
        $ret_val['message']    = 'Successfully created new Role => ' . $role_name;
      }
    } catch ( Exception $e ) {
        $ret_val['error_code'] = $e->getCode();
        $ret_val['message']    = $e->getMessage();
        $ret_val['result']     = false;
    } // End Try Block
    return $ret_val;
  }

  function getRole ($role) {
    $ret_val                   = array( 'result' => false, 'error_code' => -1, 'message' => 'Unsuccessfully queried Role Info => ' . $role);
    try {
      $db_table                = prefix_table("roles_title");
      $r_data                  = DB::queryFirstRow("SELECT * FROM " . $db_table . " WHERE title = %s", $role);
      $count                   = DB::count();
      if ( $count > 0 ) {
        $ret_val['result']     = $r_data;
        $ret_val['error_code'] = 1;
        $ret_val['message']    = 'Successfully queried Role Info => ' . $role;
      }
    } catch ( Exception $e ) {
        $ret_val['error_code'] = $e->getCode();
        $ret_val['message']    = $e->getMessage();
        $ret_val['result']     = false;
    } // End Try Block
    return $ret_val;
  }

  function updateUserRoles ($user_id, $role_ids) {
    $ret_val                   = array( 'result' => false, 'error_code' => -1, 'message' => 'Unsuccessfully updated user\'s. User Id => ' . $user_id . ', Role Ids => ' . $role_ids);
    try {
      $db_table                = prefix_table("users");
      DB::update( $db_table, array( 'fonction_id' => $role_ids ), "id = %i", $user_id );
      $count                   = DB::affectedRows();
      if ( $count > 0 ) {
        $ret_val['result']     = true;
        $ret_val['error_code'] = 1;
        $ret_val['message']    = 'Successfully inserted Role Info.';
      }
    } catch ( Exception $e ) {
        $ret_val['error_code'] = $e->getCode();
        $ret_val['message']    = $e->getMessage();
        $ret_val['result']     = false;
    } // End Try Block
    return $ret_val;
  }

  function getUserByLogin ($filter) {
    $ret_val                   = array( 'result' => false, 'error_code' => -1, 'message' => 'Unsuccessfully queried User using the User\s login => ' . $filter);
    try {
      $db_table                = prefix_table("users");
      $r_data                  = DB::queryfirstrow("SELECT * FROM " . $db_table . " WHERE login = %s", $filter);
      $count                   = DB::count();
      if ( $count > 0 ) {
        $ret_val['result']     = $r_data;
        $ret_val['error_code'] = 1;
        $ret_val['message']    = 'Successfully queried User using the User\s login => ' . $filter;
      }
    } catch ( Exception $e ) {
        $ret_val['error_code'] = $e->getCode();
        $ret_val['message']    = $e->getMessage();
        $ret_val['result']     = false;
    } // End Try Block
    return $ret_val;
  }

  function getUserById ($filter) {
    $ret_val                   = array( 'result' => false, 'error_code' => -1, 'message' => 'Unsuccessfully queried User using the User\s Id => ' . $filter);
    try {
      $db_table                = prefix_table("users");
      $r_data                  = DB::queryfirstrow("SELECT * FROM " . $db_table . " WHERE id = %s", $filter);
      $count                   = DB::count();
      if ( $count > 0 ) {
        $ret_val['result']     = $r_data;
        $ret_val['error_code'] = 1;
        $ret_val['message']    = 'Successfully queried User using the User\s Id => ' . $filter;
      }
    } catch ( Exception $e ) {
        $ret_val['error_code'] = $e->getCode();
        $ret_val['message']    = $e->getMessage();
        $ret_val['result']     = false;
    } // End Try Block
    return $ret_val;
  }

  private static function setupDbConnection () {
    $ret_val                   = array( 'result' => true, 'error_code' => 1, 'message' => 'Successfully setup Connection Requirements.');
    try {
      require $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
      // Setup DB connection
      DB::$host                = $server;
      DB::$user                = $user;
      DB::$password            = $pass;
      DB::$dbName              = $database;
      DB::$port                = $port;
      DB::$encoding            = $encoding;
      DB::$error_handler       = true;
      DB::debugmode(false);
    } catch (Exception $e) {
        $ret_val['error_code'] = $e->getCode();
        $ret_val['message']    = $e->getMessage();
        $ret_val['result']     = false;
    } // End Try Block
    return $ret_val;
  }
}
?>
