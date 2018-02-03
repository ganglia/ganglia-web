<?php

##########################################################################################
# Author; Vladimir Vuksan
# This is a Ganglia Nagios plugins that alerts based on values extracted from Ganglia
#
# You need to supply following GET values
#
#  host = "Hostname"
#  metric_name e.g. load_one, bytes_out
#  operator for critical condition e.g. less, more, equal, notequal
#  critical_value e.g. value for critical
##########################################################################################
$conf['gweb_root'] = dirname(dirname(__FILE__));

include_once $conf['gweb_root'] . "/eval_conf.php";
include_once $conf['gweb_root'] . "/functions.php";

# To turn on debug set to 1
$debug = 0;

if ( isset($_GET['host']) && isset($_GET['metric_name']) && isset($_GET['operator']) && isset($_GET['critical_value']) ) {
   $host = sanitize($_GET['host']);
   $metric_name = sanitize($_GET['metric_name']);
   $operator = sanitize($_GET['operator']);
   $critical_value = sanitize($_GET['critical_value']);
} else {
   die("You need to supply host, metric_name, operator and critical_value");
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
    echo("UNKNOWN|" . $metric_name . " - Invalid metric request for this host. Please check metric exists.");
    exit(3);
  }
  
  $ganglia_units = $metrics[$fqdn][$metric_name]['UNITS'];
  
  if ( ($operator == "less" && $metric_value > $critical_value) || ( $operator == "more" && $metric_value < $critical_value ) || ( $operator == "equal" && trim($metric_value) != trim($critical_value) ) || ( $operator == "notequal" && trim($metric_value) == trim($critical_value) ) ) {
   print "OK|" . $metric_name . " = " . $metric_value . " " . $ganglia_units;
   exit (0);
  } else {
    print "CRITICAL|" . $metric_name . " = ". $metric_value . " " . $ganglia_units;
    exit (2);
  } 
  
} else {
   echo("UNKNOWN|" . $host. " - Hostname info not available. Likely invalid hostname");
   exit(3);
}

?>
