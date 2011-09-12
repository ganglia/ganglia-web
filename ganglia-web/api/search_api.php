<?php

/////////////////////////////////////////////////////////////////////////////
// This API allows you to get cluster name for a hostname
/////////////////////////////////////////////////////////////////////////////

require_once("../cache.php");


if ( isset($_GET['hostname']) and isset($_GET['search_type']) and $_GET['search_type'] == "exact") {
    $hostname = $_GET['hostname'];
    if ( isset($index_array['cluster'][$hostname])) {
        print $index_array['cluster'][$hostname];   
    }
} else {
    print "No valid search provided";
}

?>