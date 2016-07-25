<?php

/* Pass in by reference! */
function graph_cpu_report( &$rrdtool_graph ) { 

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

    $title = 'CPU';
    $rrdtool_graph['title'] = $title;
    $rrdtool_graph['upper-limit'] = '100';
    $rrdtool_graph['lower-limit'] = '0';
    $rrdtool_graph['vertical-label'] = 'Percent';
    $rrdtool_graph['height'] += ($size == 'medium') ? 28 : 0;
    $rrdtool_graph['extras'] = ($conf['graphreport_stats'] == true) ? ' --font LEGEND:7' : '';
    $rrdtool_graph['extras']  .= " --rigid";

    if ( $conf['graphreport_stats'] ) {
        $rrdtool_graph['height'] += ($size == 'medium') ? 16 : 0;
        $rmspace = '\\g';
    } else {
        $rmspace = '';
    }

    $series = '';

    // RB: Perform some formatting/spacing magic.. tinkered to fit
    //
    $eol1 = '';
    $space1 = '';
    $space2 = '';
    if ($size == 'small') {
       $eol1 = '\\l';
       $space1 = ' ';
       $space2 = '         ';
    } else if ($size == 'medium' || $size == 'default') {
       $eol1 = '';
       $space1 = ' ';
       $space2 = '';
    } else if ($size == 'large') {
       $eol1 = '';
       $space1 = '                 ';
       $space2 = '                 ';
    }

    $cpu_user_def = '';
    $cpu_user_cdef = '';
    $cpu_user_stack = '';
    $cpu_nice_def = '';
    $cpu_nice_cdef = '';
    $cpu_nice_stack = '';

    if (file_exists("$rrd_dir/cpu_user.rrd")) {
        $cpu_user_def = "DEF:'cpu_user'='${rrd_dir}/cpu_user.rrd':'sum':AVERAGE ";
        $cpu_user_cdef = "CDEF:'ccpu_user'=cpu_user,num_nodes,/ ";
    }
    if (file_exists("$rrd_dir/cpu_nice.rrd")) {
        $cpu_nice_def = "DEF:'cpu_nice'='${rrd_dir}/cpu_nice.rrd':'sum':AVERAGE ";
        $cpu_nice_cdef = "CDEF:'ccpu_nice'=cpu_nice,num_nodes,/ ";
    }

    if ($context != "host" ) {
        $series .= "DEF:'num_nodes'='${rrd_dir}/cpu_user.rrd':'num':AVERAGE ";
    }

    $series .= $cpu_user_def
            . $cpu_nice_def
            . "DEF:'cpu_system'='${rrd_dir}/cpu_system.rrd':'sum':AVERAGE "
            . "DEF:'cpu_idle'='${rrd_dir}/cpu_idle.rrd':'sum':AVERAGE ";

    if (file_exists("$rrd_dir/cpu_wio.rrd")) {
        $series .= "DEF:'cpu_wio'='${rrd_dir}/cpu_wio.rrd':'sum':AVERAGE ";
    }

    if (file_exists("$rrd_dir/cpu_steal.rrd")) {
        $series .= "DEF:'cpu_steal'='${rrd_dir}/cpu_steal.rrd':'sum':AVERAGE ";
    }

    if (file_exists("$rrd_dir/cpu_sintr.rrd")) {
        $series .= "DEF:'cpu_sintr'='${rrd_dir}/cpu_sintr.rrd':'sum':AVERAGE ";
    }

    if (file_exists("$rrd_dir/cpu_guest.rrd")) {
        $series .= "DEF:'cpu_guest'='${rrd_dir}/cpu_guest.rrd':'sum':AVERAGE ";
        // cpu_guest has already been included in cpu_user, subtract
        // if cpu_guest is unknown (not measured), just use cpu_user
        $series .= "CDEF:'cpu_guest_user'='cpu_user','cpu_guest',UN,0,'cpu_guest',IF,- ";
        $cpu_user_stack = "_guest";
    }

    if (file_exists("$rrd_dir/cpu_gnice.rrd")) {
        $series .= "DEF:'cpu_gnice'='${rrd_dir}/cpu_gnice.rrd':'sum':AVERAGE ";
        // cpu_gnice has already been included in cpu_nice, subtract
        // if cpu_gnice is unknown (not measured), just use cpu_nice
        $series .= "CDEF:'cpu_guest_nice'='cpu_nice','cpu_gnice',UN,0,'cpu_gnice',IF,- ";
        $cpu_nice_stack = "_guest";
    }

    if ($context != "host" ) {
        $series .= $cpu_user_cdef
                . $cpu_nice_cdef
                . "CDEF:'ccpu_system'=cpu_system,num_nodes,/ "
                . "CDEF:'ccpu_idle'=cpu_idle,num_nodes,/ ";

        if (file_exists("$rrd_dir/cpu_wio.rrd")) {
            $series .= "CDEF:'ccpu_wio'=cpu_wio,num_nodes,/ ";
        }

        if (file_exists("$rrd_dir/cpu_sintr.rrd")) {
            $series .= "CDEF:'ccpu_sintr'=cpu_sintr,num_nodes,/ ";
        }

        if (file_exists("$rrd_dir/cpu_steal.rrd")) {
            $series .= "CDEF:'ccpu_steal'=cpu_steal,num_nodes,/ ";
        }

        if (file_exists("$rrd_dir/cpu_guest.rrd")) {
            $series .= "CDEF:'ccpu_guest'=cpu_guest,num_nodes,/ ";
            $series .= "CDEF:'ccpu_guest_user'='ccpu_user','ccpu_guest',UN,0,'ccpu_guest',IF,- ";
        }

        if (file_exists("$rrd_dir/cpu_gnice.rrd")) {
            $series .= "CDEF:'ccpu_gnice'=cpu_gnice,num_nodes,/ ";
            $series .= "CDEF:'ccpu_guest_nice'='ccpu_nice','ccpu_gnice',UN,0,'ccpu_gnice',IF,- ";
        }

        $plot_prefix ='ccpu';
    } else {
        $plot_prefix ='cpu';
    }

    $series .= "AREA:'${plot_prefix}${cpu_user_stack}_user'#${conf['cpu_user_color']}:'User${rmspace}' ";

    if ( $conf['graphreport_stats'] ) {
        $series .= "CDEF:user_pos=${plot_prefix}_user,0,INF,LIMIT "
                . "VDEF:user_last=user_pos,LAST "
                . "VDEF:user_min=user_pos,MINIMUM "
                . "VDEF:user_avg=user_pos,AVERAGE "
                . "VDEF:user_max=user_pos,MAXIMUM "
                . "GPRINT:'user_last':'  ${space1}Now\:%5.1lf%%' "
                . "GPRINT:'user_min':'${space1}Min\:%5.1lf%%${eol1}' "
                . "GPRINT:'user_avg':'${space2}Avg\:%5.1lf%%' "
                . "GPRINT:'user_max':'${space1}Max\:%5.1lf%%\\l' ";
    }

    if (file_exists("$rrd_dir/cpu_guest.rrd")) {
        $series .= "STACK:'${plot_prefix}_guest'#${conf['cpu_guest_color']}:'Guest${rmspace}' ";

        if ( $conf['graphreport_stats'] ) {
                $series .= "CDEF:guest_pos=${plot_prefix}_guest,0,INF,LIMIT "
                        . "VDEF:guest_last=guest_pos,LAST "
                        . "VDEF:guest_min=guest_pos,MINIMUM "
                        . "VDEF:guest_avg=guest_pos,AVERAGE "
                        . "VDEF:guest_max=guest_pos,MAXIMUM "
                        . "GPRINT:'guest_last':' ${space1}Now\:%5.1lf%%' "
                        . "GPRINT:'guest_min':'${space1}Min\:%5.1lf%%${eol1}' "
                        . "GPRINT:'guest_avg':'${space2}Avg\:%5.1lf%%' "
                        . "GPRINT:'guest_max':'${space1}Max\:%5.1lf%%\\l' ";
        }
    }

    if (file_exists("$rrd_dir/cpu_nice.rrd")) {
        $series .= "STACK:'${plot_prefix}${cpu_nice_stack}_nice'#${conf['cpu_nice_color']}:'Nice${rmspace}' ";

        if ( $conf['graphreport_stats'] ) {
            $series .= "CDEF:nice_pos=${plot_prefix}_nice,0,INF,LIMIT " 
                    . "VDEF:nice_last=nice_pos,LAST "
                    . "VDEF:nice_min=nice_pos,MINIMUM "
                    . "VDEF:nice_avg=nice_pos,AVERAGE "
                    . "VDEF:nice_max=nice_pos,MAXIMUM "
                    . "GPRINT:'nice_last':'  ${space1}Now\:%5.1lf%%' "
                    . "GPRINT:'nice_min':'${space1}Min\:%5.1lf%%${eol1}' "
                    . "GPRINT:'nice_avg':'${space2}Avg\:%5.1lf%%' "
                    . "GPRINT:'nice_max':'${space1}Max\:%5.1lf%%\\l' ";
        }
    }

    if (file_exists("$rrd_dir/cpu_gnice.rrd")) {
        $series .= "STACK:'${plot_prefix}_gnice'#${conf['cpu_gnice_color']}:'G.Nice${rmspace}' ";

        if ( $conf['graphreport_stats'] ) {
                $series .= "CDEF:gnice_pos=${plot_prefix}_gnice,0,INF,LIMIT "
                        . "VDEF:gnice_last=gnice_pos,LAST "
                        . "VDEF:gnice_min=gnice_pos,MINIMUM "
                        . "VDEF:gnice_avg=gnice_pos,AVERAGE "
                        . "VDEF:gnice_max=gnice_pos,MAXIMUM "
                        . "GPRINT:'gnice_last':'${space1}Now\:%5.1lf%%' "
                        . "GPRINT:'gnice_min':'${space1}Min\:%5.1lf%%${eol1}' "
                        . "GPRINT:'gnice_avg':'${space2}Avg\:%5.1lf%%' "
                        . "GPRINT:'gnice_max':'${space1}Max\:%5.1lf%%\\l' ";
        }
    }

    $series .= "STACK:'${plot_prefix}_system'#${conf['cpu_system_color']}:'System${rmspace}' ";

    if ( $conf['graphreport_stats'] ) {
        $series .= "CDEF:system_pos=${plot_prefix}_system,0,INF,LIMIT "
                . "VDEF:system_last=system_pos,LAST "
                . "VDEF:system_min=system_pos,MINIMUM "
                . "VDEF:system_avg=system_pos,AVERAGE "
                . "VDEF:system_max=system_pos,MAXIMUM "
                . "GPRINT:'system_last':'${space1}Now\:%5.1lf%%' "
                . "GPRINT:'system_min':'${space1}Min\:%5.1lf%%${eol1}' "
                . "GPRINT:'system_avg':'${space2}Avg\:%5.1lf%%' "
                . "GPRINT:'system_max':'${space1}Max\:%5.1lf%%\\l' ";
    }

    if (file_exists("$rrd_dir/cpu_wio.rrd")) {
        $series .= "STACK:'${plot_prefix}_wio'#${conf['cpu_wio_color']}:'Wait${rmspace}' ";

        if ( $conf['graphreport_stats'] ) {
                $series .= "CDEF:wio_pos=${plot_prefix}_wio,0,INF,LIMIT "
                        . "VDEF:wio_last=wio_pos,LAST "
                        . "VDEF:wio_min=wio_pos,MINIMUM "
                        . "VDEF:wio_avg=wio_pos,AVERAGE "
                        . "VDEF:wio_max=wio_pos,MAXIMUM "
                        . "GPRINT:'wio_last':'  ${space1}Now\:%5.1lf%%' "
                        . "GPRINT:'wio_min':'${space1}Min\:%5.1lf%%${eol1}' "
                        . "GPRINT:'wio_avg':'${space2}Avg\:%5.1lf%%' "
                        . "GPRINT:'wio_max':'${space1}Max\:%5.1lf%%\\l' ";
        }
    }

    if (file_exists("$rrd_dir/cpu_steal.rrd")) {
        $series .= "STACK:'${plot_prefix}_steal'#${conf['cpu_steal_color']}:'Steal${rmspace}' ";

        if ( $conf['graphreport_stats'] ) {
                $series .= "CDEF:steal_pos=${plot_prefix}_steal,0,INF,LIMIT "
                        . "VDEF:steal_last=steal_pos,LAST "
                        . "VDEF:steal_min=steal_pos,MINIMUM "
                        . "VDEF:steal_avg=steal_pos,AVERAGE "
                        . "VDEF:steal_max=steal_pos,MAXIMUM "
                        . "GPRINT:'steal_last':' ${space1}Now\:%5.1lf%%' "
                        . "GPRINT:'steal_min':'${space1}Min\:%5.1lf%%${eol1}' "
                        . "GPRINT:'steal_avg':'${space2}Avg\:%5.1lf%%' "
                        . "GPRINT:'steal_max':'${space1}Max\:%5.1lf%%\\l' ";
        }
    }

    if (file_exists("$rrd_dir/cpu_sintr.rrd")) {
        $series .= "STACK:'${plot_prefix}_sintr'#${conf['cpu_sintr_color']}:'Sintr${rmspace}' ";

        if ( $conf['graphreport_stats'] ) {
                $series .= "CDEF:sintr_pos=${plot_prefix}_sintr,0,INF,LIMIT "
                        . "VDEF:sintr_last=sintr_pos,LAST "
                        . "VDEF:sintr_min=sintr_pos,MINIMUM "
                        . "VDEF:sintr_avg=sintr_pos,AVERAGE "
                        . "VDEF:sintr_max=sintr_pos,MAXIMUM "
                        . "GPRINT:'sintr_last':'  ${space1}Now\:%5.1lf%%' "
                        . "GPRINT:'sintr_min':'${space1}Min\:%5.1lf%%${eol1}' "
                        . "GPRINT:'sintr_avg':'${space2}Avg\:%5.1lf%%' "
                        . "GPRINT:'sintr_max':'${space1}Max\:%5.1lf%%\\l' ";
        }
    }

    $series .= "STACK:'${plot_prefix}_idle'#${conf['cpu_idle_color']}:'Idle${rmspace}' ";

    if ( $conf['graphreport_stats'] ) {
                $series .= "CDEF:idle_pos=${plot_prefix}_idle,0,INF,LIMIT "
                        . "VDEF:idle_last=idle_pos,LAST "
                        . "VDEF:idle_min=idle_pos,MINIMUM "
                        . "VDEF:idle_avg=idle_pos,AVERAGE "
                        . "VDEF:idle_max=idle_pos,MAXIMUM "
                        . "GPRINT:'idle_last':'  ${space1}Now\:%5.1lf%%' "
                        . "GPRINT:'idle_min':'${space1}Min\:%5.1lf%%${eol1}' "
                        . "GPRINT:'idle_avg':'${space2}Avg\:%5.1lf%%' "
                        . "GPRINT:'idle_max':'${space1}Max\:%5.1lf%%\\l' ";
    }

  // If metrics like cpu_user and wio are not present we are likely not collecting them on this
  // host therefore we should not attempt to build anything and will likely end up with a broken
  // image. To avoid that we'll make an empty image
  if ( !file_exists("$rrd_dir/cpu_wio.rrd") && !file_exists("$rrd_dir/cpu_user.rrd") ) 
    $rrdtool_graph[ 'series' ] = 'HRULE:1#FFCC33:"No matching metrics detected"';   
  else
    $rrdtool_graph[ 'series' ] = $series;

    return $rrdtool_graph;
}

?>
