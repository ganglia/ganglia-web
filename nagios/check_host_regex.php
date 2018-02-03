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
#  hreg = "Hostname Regex"
#  checks = is a list of checks separated  with a colon. Check is defined by
#  comma delimiting following
#  metric_name e.g. load_one, bytes_out
#  operator for critical condition e.g. less, more, equal, notequal,totalmore,totalless
#  critical_value e.g. value for critical
#
#  Example would be
#
#  ?hreg=apache\tomcat&checks=disk_rootfs,totalmore,10:disk_tmp,more,20
#
##########################################################################################
$conf['gweb_root'] = dirname(dirname(__FILE__));

include_once $conf['gweb_root'] . "/eval_conf.php";
include_once $conf['gweb_root'] . "/functions.php";

# To turn on debug set to 1
$debug = $_GET['debug'];

if ( isset($_GET['hreg']) && isset($_GET['checks']) ) {
   $host_reg = sanitize($_GET['hreg']);
   # Checks are : delimited
   $ignore_unknowns = isset($_GET['ignore_unknowns']) && $_GET['ignore_unknowns'] == 1 ? 1 : 0;
   $checks = explode(":", sanitize($_GET['checks']));
} else {
   die("You need to supply hreg (host regex) and list of checks of format metrics,operator,critical value. Multiple checks can be supplied separated using a colon");
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

$results_ok = array();
$results_notok = array();
$metric_total_value = array();

# Loop through all hosts looking for matches
foreach ( $ganglia_hosts_array as $index => $hostname ) {

   # Find matching hosts and make sure they are alive
   if ( preg_match("/" . $host_reg  .  "/", $hostname) && ( time() - $metrics[$hostname]['last_reported_timestamp']['VAL']) < 60) {

      # Loop through all the checks
      foreach ( $checks as $index => $check ) {
   
	 # Separate check into it's pieces
	 $pieces = explode(",", $check);
	 $metric_name = $pieces[0];
	 $operator = $pieces[1];
	 $critical_value = $pieces[2];
	 unset($pieces);
	 
	 # Check for the existence of a metric
	 if ( isset($metrics[$hostname][$metric_name]['VAL'])  ) {
	   $metric_value = $metrics[$hostname][$metric_name]['VAL'];
	 } else {
	   if ( !$ignore_unknowns ) {
	    $results_notok[] =  "UNKNOWN " . $hostname . " " . $metric_name . " not found";
	   }
	   continue;
	 }
	 
	 $ganglia_units = $metrics[$hostname][$metric_name]['UNITS'];

     if ( $operator == "totalmore" || $operator == "totalless") {
         if ( isset($metric_total_value[$metric_name]) ){
             $metric_total_value[$metric_name]['VAL'] += $metric_value;
         }else{
             $metric_total_value[$metric_name]['VAL'] = $metric_value;
             $metric_total_value[$metric_name]['UNITS'] = $ganglia_units;
             $metric_total_value[$metric_name]['CRIT'] = $critical_value;
             $metric_total_value[$metric_name]['OPER'] = $operator;
         }
     }else{
	 
         if ( ($operator == "less" && $metric_value > $critical_value) || ( $operator == "more" && $metric_value < $critical_value ) || ( $operator == "equal" && trim($metric_value) != trim($critical_value) ) || ( $operator == "notequal" && trim($metric_value) == trim($critical_value) ) ) {
            $results_ok[] =  "OK " . $hostname . " " . $metric_name . " = " . $metric_value . " " . $ganglia_units;
         } else {
            $results_notok[] =  "CRITICAL " . $hostname . " " . $metric_name . " = ". $metric_value . " " . $ganglia_units;
         }
     }
     
      } // end of foreach ( $checks as $index => $check
     
   } //  end of if ( preg_match("/" . $host_reg 

} // end of foreach ( $ganglia_hosts_array as $index => $hostname ) {

# Check for total metric value
if( !empty($metric_total_value) ) {
    foreach ( $metric_total_value as $metric_name => $metric_total ) {
        if ( ( $metric_total['OPER'] == 'totalmore' && $metric_total['VAL'] < $metric_total['CRIT'] )
             || ( $metric_total['OPER'] == 'totalless' && $metric_total['VAL'] > $metric_total['CRIT'] ) ){
                 $results_ok[] = 'OK "' . $host_reg . '" Total of ' . $metric_name . ' = ' . $metric_total['VAL'] . ' ' . $metric_total['UNITS'];
             } else {
                 $results_notok[] = 'CRITICAL ' . $host_reg . ' Total of ' . $metric_name . ' = ' . $metric_total['VAL'] . ' ' . $metric_total['UNITS'];
             }
    } 
}

unset($metric_total_value);

if ( count( $results_notok ) == 0 ) {
	print "OK!# Services OK = " . count($results_ok) . " ; " . join(",", $results_ok);
	exit(0);
} else {
	print "CRITICAL!# Services OK = " . count($results_ok) . ", CRIT/UNK = " . count($results_notok) . " ; " . join(", ", $results_notok);
	exit(2);
}

?>
