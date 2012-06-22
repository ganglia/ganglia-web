<?php

##########################################################################################
# Author; Vladimir Vuksan
# This is a Ganglia Nagios plugins that alerts based on values extracted from Ganglia
# It is similar to check_metric however it allows you to check multiple values and
# generate a single result. For example if you have multiple disks on the system
# you want a single check that will alert whenever 
#
# You need to supply following GET values
#
#  host = "Hostname"
#  checks = is a list of checks separated  with a colon. Check is defined by
#  comma delimiting following
#  metric_name e.g. load_one, bytes_out
#  operator for critical condition e.g. less, more, equal, notequal
#  critical_value e.g. value for critical
#
#  Example would be
#
#  ?host=mytestserver.com&checks=disk_rootfs,more,10:disk_tmp,more,20
#
##########################################################################################
$conf['gweb_root'] = dirname(dirname(__FILE__));

include_once $conf['gweb_root'] . "/eval_conf.php";

# To turn on debug set to 1
$debug = 0;

if ( isset($_GET['host']) && isset($_GET['checks']) ) {
   $host = $_GET['host'];
   # Checks are : delimited
   $checks = explode(":", $_GET['checks']);
} else {
   die("You need to supply host and list of checks");
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
for ( $i = 0 ; $i < sizeof($ganglia_hosts_array) ; $i++ ) {
 if ( strpos(  $ganglia_hosts_array[$i], $host ) !== false  ) {
 $fqdn = $ganglia_hosts_array[$i];
 $host_found = 1;
 break;
 }
}

# Host has been found in the Ganglia tree
if ( $host_found == 1 ) {
   
  $results_ok = array();
  $results_notok = array();
   
  # Loop through all the checks
  foreach ( $checks as $index => $check ) {

   # Separate check into it's pieces
   $pieces = explode(",", $check);
   $metric_name = $pieces[0];
   $operator = $pieces[1];
   $critical_value = $pieces[2];
   unset($pieces);
   
   # Check for the existence of a metric
   if ( isset($metrics[$fqdn][$metric_name]['VAL']) ) {
     $metric_value = $metrics[$fqdn][$metric_name]['VAL'];
   } else {
     $results_notok[] =  "UNKNOWN " . $metric_name . " not found";
     continue;
   }
   
   $ganglia_units = $metrics[$fqdn][$metric_name]['UNITS'];
   
   if ( ($operator == "less" && $metric_value > $critical_value) || ( $operator == "more" && $metric_value < $critical_value ) || ( $operator == "equal" && trim($metric_value) != trim($critical_value) ) || ( $operator == "notequal" && trim($metric_value) == trim($critical_value) ) ) {
      $results_ok[] =  "OK " . $metric_name . " = " . $metric_value . " " . $ganglia_units;
   } else {
      $results_notok[] =  "CRITICAL " . $metric_name . " = ". $metric_value . " " . $ganglia_units;
   }
  
  } // end of foreach ( $checks as $index => $check
  
  if ( sizeof( $results_notok ) == 0 ) {
     print "OK| Num OK: " . count($results_ok);
     exit(0);
  } else {
     print "CRITICAL| Num CRIT/UNK: " . count($results_notok) . " Num OK: " . count($results_ok) .  " -- " . join(", ", $results_notok);
     exit(2);
  }
    
} else {
   echo("UNKNOWN|" . $host . " - Hostname info not available. Likely invalid hostname");
   exit(3);
}

?>
