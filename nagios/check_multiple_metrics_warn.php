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
include_once $conf['gweb_root'] . "/functions.php";

# To turn on debug set to 1
$debug = 0;

if ( isset($_GET['host']) && isset($_GET['checks']) ) {
   $host = sanitize($_GET['host']);
   # Checks are : delimited
   $checks = explode(":", sanitize($_GET['checks']));
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
foreach ( $ganglia_hosts_array as $ganglia_host ) {
  if ( strpos(  $ganglia_host, $host ) !== false  ) {
    $fqdn = $ganglia_host;
    $host_found = 1;
    break;
  }
}

# Host has been found in the Ganglia tree
if ( $host_found == 1 ) {
   
  $results_ok = array();
  $results_warn = array();
  $results_crit = array();
   
  # Loop through all the checks
  foreach ( $checks as $index => $check ) {

   # Separate check into it's pieces
   $pieces = explode(",", $check);
   $metric_name = $pieces[0];
   $warn_operator = $pieces[1];
   $warn_value = $pieces[2];
   $critical_operator = $pieces[3];
   $critical_value = $pieces[4];
   unset($pieces);
   
   # Check for the existence of a metric
   if ( isset($metrics[$fqdn][$metric_name]['VAL']) ) {
     $metric_value = $metrics[$fqdn][$metric_name]['VAL'];
   } else {
     continue;
   }
   
   $ganglia_units = $metrics[$fqdn][$metric_name]['UNITS'];
   
   if ( ( $critical_operator == "less" && $metric_value < $critical_value) || ( $critical_operator == "more" && $metric_value > $critical_value ) || ( $critical_operator == "equal" && trim($metric_value) == trim($critical_value) ) || ( $critical_operator == "notequal" && trim($metric_value) != trim($critical_value) ) ) {
      $results_crit[] = "CRITICAL " . $metric_name . " = ". $metric_value . " " . $ganglia_units;
   } else if ( ( $warn_operator == "less" && $metric_value < $warn_value) || ( $warn_operator == "more" && $metric_value > $warn_value ) || ( $warn_operator == "equal" && trim($metric_value) == trim($warn_value) ) || ( $warn_operator == "notequal" && trim($metric_value) != trim($warn_value) ) ){
      $results_warn[] = "WARNING " . $metric_name . " = ". $metric_value . " " . $ganglia_units;
   } else {
      $results_ok[] =  "OK " . $metric_name . " = " . $metric_value . " " . $ganglia_units;
   }
  
  } // end of foreach ( $checks as $index => $check
  
  if ( count( $results_crit ) != 0 ) {
        print "CRITICAL|System check - CRIT: (" . count($results_crit) . ") WARN: (" . count($results_warn) .  ") OK: (" . count($results_ok) .  ") --" . join(",", $results_crit) .  " --" . join(",", $results_warn) .  "--" . join(",", $results_ok);
        exit(2);
  } else if ( count( $results_warn ) != 0 ) {
     print "WARNING|System check - WARN: (" . count($results_warn) . ") OK: (" . count($results_ok) .  ") --" . join(",", $results_warn) .  "--" . join(",", $results_ok);
     exit(1);
  } else if ( count( $results_ok ) != 0 ) {
        print "OK|System check - OK: (" . count($results_ok) .  ") --" . join(",", $results_ok);
        exit(0);
  } else {
        print("UNKNOWN|System check - No metrics returned values");
        exit(3);
  }
     
} else {
   echo("UNKNOWN|System check - " . $host. " - Hostname info not available. Likely invalid hostname");
   exit(3);
}
