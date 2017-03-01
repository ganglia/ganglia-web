<?php

/* Pass in by reference! */
function graph_disk_report ( &$rrdtool_graph ) {

    global $conf,
           $context,
           $range,
           $rrd_dir,
           $size;

    if ($conf['strip_domainname']) {
       $hostname = strip_domainname($GLOBALS['hostname']);
    } else {
       $hostname = $GLOBALS['hostname'];
    }

    $title = 'Disk';
    $rrdtool_graph['title'] = $title;
    $rrdtool_graph['lower-limit'] = '0';
    $rrdtool_graph['vertical-label'] = 'Bytes';
    $rrdtool_graph['extras'] = '--base 1024';
    $rrdtool_graph['height'] += ($size == 'medium') ? 28 : 0;

    if ( $conf['graphreport_stats'] ) {
        $rrdtool_graph['height'] += ($size == 'medium') ? 4 : 0;
        $rmspace = '\\g';
    } else {
        $rmspace = '';
    }
    $rrdtool_graph['extras'] .= ($conf['graphreport_stats'] == true) ? ' --font LEGEND:7' : '';

    if ($size == 'small') {
       $eol1 = '\\l';
       $space1 = ' ';
       $space2 = '         ';
    } else if ($size == 'medium' || $size = 'default') {
       $eol1 = '';
       $space1 = ' ';
       $space2 = '';
    } else if ($size == 'large') {
       $eol1 = '';
       $space1 = '                 ';
       $space2 = '                 ';
    }

    $gmultiplier = 1024 * 1024 * 1024;
    $gdisk_used_cdef = "CDEF:'gdisk_used'='gdisk_total','gdisk_free',-";

    if ( $conf['disk_used_color'] )
        $disk_used_color = $conf['disk_used_color'];
    else
        $disk_used_color = $conf['mem_used_color'];
    if ( $conf['disk_free_color'] )
        $disk_free_color = $conf['disk_free_color'];
    else
        $disk_free_color = $conf['mem_free_color'];

    $series = "DEF:'disk_total'='${rrd_dir}/disk_total.rrd':'sum':AVERAGE "
        ."CDEF:'gdisk_total'=disk_total,${gmultiplier},* "
        ."DEF:'disk_free'='${rrd_dir}/disk_free.rrd':'sum':AVERAGE "
        ."CDEF:'gdisk_free'=disk_free,${gmultiplier},* "
        ."$gdisk_used_cdef "
        ."AREA:'gdisk_used'#${disk_used_color}:'Used${rmspace}' ";

    if ( $conf['graphreport_stats'] ) {
        $series .= "CDEF:used_pos=gdisk_used,0,INF,LIMIT " 
                . "VDEF:used_last=used_pos,LAST "
                . "VDEF:used_min=used_pos,MINIMUM " 
                . "VDEF:used_avg=used_pos,AVERAGE " 
                . "VDEF:used_max=used_pos,MAXIMUM " 
                . "GPRINT:'used_last':'   ${space1}Now\:%6.1lf%s' "
                . "GPRINT:'used_min':'${space1}Min\:%6.1lf%s${eol1}' "
                . "GPRINT:'used_avg':'${space2}Avg\:%6.1lf%s' "
                . "GPRINT:'used_max':'${space1}Max\:%6.1lf%s\\l' ";
    }

    $series .= "STACK:'gdisk_free'#${disk_free_color}:'Avail${rmspace}' ";

    if ( $conf['graphreport_stats'] ) {
        $series .= "CDEF:free_pos=gdisk_free,0,INF,LIMIT "
                . "VDEF:free_last=free_pos,LAST "
                . "VDEF:free_min=free_pos,MINIMUM " 
                . "VDEF:free_avg=free_pos,AVERAGE " 
                . "VDEF:free_max=free_pos,MAXIMUM " 
                . "GPRINT:'free_last':' ${space1}Now\:%6.1lf%s' "
                . "GPRINT:'free_min':'${space1}Min\:%6.1lf%s${eol1}' "
                . "GPRINT:'free_avg':'${space2}Avg\:%6.1lf%s' "
                . "GPRINT:'free_max':'${space1}Max\:%6.1lf%s\\l' ";
    }

    $series .= "LINE2:'gdisk_total'#${conf['cpu_num_color']}:'Total${rmspace}' ";

    if ( $conf['graphreport_stats'] ) {
        $series .= "CDEF:total_pos=gdisk_total,0,INF,LIMIT "
                . "VDEF:total_last=total_pos,LAST "
                . "VDEF:total_min=total_pos,MINIMUM " 
                . "VDEF:total_avg=total_pos,AVERAGE " 
                . "VDEF:total_max=total_pos,MAXIMUM " 
                . "GPRINT:'total_last':' ${space1}Now\:%6.1lf%s' "
                . "GPRINT:'total_min':'${space1}Min\:%6.1lf%s${eol1}' "
                . "GPRINT:'total_avg':'${space2}Avg\:%6.1lf%s' "
                . "GPRINT:'total_max':'${space1}Max\:%6.1lf%s\\l' ";
    }

    // If metrics like mem_used and mem_shared are not present we are likely not collecting them on this
    // host therefore we should not attempt to build anything and will likely end up with a broken
    // image. To avoid that we'll make an empty image
    if ( !file_exists("$rrd_dir/disk_free.rrd") && !file_exists("$rrd_dir/disk_total.rrd") ) 
      $rrdtool_graph[ 'series' ] = 'HRULE:1#FFCC33:"No matching metrics detected"';   
    else
      $rrdtool_graph[ 'series' ] = $series;

    return $rrdtool_graph;
}

?>
