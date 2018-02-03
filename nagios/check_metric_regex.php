<?php

##########################################################################################
# Author; Vladimir Vuksan / Jeff Buchbinder
# This is a Ganglia Nagios plugins that alerts based on values extracted from Ganglia
# It is similar to check_metric however it allows you to check multiple values and
# generate a single result. For example if you have multiple disks on the system
# you want a single check that will alert whenever 
#
# You need to supply following GET values
#
#  hreg = "Host Regular Expression"
#  checks = is a list of checks separated  with a colon. Check is defined by
#  comma delimiting following
#      metric_regex e.g. load_one, bytes_out
#      operator for critical condition e.g. less, more, equal, notequal
#      critical_value e.g. value for critical
#
#  Example would be
#
#  ?hreg=apache\tomcat&checks=disk_rootfs,more,10:disk_tmp,more,20
#
##########################################################################################
$conf['gweb_root'] = dirname(dirname(__FILE__));

include_once $conf['gweb_root'] . "/eval_conf.php";
include_once $conf['gweb_root'] . "/functions.php";

# To turn on debug set to 1
$debug = 0;

if ( isset($_GET['hreg']) && isset($_GET['checks']) ) {
   $host_reg = sanitize($_GET['hreg']);
   # Checks are : delimited
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

        foreach ( $metrics[$hostname] as $m_k => $m_v ) {
          if ( preg_match("/" . $metric_name . "/", $m_k) ) {
            $metric_value = $m_v['VAL'];
            $ganglia_units = $m_v['UNITS'];
            if ( ($operator == "less" && $metric_value > $critical_value) || ( $operator == "more" && $metric_value < $critical_value ) || ( $operator == "equal" && trim($metric_value) != trim($critical_value) ) || ( $operator == "notequal" && trim($metric_value) == trim($critical_value) ) ) {
              $results_ok[] =  "OK " . $hostname . " " . $m_k . " = " . $metric_value . " " . $ganglia_units;
            } else {
              $results_notok[] =  "CRITICAL " . $hostname . " " . $m_k . " = ". $metric_value . " " . $ganglia_units;
            }
          }
        }
      } // end of foreach ( $checks as $index => $check
   } //  end of if ( preg_match("/" . $host_reg 

} // end of foreach ( $ganglia_hosts_array as $index => $hostname ) {

if ( count( $results_notok ) == 0 ) {
	print "OK|# Services OK = " . count($results_ok);
	exit(0);
} else {
	print "CRITICAL|# Services OK = " . count($results_ok) . ", CRIT/UNK = " . count($results_notok) . " ; " . join(", ", $results_notok);
	exit(2);
}

?>
