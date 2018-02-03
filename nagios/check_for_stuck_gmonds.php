<?php

##########################################################################################
# Author: Vladimir Vuksan
#
# This script checks for stuck gmonds. Stuck gmonds will appear as flatlines on e.g.
# network or load graphs. Trouble is that if you are running gmetric from a cron that will
# update "heartbeat" so it will appear that machine is still up. This check looks when
# load_one metric for the host was last reported. If it's more than 180 seconds it
# considers gmond to be stuck.
# 
# Optional arguments:
# ignore_host_reg = regular expression of hosts we should ignore e.g. if you don't
# want to be alerted if gmond on test machines is down
##########################################################################################
$conf['gweb_root'] = dirname(dirname(__FILE__));

$ignore_host_reg = "";

include_once $conf['gweb_root'] . "/eval_conf.php";

# To turn on debug set to 1
$debug = 0;

$context = "cluster";
include_once $conf['gweb_root'] . "/functions.php";
include_once $conf['gweb_root'] . "/ganglia.php";
include_once $conf['gweb_root'] . "/get_ganglia.php";


# Sometimes we want to ignore certain hosts. If so supply them as a regex
if ( isset($_GET['ignore_host_reg'])  ) {
   $ignore_host_reg = sanitize($_GET['ignore_host_reg']);
} 

# Massage the metrics to minimize the cache file by caching only attributes
# we care about
$stuck_gmond_hosts = array();
foreach ( $metrics as $mhost => $host_metrics ) {
    # 
    if ( $ignore_host_reg != "" and preg_match("/" .$ignore_host_reg . "/", $mhost) ) {
	continue;
    }

    # Make sure that host is up and last time load_one was updated was over 3 minutes ago
    if ( $host_metrics['load_one']['TN'] > 180 && (( time() - $host_metrics['last_reported_timestamp']['VAL']) < 60 )) {
#    if ( $host_metrics['load_one']['TN'] > 180 ) {
	$stuck_gmond_hosts[] = $mhost;
    }
}

if ( count($stuck_gmond_hosts) == 0 ) {
   print "OK|All gmonds reporting normally. No stuck gmond hosts";
   exit (0);
} else {
    print "CRITICAL|Restart req'd on stuck gmonds => " . join(",", $stuck_gmond_hosts);
    exit (2);
}

?>
