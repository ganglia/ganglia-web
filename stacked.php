<?php

//////////////////////////////////////////////////////////////////////////////
// Authors: Cal Henderson and Gilad Raphaelli
//////////////////////////////////////////////////////////////////////////////

$conf['ganglia_dir'] = dirname(__FILE__);

include_once $conf['ganglia_dir'] . "/eval_conf.php";
include_once $conf['ganglia_dir'] . "/functions.php";
include_once $conf['ganglia_dir'] . "/ganglia.php";
include_once $conf['ganglia_dir'] . "/get_ganglia.php";

$clustername = $_GET['c'];
$metricname = $_GET['m'];
$range = $_GET['r'];

$start = $conf['time_ranges'][$range];

$command = $conf['rrdtool'] . " graph - $rrd_options -E";
$command .= " --start -${start}s";
$command .= " --end N";
$command .= " --width 500";
$command .= " --height 300";
$command .= " --title '$clustername aggregated $metricname last $range'";

if (isset($_GET['x'])){ $command .= " --upper-limit '$_GET[x]'"; }
if (isset($_GET['n'])){ $command .= " --lower-limit '$_GET[n]'"; }

if (isset($_GET['x']) || isset($_GET['n'])) {
        $command .= " --rigid";
} else {
        $command .= " --upper-limit '0'";
        $command .= " --lower-limit '0'";
}

$c = 0;
$total_cmd = " CDEF:'total'=0";

# We'll get the list of hosts from here
retrieve_metrics_cache();

$counter = 0;

foreach($index_array['cluster'] as $host => $cluster ) {
    
    if ( $cluster == $clustername ) {
        $filename = $conf['rrds'] . "/$clustername/$host/$metricname.rrd";
        if (file_exists($filename)) {
            $c++;
            $command .= " DEF:'a$c'='$filename':'sum':AVERAGE";
            $total_cmd .= ",a$c,+";
        }
    }
}

$mean_cmd = " CDEF:'mean'=total,$c,/";

$first = array_shift($hosts);
$color = get_col(0);
$command .= " AREA:'a1'#$color:'$first'";

$c = 1;

foreach($hosts as $host) {
    $cx = $c/(1+count($hosts));
    $color = get_col($cx);
    $c++;
    $command .= " STACK:'a$c'#$color:'$host'";
}

$command .= " LINE1:'a1'#333";

$c = 1;
foreach($hosts as $host) {
    $c++;
    $command .= " STACK:'a$c'#000000";
}

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
        echo $command;
    }
else
    {
    header ("Content-type: image/png");
    passthru($command);
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
