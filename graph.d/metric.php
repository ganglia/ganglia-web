<?php

// This report is used for specific metric graphs at the bottom of the
// cluster_view page.

/* Pass in by reference! */
function graph_metric ( &$rrdtool_graph ) {

    global $conf,
           $context,
           $jobstart,
           $load_color,
           $max,
           $metricname,
           $metrictitle,
           $min,
           $range,
           $rrd_dir,
           $size,
           $summary,
           $value,
           $vlabel;

    if ($conf['strip_domainname']) {
        $hostname = strip_domainname($GLOBALS['hostname']);
    } else {
        $hostname = $GLOBALS['hostname'];
    }

    $rrdtool_graph['height'] += 0; //no fudge needed
    $rrdtool_graph['extras'] = '';

    if ($size == 'medium') {
       $rrdtool_graph['extras']        .= ($conf['graphreport_stats'] == true) ? ' --font LEGEND:7' : '';
    } else if ($size == 'large') {
       $rrdtool_graph['extras']        .= ($conf['graphreport_stats'] == true) ? ' --font LEGEND:10' : '';
    }

    switch ($context) {

        case 'host':

            if ($summary) {
                $rrdtool_graph['title'] = $hostname;
                $prefix = $metricname;
            } else {
                $prefix = $hostname;
                if ($metrictitle) {
                   $rrdtool_graph['title'] = $metrictitle;
                } else {
                   $rrdtool_graph['title'] = $metricname;
                }
            }

            $prefix = $summary ? $metricname : $hostname;
            $value = ($value > 1000)
                        ? number_format($value)
                        : number_format($value, 2);

            if ($range == 'job') {
                $hrs = intval (-$jobrange / 3600);
                $subtitle = "$prefix last ${hrs} (now $value)";
            } else {
                if ($summary) {
                   $subtitle_one = "$metricname last $range";
                } else {
                   $subtitle_one = "$hostname last $range";
                }
                $subtitle_two = "  (now $value)";
            }

            break;

        case 'meta':
            $rrdtool_graph['title'] = "${conf['meta_designator']} ". $rrdtool_graph['title'] ."last $range";
            break;

        case 'grid':
            $rrdtool_graph['title'] = "${conf['meta_designator']} ". $rrdtool_graph['title'] ."last $range";
            break;

        case 'cluster':
            $rrdtool_graph['title'] = "$clustername "    . $rrdtool_graph['title'] ."last $range";
            break;

        default:
            if ($size == 'small') {
                $rrdtool_graph['title'] = $hostname;
            } else if ($summary) {
                $rrdtool_graph['title'] = $hostname;
            } else {
                $rrdtool_graph['title'] = $metricname;
            }
            break;

    }

    if ($load_color)
        $rrdtool_graph['color'] = "BACK#'$load_color'";

    if (isset($max) && is_numeric($max))
        $rrdtool_graph['upper-limit'] = $max;

    if (isset($min) && is_numeric($min))
        $rrdtool_graph['lower-limit'] = $min;

    if ($vlabel) {
        // We should set $vlabel **even if it isn't used** for spacing
        // and alignment reasons.  This is mostly for aesthetics.
        $temp_vlabel = trim($vlabel);
        $rrdtool_graph['vertical-label'] = strlen($temp_vlabel)
                   ?  $temp_vlabel
                   :  ' ';
    } else {
        $rrdtool_graph['vertical-label'] = $metricname;
    }

    //# the actual graph...
    $series  = "DEF:'sum'='$rrd_dir/$metricname.rrd:sum':AVERAGE ";
    $series .= "AREA:'sum'#${conf['default_metric_color']}:'$subtitle_one   '";

    if ($conf['graphreport_stats'] == false) {
        $series .= ":STACK: COMMENT:'$subtitle_two\\l'";
    }
    $series .= " ";

    if ($size == 'small') {
        $eol2        = '\\l';
    } else {
        $eol2        = '';
    }

    if($conf['graphreport_stats'] == true) {

        $series .= "CDEF:sum_pos=sum,0,LT,0,sum,IF "
                . "VDEF:sum_last=sum_pos,LAST "
                . "VDEF:sum_min=sum_pos,MINIMUM "
                . "VDEF:sum_avg=sum_pos,AVERAGE "
                . "VDEF:sum_max=sum_pos,MAXIMUM "
                . "GPRINT:'sum_last':'Now\:%7.2lf%s' "
                . "GPRINT:'sum_min':'Min\:%7.2lf%s${eol2}' "
                . "GPRINT:'sum_avg':'Avg\:%7.2lf%s' "
                . "GPRINT:'sum_max':'Max\:%7.2lf%s\\l' ";
    }

    if ($jobstart) {
        $series .= "VRULE:$jobstart#${conf['jobstart_color']} ";
    }

    // If metric is not present we are likely not collecting it on this
    // host therefore we should not attempt to build anything and will likely end up with a broken
    // image. To avoid that we'll make an empty image
    if ( !file_exists("$rrd_dir/$metricname.rrd") ) 
      $rrdtool_graph[ 'series' ] = 'HRULE:1#FFCC33:"No matching metrics detected"';   
    else
      $rrdtool_graph[ 'series' ] = $series;

    return $rrdtool_graph;

}

?>
