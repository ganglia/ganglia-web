<?php

//////////////////////////////////////////////////////////////////////////////
// Authors: Cal Henderson and Gilad Raphaelli
//////////////////////////////////////////////////////////////////////////////

$conf['gweb_root'] = dirname(__FILE__);

include_once $conf['gweb_root'] . "/eval_conf.php";
include_once $conf['gweb_root'] . "/functions.php";

$clustername = $_REQUEST['c'];
$metricname = $_REQUEST['m'];
$range = $_REQUEST['r'];

$start = $conf['time_ranges'][$range];

$command = $conf['rrdtool'] . " graph - $rrd_options -E";
$command .= " --start -${start}s";
$command .= " --end N";
$command .= " --width 700";
$command .= " --height 300";
if (isset($_GET['title'])) {
  $command .= " --title " . escapeshellarg($_GET['title']);
} else {
  $command .= " --title " . escapeshellarg("$clustername aggregated $metricname last $range");
}

if (isset($_GET['x'])) { $command .= " --upper-limit " . escapeshellarg($_GET[x]); }
if (isset($_GET['n'])) { $command .= " --lower-limit " . escapeshellarg($_GET[n]); }

if (isset($_GET['x']) || isset($_GET['n'])) {
        $command .= " --rigid";
} else {
        $command .= " --upper-limit '0'";
        $command .= " --lower-limit '0'";
}

if (isset($_GET['vl'])) {
        $command .= " --vertical-label " . escapeshellarg($_GET['vl']);
}

$total_cmd = " CDEF:'total'=0";

# We'll get the list of hosts from here
retrieve_metrics_cache();

unset($hosts);
#####################################################################
# Keep track of maximum host length so we can neatly stack metrics
$max_len = 0;

foreach($index_array['cluster'] as $host => $cluster_array ) {

    foreach ( $cluster_array as $index => $cluster ) {
        // Check cluster name
        if ( $cluster == $clustername ) {
            // If host regex is specified make sure it matches
            if ( isset($_REQUEST["host_regex"] ) ) {
              if ( preg_match("/" . $_REQUEST["host_regex"] . "/", $host ) ) {
                $hosts[] = $host;
              }
            } else {
                $hosts[] = $host;
            }

            #
            if ($conf['strip_domainname'])
              $host_len = strlen(strip_domainname($host));
            else
              $host_len = strlen($host);
            $max_len = max($host_len, $max_len);
        }
    }
}

// Force all hosts to be in name order
sort($hosts);

foreach ( $hosts as $index => $host ) {
        $filename = $conf['rrds'] . "/$clustername/$host/$metricname.rrd";
        if (file_exists($filename)) {
            $command .= " DEF:'a$index'='$filename':'sum':AVERAGE";
            $total_cmd .= ",a$index,ADDNAN";
            $c++;
        } else {
            // Remove host from the list if the metric doesn't exist to
            // avoid unsightly broken stacked graphs.
            unset($hosts[$index]);
        }
}

$mean_cmd = " CDEF:'mean'=total,$index,/";

$first_color = get_col(0);
$min_index = min(array_keys($hosts));

foreach($hosts as $index =>  $host) {
    $cx = $i/(1+count($hosts));
    $i++;
    $color = get_col($cx);
    if ($conf['strip_domainname'])
         $host = strip_domainname($host);
    if ( $index != $min_index )
       $command .= " STACK:'a$index'#$color:'".str_pad($host, $max_len + 1, ' ', STR_PAD_RIGHT)."'";
    else
       $command .= " AREA:'a$index'#$first_color:'".str_pad($host, $max_len + 1, ' ', STR_PAD_RIGHT)."'";

    $c++;
}

#$command .= " LINE1:'a0'#333";

$c = 1;
foreach($hosts as $index => $host) {
    #if ( $index != 0 )
#       $command .= " STACK:'a$index'#000000";
    $c++;
}

$command = sanitize($command);
$command .= $total_cmd . $mean_cmd;
$command .= " COMMENT:'\\j'";
$command .= " GPRINT:'total':AVERAGE:'Avg Total\: %5.2lf'";
$command .= " GPRINT:'total':LAST:'Current Total\: %5.2lf\\c'";
$command .= " GPRINT:'mean':AVERAGE:'Avg Average\: %5.2lf'";
$command .= " GPRINT:'mean':LAST:'Current Average\: %5.2lf\\c'";

header ("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header ("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header ("Cache-Control: no-cache, must-revalidate");
header ("Pragma: no-cache");

if (isset($_GET['debug']))
    {
        header ("Content-type: text/plain");
        echo ($command);
    }
else
    {
    header ("Content-type: image/png");
    my_passthru($command);
    }

function HSV_TO_RGB ($H, $S, $V){

    if($S == 0){
        $R = $G = $B = $V * 255;
    }else{
        $var_H = $H * 6;
        $var_i = floor( $var_H );
        $var_1 = $V * ( 1 - $S );
        $var_2 = $V * ( 1 - $S * ( $var_H - $var_i ) );
        $var_3 = $V * ( 1 - $S * (1 - ( $var_H - $var_i ) ) );

             if ($var_i == 0) { $var_R = $V     ; $var_G = $var_3 ; $var_B = $var_1 ; }
        else if ($var_i == 1) { $var_R = $var_2 ; $var_G = $V     ; $var_B = $var_1 ; }
        else if ($var_i == 2) { $var_R = $var_1 ; $var_G = $V     ; $var_B = $var_3 ; }
        else if ($var_i == 3) { $var_R = $var_1 ; $var_G = $var_2 ; $var_B = $V     ; }
        else if ($var_i == 4) { $var_R = $var_3 ; $var_G = $var_1 ; $var_B = $V     ; }
        else if ($var_i == 5) { $var_R = $V     ; $var_G = $var_1 ; $var_B = $var_2 ; }
        else { return array(255, 255, 255); }

        $R = $var_R * 255;
        $G = $var_G * 255;
        $B = $var_B * 255;
    }

    return array($R, $G, $B);
}

function get_col($value){
    list($r,$g,$b) = HSV_TO_RGB($value, 1, 0.9);

    return sprintf('%02X%02X%02X',$r,$g,$b);
}

?>
