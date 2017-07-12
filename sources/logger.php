<?php
/**
 *
 * @file          logger.php
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

if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
  die('Hacking attempt...');
}

if (!isset($_SESSION['settings']['cpassman_dir']) || $_SESSION['settings']['cpassman_dir'] === "" || $_SESSION['settings']['cpassman_dir'] === ".") {
  $_SESSION['settings']['cpassman_dir'] = "..";
}

date_default_timezone_set($_SESSION['settings']['timezone']);

define('DEFAULT_LOGFILE',$_SESSION['settings']['cpassman_dir'] . '/files/vault.log');

class Logger
{
  private $base_path;
  private $basename;
  private $filename;
  private $threshold = 1024;
  private $limit = 5;
  private $size = TRUE;

  private $first_run = TRUE;

  private $rotateET = FALSE;
  private $no_rotation = FALSE;
  public $enable;
  function __construct($enable)
  {
    $this->enabled = $enable;
    $this->base_path = dirname(__FILE__);
    $this->basename = DEFAULT_LOGFILE;
    $this->threshold = 1024;
  }

  /**
   * runned the first time
   */
  private function firstRun()
  {
    if ( $this->enabled ) {
      $this->filename = DEFAULT_LOGFILE;
      $this->rotate_log();
      $this->flog = fopen($this->filename,'a+');
      $this->first_run = false;
    }
  }

  /**
   * set the filename
   */
  function setFilename($filename)
  {
    $this->basename = $filename;
  }

  /**
   * set base path (base dir)
   */
  function setBasepath($basepath)
  {
    $this->base_path = $basepath;
  }

  /**
   * set threshold for rotation, in term of number of KBytes
   * defaults to 1024 (1 MByte)
   */
  function setThreshold($threshold) {
    $this->threshold = $threshold;
  }

  /**
   * set ratate every time: every time log function is called rotate is called
   */
  function setRotateEveryTime($rotateET)
  {
    $this->rotateET = $rotateET;
  }

  /**
   * use this to disable log file name rotation ( $logger->setNoRotation(TRUE) means no rotation of file log)
   */
  function setNoRotation($rotation)
  {
    $this->no_rotation = ($rotation?FALSE:TRUE);
  }

  /**
   * rotate log $filename adding .x to end of filename
   * use this to force rotate
   */
  public function rotate_log()
  {
    if($this->no_rotation) return;
    $threshold_bytes = $this->threshold* 1024;
    $filename = $this->filename;
    if( file_exists($this->filename) && filesize($filename) >= $threshold_bytes ) {
      // rotate
      $path_info = pathinfo($filename);
      $base_directory = $path_info['dirname'];
      $base_name = $path_info['basename'];
      $num_map = array();
      foreach( new DirectoryIterator($base_directory) as $fInfo) {
        if($fInfo->isDot() || ! $fInfo->isFile()) continue;
        if (preg_match('/^'.$base_name.'\.?([0-9]*)$/',$fInfo->getFilename(), $matches) ) {
          $num = $matches[1];
          $file2move = $fInfo->getFilename();
          if ($num == '') $num = -1;
          $num_map[$num] = $file2move;
        }
      }
      krsort($num_map);
      foreach($num_map as $num => $file2move) {
        $targetN = $num+1;
        if ($targetN <= $this->limit) {
          rename($base_directory.DIRECTORY_SEPARATOR.$file2move,$filename.'.'.$targetN);
        } else {
          unlink($base_directory.DIRECTORY_SEPARATOR.$file2move);
        }
      }
    }
  }

  /**
   * write log line.
   * that is:
   * [date] PID: [[PID]] $text\n
   * where
   * [date] is date('Ymd H:i:s')
   * [PID] is getmypid() (i.e. [[PID]] would be [3123] string)
   */
  function log($text)
  {
    if ( $this->enabled ) {
      if($this->first_run) $this->firstRun();
      fwrite($this->flog,date('Ymd H:i:s')." PID: [".getmypid()."] ".$text."\n");
    }
  }

  /**
   * destructor ensure file close, use
   * unset $logger;
   * that is.
   */
  function __destruct()
  {
    if ( $this->flog ) {
      fclose($this->flog);
    }
  }

}
?>
