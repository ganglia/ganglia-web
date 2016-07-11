<?php

// vim: tabstop=2:softtabstop=2:shiftwidth=2:expandtab

if ( !isset($_GET['debug']) ) {
  header("Content-Type: text/json");
}

$conf['gweb_root'] = dirname(dirname(__FILE__));

include_once $conf['gweb_root'] . "/eval_conf.php";
include_once $conf['gweb_root'] . "/functions.php";
include_once $conf['gweb_root'] . "/lib/common_api.php";

if ( isset($_GET['host']) && isset($_GET['metric_name']) ) {
   $host = $_GET['host'];
   $metric_name = $_GET['metric_name'];
} else {
   api_return_error("You need to supply host and metric_name.");
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
 if ( !strcasecmp( $ganglia_host, $host )   ) {
  $fqdn = $ganglia_host;
  $host_found = 1;
  break;
 }
}

# Host has been found in the Ganglia tree
if ( $host_found == 1 ) {
  # Check for the existence of a metric
  if ( isset($metrics[$fqdn][$metric_name]['VAL']) ) {
    $metric_value = $metrics[$fqdn][$metric_name]['VAL'];
  } else {
    api_return_error(htmlentities($metric_name) . " - Invalid metric request for this host. Please check metric exists.");
    exit(3);
  }

  $ganglia_units = $metrics[$fqdn][$metric_name]['UNITS'];

  api_return_ok(array(
      'metric_value' => $metric_value
    , 'units' => $ganglia_units
  ));
} else {
   api_return_error(htmlentities($metric_name) . " - Hostname info not available. Likely invalid hostname");
}

?>
