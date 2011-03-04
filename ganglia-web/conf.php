<?php
if( strstr($_SERVER['PHP_SELF'],'/ganglia/g1') !== false ) {
  require_once 'conf.php-1';
} else {
  require_once 'conf.php-2';
}
?>