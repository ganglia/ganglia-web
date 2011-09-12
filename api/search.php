<?php

/////////////////////////////////////////////////////////////////////////////
// This API allows you to get cluster name for a hostname
//
// Currently only query supported is
//
//
// hostname=YOURHOSTNAME&search_type=exact
//
// will give you back the name of the cluster where host is in
/////////////////////////////////////////////////////////////////////////////

$conf['ganglia_dir'] = dirname(dirname(__FILE__));

include_once $conf['ganglia_dir'] . "/eval_conf.php";
include_once $conf['ganglia_dir'] . "/lib/common_api.php";
include_once $conf['ganglia_dir'] . "/lib/cache.php";
ganglia_cache_metrics();

if ( isset($_GET['hostname']) and isset($_GET['search_type']) and $_GET['search_type'] == "exact") {
    $hostname = $_GET['hostname'];
    if ( isset($index_array['cluster'][$hostname])) {
        api_return_ok($index_array['cluster'][$hostname]);
    } else {
        api_return_error("Cluster not found");
    }
} else {
    api_return_error("No valid search provided");
}

?>
