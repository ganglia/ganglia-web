<?php
require_once 'eval_conf.php';

$auth = GangliaAuth::getInstance();
$auth->destroyAuthCookie();
header("Location: index.php");
?>