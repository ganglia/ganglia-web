<?php

##########################################################################################
# Author; Vladimir Vuksan
# This is a Ganglia Nagios plugins that alerts based on values extracted from Ganglia
# 
# This script checks whether metric value(s) are the same across a range of 
#
# You need to supply following GET values
#
#  hreg = "Host regular expression"
#  checks = "Comma delimited list of checks"
#  Example would be if you wanted to make sure number that subversion tag on all 
#  deployed files was the same
#
#  ?hreg=apache\tomcat&checks=svn_revision
#
##########################################################################################
$conf['ganglia_dir'] = dirname(dirname(__FILE__));

include_once $conf['ganglia_dir'] . "/eval_conf.php";
include_once $conf['ganglia_dir'] . "/functions.php";


# To turn on debug set to 1
$debug = 0;

$host_reg="cache-|varnish-";

if ( isset($_GET['hreg']) &&  isset($_GET['checks'])) {
   $host_reg = sanitize($_GET['hreg']);
   $check_metrics =  explode(",", sanitize($_GET['checks']));
} else {
   die("You need to supply hreg (host regex)");
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
  include_once $conf['ganglia_dir'] . "/functions.php";
  include_once $conf['ganglia_dir'] . "/ganglia.php";
  include_once $conf['ganglia_dir'] . "/get_ganglia.php";
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

$results_ok = array();
$results_notok = array();

# Initialize results array
foreach ( $check_metrics as $index => $metric_name ) {
  $results[$metric_name]["values"] = array();
}


// Loop through all hosts looking for matches
foreach ( $ganglia_hosts_array as $index => $hostname ) {

   // Find matching hosts and make sure they are alive
   if ( preg_match("/" . $host_reg  .  "/", $hostname) && ( time() - $metrics[$hostname]['last_reported_timestamp']['VAL']) < 60) {

	 // Now let's look at all the metrics
	 foreach ( $check_metrics as $index => $metric_name ) {
	    // Check for the existence of a metric
	    if ( isset($metrics[$hostname][$metric_name]['VAL']) ) {
	       $metric_value = $metrics[$hostname][$metric_name]['VAL'];
	      // First we check if we have seen the value already. If not add the value to the 
	      // values array and add the host to members array
	      if ( ! in_array($metric_value, $results[$metric_name]["values"] ) ) {
		$results[$metric_name]["values"][] = $metric_value;
	      } 
	      // We have seen the value before
	      // Find index of the value
	      $value_index = array_search($metric_value, $results[$metric_name]["values"]);
	      $results[$metric_name]["members"][$value_index][] = $hostname;


	    } // end of if ( isset($metrics[$hostname][$metric_name]['VAL']) )

	 } // end of foreach ( $check_metrics as $index => $metric_name )

   } //  end of if ( preg_match("/" . $host_reg 

} // end of foreach ( $ganglia_hosts_array as $index => $hostname ) {

$ok=true;

$output = "";

foreach ( $results as $metric_name => $metrics_results ) {
  if ( count($metrics_results["values"]) > 1 ) {
    $ok=false;
    $output .= " CRIT " . $metric_name . " differs values => ";
    foreach ( $metrics_results["values"] as $index => $value ) {
      $output .= $value . " ( "  . join(",", $metrics_results["members"][$index]) . " ) ";
    }
  } else {
    $output .= ", " .$metric_name . " same => " . count($metrics_results["members"][0]) . " nodes";
  }
}

if ( $ok === true ) {
	print "OK|" . $output;
	exit(0);
} else {
	print "CRITICAL|" . $output;
	exit(2);
}

?>
