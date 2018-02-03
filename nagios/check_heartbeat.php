<?php

##########################################################################################
# Author; Vladimir Vuksan
# This is a Ganglia Nagios plugins that checks Ganglia's last reported value which is
# akin to a "heartbeat". By default gmond will send a heartbeat every 20 seconds
# 
#
# You need to supply following GET values
#
#  host = "Hostname"
#  threshold = "Critical threshold ie. if last_reported is greater than this value
#    host is considered down"
##########################################################################################
$conf['gweb_root'] = dirname(dirname(__FILE__));

include_once $conf['gweb_root'] . "/eval_conf.php";
include_once $conf['gweb_root'] . "/functions.php";

# To turn on debug set to 1
$debug = 0;

if ( isset($_GET['host']) ) {
   $host = sanitize($_GET['host']);
   $threshold = isset($_GET['threshold']) && is_numeric($_GET['threshold']) ? $_GET['threshold'] : 25;
} else {
   die("You need to supply host and if you'd like threshold");
}
global $metrics;

if($conf['nagios_cache_enabled'] && file_exists($conf['nagios_cache_file'])){
    	// check for the cached file
    	// snag it and return it if it is still fresh
	$time_diff = time() - filemtime($conf['nagios_cache_file']);
	$expires_in = $conf['nagios_cache_time'] - $time_diff;
     	if( $time_diff < $conf['nagios_cache_time']){
		if ( $debug == 1 ) {
		  error_log("DEBUG: Fetching data from cache. Expires in " . $expires_in . " seconds.\n");
		}
     		$metrics = unserialize(file_get_contents($conf['nagios_cache_file']));
     	}
}

if ( ! is_array( $metrics ) ) {

  if ( $debug == 1 ) {
		  error_log("DEBUG: Querying GMond for new data\n");
  }
  $context = "cluster";
  include_once $conf['gweb_root'] . "/functions.php";
  include_once $conf['gweb_root'] . "/ganglia.php";
  include_once $conf['gweb_root'] . "/get_ganglia.php";
  # Massage the metrics to minimize the cache file by caching only attributes
  # we care about
  foreach ( $metrics as $mhost => $host_metrics ) {
    foreach ( $host_metrics as $name => $attributes ) {
    	$new_metrics[$mhost][$name]['VAL'] = $metrics[$mhost][$name]['VAL'];
	if ( isset($metrics[$mhost][$name]['UNITS']) ) 
    	$new_metrics[$mhost][$name]['UNITS'] = $metrics[$mhost][$name]['UNITS'];
    }
  }
  file_put_contents($conf['nagios_cache_file'], serialize($new_metrics));
  unset($metrics);
  $metrics = $new_metrics;
  unset($new_metrics);

}
# Get a list of all hosts
$ganglia_hosts_array = array_keys($metrics);
$host_found = 0;

# Find a FQDN of a supplied server name.
foreach ( $ganglia_hosts_array as $ganglia_host ) {
  if ( strpos(  $ganglia_host, $host ) !== false  ) {
    $fqdn = $ganglia_host;
    $host_found = 1;
    break;
  }
}

# Host has been found in the Ganglia tree
if ( $host_found == 1 ) {
  # Check for the existence of a metric
  if ( isset($metrics[$fqdn]['last_reported_uptime_in_sec']['VAL']) ) {
    $last_reported = $metrics[$fqdn]['last_reported_uptime_in_sec']['VAL'];
  } else {
    echo("UNKNOWN|" . $metric_name . " - Invalid metric request for this host. Please check metric exists.");
    exit(3);
  }

  if ( $metrics[$fqdn]['last_reported_uptime_in_sec']['VAL'] < $threshold  ) {
   print "OK|Last beacon received " . $metrics[$fqdn]['last_reported']['VAL'];
   exit (0);
  } else {
    print "CRITICAL|Last beacon received " . $metrics[$fqdn]['last_reported']['VAL'];
    exit (2);
  } 
  
} else {
   echo("UNKNOWN|" . $host. " - Hostname info not available. Likely invalid hostname");
   exit(3);
}

?>
