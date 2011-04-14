<?php
require 'auth.php';
require 'functions.php';

if(!isSet($_SERVER['REMOTE_USER']) || empty($_SERVER['REMOTE_USER']) ){
  $guest = true;
} else {
  $guest = false;
  $auth = GangliaAuth::getInstance();
  $auth->setAuthCookie($_SERVER['REMOTE_USER']);
}
header("Location: index.php");
?>