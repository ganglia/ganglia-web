<?php

$conf['gweb_root'] = dirname(dirname(__FILE__));

include_once $conf['gweb_root'] . "/eval_conf.php";
include_once $conf['gweb_root'] . "/lib/common_api.php";
include_once $conf['gweb_root'] . "/functions.php";

ganglia_cache_metrics();

?>
