#!/usr/bin/php
<?php

##########################################################################################
######### $Id: check_ganglia_metric.phps 111 2010-03-03 04:09:18Z vuksan $ 
# Author; Vladimir Vuksan
# Last Changed; $Date: 2010-03-02 23:09:18 -0500 (Uto, 02 Ožu 2010) $
# This is a Ganglia Nagios plugins that alerts based on values extracted from Ganglia
##########################################################################################
$GANGLIA_WEB="/var/www/html/ganglia";
##########################################################################################
# One of the bottlenecks is that to get individual metrics we query gmond which
# returns every single host and all the metrics. If you have lots of hosts and lots of 
# checks this may be quite heavy so you may want to cache data
define("CACHEDATA", 1);
define("CACHEFILE", 	"/tmp/nagios/ganglia_metrics");
define("CACHETIME",	45); // How long to cache the data in seconds

if ( $argc !=5 ) {
   echo( "Usage:  $argv[0] <hostname> <metric> <less|more|equal|notequal> <critical_value> ie.
        $argv[0] server1 disk_free less 10
	less, more and equal specify whether we mark metric critical if it is less, more or equal than critical value
Exiting ....\n");
   exit(3);
}

# To turn on debug set to 1
$debug = 0;
$host = $argv[1];
$metric_name = $argv[2];
$operator = $argv[3];
$critical_value = $argv[4];
global $metrics;

if(CACHEDATA == 1 && file_exists(CACHEFILE)){
    	// check for the cached file
    	// snag it and return it if it is still fresh
	$time_diff = time() - filemtime(CACHEFILE);
	$expires_in = CACHETIME - $time_diff;
     	if( $time_diff < CACHETIME){
		if ( $debug == 1 ) {
		  echo("DEBUG: Fetching data from cache. Expires in " . $expires_in . " seconds.\n");
		}
     		$metrics = unserialize(file_get_contents(CACHEFILE));
     	}
}

if ( ! is_array( $metrics ) ) {

  if ( $debug == 1 ) {
		  echo("DEBUG: Querying GMond for new data\n");
  }
  include_once "$GANGLIA_WEB/conf.php";
  # Set up for cluster summary
  $context = "cluster";
  include_once "$GANGLIA_WEB/functions.php";
  include_once "$GANGLIA_WEB/ganglia.php";
  include_once "$GANGLIA_WEB/get_ganglia.php";
  # Put the serialized metrics into a file
  file_put_contents(CACHEFILE, serialize($metrics));

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
  # Check for the existence of a metric
  if ( isset($metrics[$fqdn][$metric_name]['VAL']) ) {
    $metric_value = $metrics[$fqdn][$metric_name]['VAL'];
  } else {
    echo($metric_name . " UNKNOWN - Invalid metric request for this host. Please check metric exists.");
    exit(3);
  }
  
  $ganglia_units = $metrics[$fqdn][$metric_name]['UNITS'];
  
  if ( ($operator == "less" && $metric_value > $critical_value) || ( $operator == "more" && $metric_value < $critical_value ) || ( $operator == "equal" && trim($metric_value) != trim($critical_value) ) || ( $operator == "notequal" && trim($metric_value) == trim($critical_value) ) ) {
   print $metric_name . " OK - Value = " . $metric_value . " " . $ganglia_units;
   exit (0);
  } else {
    print $metric_name . " CRITICAL - Value = ". $metric_value . " " . $ganglia_units;
    exit (2);
  } 
  
} else {
   echo($metric_name . " UNKNOWN - Hostname info not available. Likely invalid hostname");
   exit(3);
}

?>
