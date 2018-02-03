<?php

include_once "./functions.php";

$cluster_designator = "Cluster Overview";

///////////////////////////////////////////////////////////////////////////////
// Determine which context we are in. Context is not specifically specified
// so we have to figure it out ie. if vn(view_name) is present it's the views
// context, if cluster name is specified without a hostname it's cluster etc.
///////////////////////////////////////////////////////////////////////////////
// Blocking malicious CGI input.
$user['clustername'] = isset($_GET["c"]) ?
    sanitize($_GET["c"]) : NULL;
$user['gridname'] = isset($_GET["G"]) ?
    sanitize($_GET["G"]) : NULL;

$user['viewname'] = '';
if ( isset($_GET["vn"]) &&  is_proper_view_name ($_GET["vn"]) ) {
    $user['viewname'] = $_GET["vn"];
}

if($conf['case_sensitive_hostnames'] == 1) {
    $user['hostname'] = isset($_GET["h"]) ?
        sanitize($_GET["h"]) : NULL;
} else {
    $user['hostname'] = isset($_GET["h"]) ?
        strtolower( sanitize($_GET["h"]) ) : NULL;
}

$user['range'] = isset( $_GET["r"] ) && in_array($_GET["r"], array_keys( $conf['time_ranges'] ) ) ?
    escapeshellcmd( rawurldecode($_GET["r"])) : NULL;
$user['metricname'] = isset($_GET["m"]) ?
    sanitize($_GET["m"]) : NULL;
$user['metrictitle'] = isset($_GET["ti"]) ?
    sanitize($_GET["ti"]) : NULL;
$user['sort'] = isset($_GET["s"]) ?
    sanitize($_GET["s"]) : NULL;
$user['controlroom'] = isset($_GET["cr"]) ?
    sanitize($_GET["cr"]): NULL;
# Default value set in conf.php, Allow URL to overrride
if (isset($_GET["hc"]))
    //TODO: shouldn't set $conf from user input.
    $conf['hostcols'] = clean_number($_GET["hc"]);
if (isset($_GET["mc"]))
    $conf['metriccols'] = clean_number($_GET["mc"]);
# Flag, whether or not to show a list of hosts
$user['showhosts'] = isset($_GET["sh"]) ?
    clean_number( $_GET["sh"] ) : NULL;
# The 'p' variable specifies the verbosity level in the physical view.
$user['physical'] = isset($_GET["p"]) ?
    clean_number( $_GET["p"] ) : NULL;
$user['tree'] = isset($_GET["t"]) ?
    sanitize($_GET["t"] ) : NULL;
# A custom range value for job graphs, in -sec.
$user['jobrange'] = isset($_GET["jr"]) ?
    clean_number( $_GET["jr"] ) : NULL;
# A red vertical line for various events. Value specifies the event time.
$user['jobstart'] = isset($_GET["js"]) ?
    clean_number( $_GET["js"] ) : NULL;
# custom start and end
$user['cs'] = isset($_GET["cs"]) ?
    sanitize($_GET["cs"]) : NULL;
$user['ce'] = isset($_GET["ce"]) ?
    sanitize($_GET["ce"]) : NULL;
# Custom step, primarily for use in exporting the raw data from graph.php
$user['step'] = isset($_GET["step"]) ?
    clean_number( $_GET["step"] ) : NULL;
# The direction we are travelling in the grid tree
$user['gridwalk'] = isset($_GET["gw"]) ?
    sanitize($_GET["gw"]) : NULL;
# Size of the host graphs in the cluster view
$user['clustergraphsize'] = isset($_GET["z"]) && in_array( $_GET[ 'z' ], $conf['graph_sizes_keys'] ) ?
    sanitize($_GET["z"]) : NULL;
# A stack of grid parents. Prefer a GET variable, default to cookie.
if (isset($_GET["gs"]) and $_GET["gs"])
    $user['gridstack'] = explode( ">", rawurldecode( $_GET["gs"] ) );
else if ( isset($_COOKIE['gs']) and $_COOKIE['gs'])
    $user['gridstack'] = explode( ">", $_COOKIE["gs"] );

if (isset($user['gridstack']) and $user['gridstack']) {
   foreach( $user['gridstack'] as $key=>$value )
      $user['gridstack'][ $key ] = sanitize( $value );
}

/////////////////////////////////////////////////////////////////////////////
// Used with to limit hosts shown
if ( isset($_GET['host_regex']) )
  $user['host_regex'] = sanitize ($_GET['host_regex']);

if ( isset($_GET['max_graphs']) && is_numeric($_GET['max_graphs'] ) )
  $user['max_graphs'] = $_GET['max_graphs'];

/////////////////////////////////////////////////////////////////////////////

$user['selected_tab'] = isset($_GET["tab"]) ? rawurldecode($_GET["tab"]) : 'm';
 
$user['compare_hosts'] = ($user['selected_tab'] == 'ch') ? 1 : NULL;

$user['decompose_graph'] = isset($_GET["dg"]) ? 1 : NULL;


# Assume we are the first grid visited in the tree if there is no gridwalk
# or gridstack is not well formed. Gridstack always has at least one element.
if ( !isset($user['gridstack']) or !strstr($user['gridstack'][0], "http://"))
    $initgrid = TRUE;

# Default values
if (!isset($conf['hostcols']) || !is_numeric($conf['hostcols'])) $conf['hostcols'] = 4;
if (!isset($conf['metriccols']) || !is_numeric($conf['metriccols'])) $conf['metriccols'] = 2;
if (!is_numeric($user['showhosts'])) $user['showhosts'] = 1;

# Filters
if(isset($_GET["choose_filter"]))
{
  $req_choose_filter = $_GET["choose_filter"];
  $user['choose_filter'] = array();
  foreach($req_choose_filter as $k_req => $v_req)
  {
    $k = sanitize ($k_req);
    $v = sanitize ($v_req);
    $user['choose_filter'][$k] = $v;
  }
}

# Set context.
#
# WARNING WARNING WARNING WARNING. If you create another context
# e.g. views, compare_hosts please make sure you add those to
# get_ganglia.php and ganglia.php otherwise you may be making
# requests to the gmetad any time you access it which will impact
# performance read make it really slow
$context = NULL;
if(!$user['clustername'] && !$user['hostname'] && $user['controlroom']) {
      $context = "control";
} else if (isset($user['tree'])) {
      $context = "tree";
} else if ( $user['compare_hosts'] ) {
      $context = "compare_hosts";
} else if ( $user['decompose_graph'] ) {
      $context = "decompose_graph";
} else if ($user['selected_tab'] == 'v') {
      $context = "views";
} else if(!$user['clustername'] and !$user['gridname'] and !$user['hostname']) {
      $context = "meta";
} else if($user['gridname']) {
      $context = "grid";
} else if ($user['clustername'] and !$user['hostname'] and $user['physical']) {
      $context = "physical";
} else if ($user['clustername'] and !$user['hostname'] and !$user['showhosts']) {
      $context = "cluster-summary";
} else if($user['clustername'] and !$user['hostname']) {
      $context = "cluster";
} else if($user['clustername'] and $user['hostname'] and $user['physical']) {
      $context = "node";
} else if($user['clustername'] and $user['hostname']) {
      $context = "host";
}

if (!$user['range'])
    $user['range'] = $conf['default_time_range'];

$end = "now";

# $conf['time_ranges'] defined in conf.php
if( $user['range'] == 'job' && isset( $user['jobrange'] ) ) {
    $start = $user['jobrange'];
} else if( isset( $conf['time_ranges'][ $user['range'] ] ) ) {
    $start = $conf['time_ranges'][ $user['range'] ] * -1 . "s";
} else {
    $start = $conf['time_ranges'][ $conf['default_time_range'] ] * -1 . "s";
}

if ($user['cs'] or $user['ce'])
    $user['range'] = "custom";

if (!$user['metricname'])
    $user['metricname'] = $conf['default_metric'];

if (!$user['sort'])
    $user['sort'] = "by name";

# Since cluster context do not have the option to sort "by hosts down" or
# "by hosts up", therefore change sort order to "descending" if previous
# sort order is either "by hosts down" or "by hosts up"
if ($context == "cluster") {
    if ($user['sort'] == "by hosts up" || $user['sort'] == "by hosts down") {
        $user['sort'] = "descending";
    }
}

// TODO: temporary step until all scripts expect $user.
extract( $user );

# A hack for pre-2.5.0 ganglia data sources.
$always_constant = array(
   "swap_total" => 1,
   "cpu_speed" => 1,
   "swap_total" => 1
);

$always_timestamp = array(
   "gmond_started" => 1,
   "reported" => 1,
   "sys_clock" => 1,
   "boottime" => 1
);

# List of report graphs
$reports = array(
   "load_report" => "load_one",
   "cpu_report" => 1,
   "mem_report" => 1,
   "network_report" => 1,
   "packet_report" => 1
);

?>
