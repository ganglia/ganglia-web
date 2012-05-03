<?php

###############################################################################
# There are race conditions when using metrics cache with Nagios where
# you can have multiple check script querying gmetad and writing to the 
# same file. To avoid this condition you can run this script periodically
# ie. every 10-15 seconds to populate the cache. You will find a shell
# script in this directory which runs this command on a specific schedule
###############################################################################
$conf['gweb_root'] = dirname(dirname(__FILE__));

include_once $conf['gweb_root'] . "/eval_conf.php";

$context = "cluster";
include_once $conf['gweb_root'] . "/functions.php";
include_once $conf['gweb_root'] . "/ganglia.php";
include_once $conf['gweb_root'] . "/get_ganglia.php";
# Put the serialized metrics into a file
file_put_contents($conf['nagios_cache_file'], serialize($metrics));

foreach ( $metrics as $mhost => $host_metrics ) {
    foreach ( $host_metrics as $name => $attributes ) {
        $new_metrics[$mhost][$name]['VAL'] = $metrics[$mhost][$name]['VAL'];
        if ( isset($metrics[$mhost][$name]['UNITS']) ) 
        $new_metrics[$mhost][$name]['UNITS'] = $metrics[$mhost][$name]['UNITS'];
    }
}

file_put_contents($conf['nagios_cache_file'], serialize($new_metrics));

?>
