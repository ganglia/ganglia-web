<?php

//////////////////////////////////////////////////////////////////////////////
// Authors: Cal Henderson and Gilad Raphaelli
//////////////////////////////////////////////////////////////////////////////
session_start();

$conf['gweb_root'] = dirname(__FILE__);

include_once $conf['gweb_root'] . "/eval_conf.php";
include_once $conf['gweb_root'] . "/functions.php";

$clustername = $_REQUEST['c'];
$metricname = $_REQUEST['m'];
$range = $_REQUEST['r'];

$cs = isset($_GET["cs"]) ?
  escapeshellcmd(htmlentities($_REQUEST["cs"])) : NULL;

$ce = isset($_GET["ce"]) ?
  escapeshellcmd(htmlentities($_REQUEST["ce"])) : NULL;

$start = ($cs and (is_numeric($cs) or strtotime($cs))) ?
  $cs : '-' . $conf['time_ranges'][$range] . 's';

$end = ($ce and (is_numeric($ce) or strtotime($ce))) ? $ce : 'N';

$command = '';
if (isset($_SESSION['tz']) && ($_SESSION['tz'] != ''))
  $command .= "TZ='" . $_SESSION['tz'] . "' ";

$command .= $conf['rrdtool'] . " graph - $rrd_options -E";
$command .= " --start '${start}'";
$command .= " --end '${end}'";
$command .= " --width 700";
$command .= " --height 300";

$title .= isset($_GET['title']) ?
  $_GET['title'] : "$clustername aggregated $metricname last $range";
$command .= " --title " . escapeshellarg($title);

if (isset($_GET['x']))
  $command .= " --upper-limit " . escapeshellarg($_GET[x]);

if (isset($_GET['n']))
  $command .= " --lower-limit " . escapeshellarg($_GET[n]);

if (isset($_GET['x']) || isset($_GET['n'])) {
  $command .= " --rigid";
} else {
  $command .= " --upper-limit '0'";
  $command .= " --lower-limit '0'";
}

if (isset($_GET['vl']))
  $command .= " --vertical-label " . escapeshellarg($_GET['vl']);

$total_cmd = " CDEF:total=0";
# The total,POP sequence is a workaround to meet the requirement that CDEFS
# must contain a DEF or CDEF
$last_total_cmd = " CDEF:last_total=total,POP,0";

# We'll get the list of hosts from here
retrieve_metrics_cache();

#####################################################################
# Keep track of maximum host length so we can neatly stack metrics
$max_len = 0;
$hosts = array();
foreach ($index_array['cluster'] as $host => $cluster_array ) {
  foreach ($cluster_array as $cluster) {
    // Check cluster name
    if ($cluster == $clustername &&
	file_exists($conf['rrds'] . "/$clustername/$host/$metricname.rrd")) {
      // If host regex is specified make sure it matches
      $add_host = (isset($_REQUEST["host_regex"]) &&
		   !preg_match("/" . $_REQUEST["host_regex"] . "/", $host)) ?
	FALSE : TRUE;

      if ($add_host) {
	$hosts[] = $host;
	$host_len = ($conf['strip_domainname']) ?
	  strlen(strip_domainname($host)) : strlen($host);
	$max_len = max($host_len, $max_len);
      }
    }
  }
}

// Force all hosts to be in name order
sort($hosts);

foreach ($hosts as $index => $host) {
  $rrd = $conf['rrds'] . "/$clustername/$host/$metricname.rrd";
  $command .= " DEF:a$index='$rrd':sum:AVERAGE";
  $command .= " VDEF:l$index=a$index,LAST";
  $total_cmd .= ",a$index,ADDNAN";
  $last_total_cmd .= ",l$index,ADDNAN";
}

$num_hosts = count($hosts);
$mean_cmd = " CDEF:mean=total,$num_hosts,/";
$last_mean_cmd = " CDEF:last_mean=last_total,$num_hosts,/";

foreach ($hosts as $index =>  $host) {
  if ($index == 0) {
    $gtype = "AREA";
    $color = get_col(0);
  } else {
    $gtype = "STACK";
    $cx = $index / (1 + count($hosts));
    $color = get_col($cx);
  }
  if ($conf['strip_domainname'])
    $host = strip_domainname($host);
  $command .= " $gtype:a$index#$color:'" .
    str_pad($host, $max_len + 1, ' ', STR_PAD_RIGHT) . "'";
}

$command = sanitize($command);
$command .= $total_cmd . $mean_cmd . $last_total_cmd . $last_mean_cmd;
$command .= " COMMENT:'\\j'";
$command .= " GPRINT:'total':AVERAGE:'Avg Total\: %5.2lf'";
$command .= " GPRINT:'last_total':LAST:'Current Total\: %5.2lf\\c'";
$command .= " GPRINT:'mean':AVERAGE:'Avg Average\: %5.2lf'";
$command .= " GPRINT:'last_mean':AVERAGE:'Current Average\: %5.2lf\\c'";

header ("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header ("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header ("Cache-Control: no-cache, must-revalidate");
header ("Pragma: no-cache");

if (isset($_GET['debug'])) {
  header ("Content-type: text/plain");
  echo ($command);
} else {
  header ("Content-type: image/png");
  my_passthru($command);
}

function HSV_TO_RGB ($H, $S, $V) {
  if ($S == 0) {
    $R = $G = $B = $V * 255;
  } else {
    $var_H = $H * 6;
    $var_i = floor( $var_H );
    $var_1 = $V * ( 1 - $S );
    $var_2 = $V * ( 1 - $S * ( $var_H - $var_i ) );
    $var_3 = $V * ( 1 - $S * (1 - ( $var_H - $var_i ) ) );

    if ($var_i == 0) {
      $var_R = $V;
      $var_G = $var_3;
      $var_B = $var_1;
    } else if ($var_i == 1) {
      $var_R = $var_2;
      $var_G = $V;
      $var_B = $var_1;
    } else if ($var_i == 2) {
      $var_R = $var_1;
      $var_G = $V;
      $var_B = $var_3;
    } else if ($var_i == 3) {
      $var_R = $var_1;
      $var_G = $var_2;
      $var_B = $V;
    } else if ($var_i == 4) {
      $var_R = $var_3;
      $var_G = $var_1;
      $var_B = $V;
    } else if ($var_i == 5) {
      $var_R = $V;
      $var_G = $var_1;
      $var_B = $var_2;
    } else {
      return array(255, 255, 255);
    }

    $R = $var_R * 255;
    $G = $var_G * 255;
    $B = $var_B * 255;
  }

  return array($R, $G, $B);
}

function get_col($value) {
  list($r, $g, $b) = HSV_TO_RGB($value, 1, 0.9);

  return sprintf('%02X%02X%02X', $r, $g, $b);
}

?>
