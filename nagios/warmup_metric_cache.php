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

foreach ( $metrics as $host => $host_metrics ) {
    foreach ( $host_metrics as $name => $attributes ) {
        $new_metrics[$host][$name]['VAL'] = $metrics[$host][$name]['VAL'];
        if ( isset($metrics[$host][$name]['UNITS']) ) 
        $new_metrics[$host][$name]['UNITS'] = $metrics[$host][$name]['UNITS'];
    }
    
    # Put host metrics in their own files as well
    file_put_contents($conf['nagios_cache_file'] . "_" . $host, serialize($new_metrics[$host]));
    
}


$temp_file = $conf['nagios_cache_file'] . ".temp";
file_put_contents($temp_file, serialize($new_metrics));
rename($temp_file, $conf['nagios_cache_file']);

?>
