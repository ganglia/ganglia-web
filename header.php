<?php
session_start();

if (isset($_GET['date_only'])) {
  $d = date("r");
  echo $d;
  exit(0);
}

# RFM - These definitions are here to eliminate "undefined variable"
# error messages in ssl_error_log.
!isset($initgrid) and $initgrid = 0;
!isset($metricname) and $metricname = "";
!isset($context_metrics) and $context_metrics = "";

if ( $context == "control" && $controlroom < 0 )
      $header = "header-nobanner";
else
      $header = "header";

#
# sacerdoti: beginning of Grid tree state handling
#
$me = $self . "@";
array_key_exists($self, $grid) and $me = $me . $grid[$self]['AUTHORITY'];
if ($initgrid)
   {
      $gridstack = array();
      $gridstack[] = $me;
   }
else if ($gridwalk=="fwd")
   {
      # push our info on gridstack, format is "name@url>name2@url".
      if (end($gridstack) != $me)
         {
            $gridstack[] = $me;
         }
   }
else if ($gridwalk=="back")
   {
      # pop a single grid off stack.
      if (end($gridstack) != $me)
         {
            array_pop($gridstack);
         }
   }
$gridstack_str = join(">", $gridstack);
$gridstack_url = rawurlencode($gridstack_str);

if (strstr($clustername, "http://")) {
   header("Location: $clustername?gw=fwd&amp;gs=$gridstack_url");
}

if ($initgrid or $gridwalk)
   {
      # Use cookie so we dont have to pass gridstack around within this site.
      # Cookie values are automatically urlencoded. Expires in a day.
      if ( !isset($_COOKIE["gs"]) or $_COOKIE["gs"] != $gridstack_str )
            setcookie("gs", $gridstack_str, time() + 86400);
   }

# Invariant: back pointer is second-to-last element of gridstack. Grid stack
# never has duplicate entries.
# RFM - The original line caused an error when count($gridstack) = 1. This
# should fix that.
$parentgrid = $parentlink = NULL;
if(count($gridstack) > 1) {
  list($parentgrid, $parentlink) = explode("@", $gridstack[count($gridstack)-2]);
}

$tpl = new Dwoo_Template_File( template("$header.tpl") );
$data = new Dwoo_Data();

// Server offset used in generating pretty dates and times when zooming
$data->assign("server_utc_offset", date('Z'));
//
$data->assign("page_title", $title);
$data->assign("refresh", $conf['default_refresh']);

# Templated Logo image
$data->assign("images","./templates/${conf['template_name']}/images");

$data->assign( "date", date("r"));

# The page to go to when "Get Fresh Data" is pressed.
if (isset($page))
      $data->assign("page",$page);
else
      $data->assign("page","./");

#
# Used when making graphs via graph.php. Included in most URLs
#
$sort_url=rawurlencode($sort);
$get_metric_string = "m=$metricname&amp;r=$range&amp;s=$sort_url&amp;hc=${conf['hostcols']}&amp;mc=${conf['metriccols']}";
if ($jobrange and $jobstart)
    $get_metric_string .= "&amp;jr=$jobrange&amp;js=$jobstart";
if ($cs)
    $get_metric_string .= "&amp;cs=" . rawurlencode($cs);
if ($ce)
    $get_metric_string .= "&amp;ce=" . rawurlencode($ce);

$start_timestamp = null;
$end_timestamp = null;
if ($cs) {
    if (! is_numeric($cs)) {
        $start_timestamp = strtotime($cs);
    } else {
        $start_timestamp = $cs;
    }

    if ($ce) {
        if (! is_numeric($ce)) {
            $end_timestamp = strtotime($ce);
        } else {
            $end_timestamp = $ce;
        }
    } else {
        $end_timestamp = $start_timestamp - $conf['time_ranges'][$range];
    }
} else {
    $end_timestamp = time();
    $start_timestamp = $end_timestamp - $conf['time_ranges'][$range];
}

$data->assign("start_timestamp", $start_timestamp);
$data->assign("end_timestamp", $end_timestamp);

# Set the Alternate view link.
$cluster_url=rawurlencode($clustername);
$node_url=rawurlencode($hostname);

# Make some information available to templates.
$data->assign("cluster_url", $cluster_url);
$alt_view = "";

if ($context == "cluster") {
   $alt_view = "<a href=\"./?p=2&amp;c=$cluster_url\">Physical View</a>";
} elseif ($context == "physical") {
   $alt_view = "<a href=\"./?c=$cluster_url\">Full View</a>";
} elseif ($context=="node") {
   $alt_view = "<a href=\"./?c=$cluster_url&amp;h=$node_url&amp;$get_metric_string\">Host View</a>";
} elseif ($context=="host") {
   $alt_view = "<a href=\"./?p=2&amp;c=$cluster_url&amp;h=$node_url\">Node View</a>";
} elseif ( $context == "views") {
   if(  checkAccess( GangliaAcl::ALL_VIEWS, GangliaAcl::EDIT, $conf ) ) {
       $alt_view = '<button onclick="return false" id="create_view_button">Create View</button>';
   }
}

$data->assign("alt_view", $alt_view);

# Build the node_menu
$node_menu = "";
if (($context != 'views') && ($context != 'compare_hosts')) {
  if ($parentgrid) {
    $node_menu .= "<B><A HREF=\"$parentlink?gw=back&amp;gs=$gridstack_url&amp;$get_metric_string\">". "$parentgrid $meta_designator</A></B> ";
    $node_menu .= "<B>&gt;</B>\n";
  }

  # Show grid.
  if ((($self != "unspecified") && !$parentgrid) ||
      $conf['always_display_grid_view']) {
    $mygrid = ($self == "unspecified") ? "" : $self;
    $node_menu .= "<B><A HREF=\"./?$get_metric_string\">$mygrid $meta_designator</A></B> ";
    $node_menu .= "<B>&gt;</B>\n";
  }

  if ($physical)
    $node_menu .= hiddenvar("p", $physical);

  /////////////////////////////////////////////////////////////////////////////
  // Cluster name has been specified. It comes right after
  // Grid >
  /////////////////////////////////////////////////////////////////////////////
  if ( $clustername ) {
    $url = rawurlencode($clustername);
    $node_menu .= "<b><a href=\"./?c=$url&amp;$get_metric_string\">$clustername</a></b> ";
    $node_menu .= "<b>&gt;</b>\n";
    $node_menu .= hiddenvar("c", $clustername);
  } else if ($context == "decompose_graph") {
    $node_menu .= '<input type="hidden" name="dg" value="1">';
    $node_menu .= "Decompose Graph";
  }  else {
    # No cluster has been specified, so drop in a list
    $node_menu .= "<select name=\"c\" OnChange=\"ganglia_form.submit();\">\n";
    $node_menu .= "<option value=\"\">--Choose a Source\n";
    ksort($grid);
    foreach ($grid as $k => $v) {
      if ($k == $self) continue;
      if (isset($v['GRID']) and $v['GRID']) {
        $url = $v['AUTHORITY'];
        $node_menu .="<OPTION VALUE=\"$url\">$k $meta_designator\n";
      } else {
        $url = rawurlencode($k);
        $node_menu .="<OPTION VALUE=\"$url\">$k\n";
      }
    }
    $node_menu .= "</select>\n";
  }

  /////////////////////////////////////////////////////////////////////////////
  // We are in the cluster view pop up a list box of nodes
  /////////////////////////////////////////////////////////////////////////////
  if ($clustername && !$hostname) {
    # Drop in a host list if we have hosts
    if (!$showhosts) {
      $node_menu .= "[Summary Only]";
    } elseif (is_array($hosts_up) || is_array($hosts_down)) {
      $node_menu .= "<select name=\"h\" OnChange=\"ganglia_form.submit();\">";
      $node_menu .= "<option value=\"\">--Choose a Node</option>";
      if (is_array($hosts_up)) {
        uksort($hosts_up, "strnatcmp");
        foreach ($hosts_up as $k=> $v) {
          $url = rawurlencode($k);
          $node_menu .= "<option value=\"$url\">$k\n";
        }
      }
      if (is_array($hosts_down)) {
        uksort($hosts_down, "strnatcmp");
        foreach ($hosts_down as $k=> $v) {
          $url = rawurlencode($k);
          $node_menu .= "<option value=\"$url\">$k\n";
        }
      }
      $node_menu .= "</select>\n";
    } else {
      $node_menu .= "<B>No Hosts</B>\n";
    }
  } else {
    $node_menu .= "<B>$hostname</B>\n";
    $node_menu .= hiddenvar("h", $hostname);
  }

  # Save other CGI variables
  $node_menu .= hiddenvar("cr", $controlroom);
  $node_menu .= hiddenvar("js", $jobstart);
  $node_menu .= hiddenvar("jr", $jobrange);
}
$data->assign("node_menu", $node_menu);


//////////////////// Build the metric menu ////////////////////////////////////

if( $context == "cluster" )
   {
   if (!count($metrics)) {
      echo "<h4>Cannot find any metrics for selected cluster \"$clustername\", exiting.</h4>\n";
      echo "Check ganglia XML tree (telnet ${conf['ganglia_ip']} ${conf['ganglia_port']})\n";
      exit;
   }
   $firsthost = key($metrics);
   foreach ($metrics[$firsthost] as $m => $foo)
         $context_metrics[] = $m;
   foreach ($reports as $r => $foo)
         $context_metrics[] = $r;
   }

#
# If there are graphs present, show ranges.
#
$range_menu = "";
if (!$physical) {
   $context_ranges = array_keys( $conf['time_ranges'] );
   if ($jobrange)
      $context_ranges[]="job";
   if ($cs or $ce)
      $context_ranges[]="custom";

   $range_menu = "<B>Last</B>&nbsp;&nbsp;";
   foreach ($context_ranges as $v) {
      $url=rawurlencode($v);
      if ($v == $range)
$checked = "checked=\"checked\"";
      else
$checked = "";
      $range_menu .= "<input OnChange=\"ganglia_submit();\" type=\"radio\" id=\"range-$v\" name=\"r\" value=\"$v\" $checked/><label for=\"range-$v\">$v</label>";

   }

}

$data->assign("range_menu", $range_menu);

#
# Only show metric list if we have some and are in cluster context.
#
$metric_menu = array();
if (is_array($context_metrics) and $context == "cluster")
   {

      sort($context_metrics);
      foreach( $context_metrics as $key )
         {
            $url = rawurlencode($key);
            $metric_menu[] = "\"$url\"";
         }

      $data->assign("available_metrics", join(",", $metric_menu) );
      $data->assign("is_metrics_picker_disabled", "");

   } else {
      // We have to disable the sort_menu if we are not in the cluster context
      $data->assign("is_metrics_picker_disabled", '$("#sort_menu").toggle(); ');
      $data->assign("available_metrics", "" );
   }


#
# Show sort order if there is more than one physical machine present.
#
$sort_menu = "";
if ($context == "meta" or $context == "cluster")
   {
      $context_sorts[]="ascending";
      $context_sorts[]="descending";
      $context_sorts[]="by name";

      #
      # Show sort order options for meta context only:
      #
      if ($context == "meta" ) {
          $context_sorts[]="by hosts up";
          $context_sorts[]="by hosts down";
      }


      $sort_menu = "<B>Sorted</B>&nbsp;&nbsp;";
      foreach ($context_sorts as $v) {
$url=rawurlencode($v);
if ($v == $sort)
$checked = "checked=\"checked\"";
else
$checked = "";
$sort_menu .= "<input OnChange=\"ganglia_submit();\" type=\"radio\" id=\"radio-" .str_replace(" ", "_", $v) . "\" name=\"s\" value=\"$v\" $checked/><label for=\"radio-" . str_replace(" ", "_", $v) . "\">$v</label>";

      }

   }
$data->assign("sort_menu", $sort_menu );
   
if ($context == "physical" or $context == "cluster" or $context == 'host' )
   {
      # Present a width list
      $cols_menu = "<SELECT NAME=\"hc\" OnChange=\"ganglia_form.submit();\">\n";

      foreach(range(0,25) as $cols)
         {
            $cols_menu .= "<OPTION VALUE=$cols ";
            if ($cols == $conf['hostcols'])
               $cols_menu .= "SELECTED";
            $cols_menu .= ">$cols\n";
         }
      $cols_menu .= "</SELECT>\n";

      $size_menu = '<SELECT NAME="z" OnChange="ganglia_form.submit();">';
      
      $size_arr = $conf['graph_sizes_keys'];
      foreach ($size_arr as $size) {
          if ($size == "default")
              continue;
          $size_menu .= "<OPTION VALUE=\"$size\"";
          if ( ( isset($clustergraphsize) && ($size === $clustergraphsize))
               || (!isset($clustergraphsize) && ($size === 'small' )) || ( !isset($_GET['z']) && $context == 'host' && $size == "medium" ) ) {
              $size_menu .= " SELECTED";
          }
          $size_menu .= ">$size</OPTION>\n";
      }
      $size_menu .= "</SELECT>\n";
  
      # Assign template variable in cluster view.
   }
if ($context == "host") {
   # Present a width list
   $metric_cols_menu = "<select name=\"mc\" OnChange=\"ganglia_form.submit();\">\n";

   foreach(range(1,25) as $metric_cols) {
      $metric_cols_menu .= "<option value=$metric_cols ";
      if ($metric_cols == $conf['metriccols'])
         $metric_cols_menu .= "selected";
      $metric_cols_menu .= ">$metric_cols\n";
   }
   $metric_cols_menu .= "</select>\n";
}

$custom_time = "";

if ( in_array($context , array ("meta", "cluster", "host", "views", "decompose_graph", "compare_hosts") ) ) {
   $examples = "Feb 27 2007 00:00, 2/27/2007, 27.2.2007, now -1 week,"
      . " -2 days, start + 1 hour, etc.";
   $custom_time = "&nbsp;&nbsp;or <span class=\"nobr\">from <input type=\"TEXT\" title=\"$examples\" NAME=\"cs\" ID=\"datepicker-cs\" SIZE=\"17\"";
   if ($cs)
      $custom_time .= " value=\"$cs\"";
   $custom_time .= "> to <input type=\"TEXT\" title=\"$examples\" name=\"ce\" ID=\"datepicker-ce\" SIZE=\"17\"";
   if ($ce)
      $custom_time .= " value=\"$ce\"";
   $custom_time .= "> <input type=\"submit\" value=\"Go\">\n";
   $custom_time .= "<input type=\"button\" value=\"Clear\" onclick=\"ganglia_submit(1)\"></span>\n";
#      $custom_time .= $calendar;
   $data->assign("custom_time", $custom_time);

#      $tpl->assign("custom_time_head", $calendar_head);
   $data->assign("custom_time_head", "");
} else {
   $data->assign("custom_time_head", "");
}
 
$data->assign("custom_time", $custom_time);

/////////////////////////////////////////////////////////////////////////
// Additional filter to add after the list of nodes. Only useful in
// cluster_view
/////////////////////////////////////////////////////////////////////////
if ( $context == "cluster" ) {
  if ( isset($user['host_regex']) && $user['host_regex'] != "" )
    $set_host_regex_value="value='" . $user['host_regex'] . "'";
  else
    $set_host_regex_value="";

  // In some clusters you may have thousands of hosts which may load
  // for a long time. For those cases we have the ability to display
  // only the max amount of graphs and put place holders for the rest ie.
  // it will say only print host name without an image
  $max_graphs_options = array(1000,500,200,100,50,25,20,15,10);

  if ( isset($user['max_graphs']) && is_numeric($user['max_graphs']) )
    $max_graphs = $user['max_graphs'];
  else
    $max_graphs = $conf['max_graphs'];
  
  $max_graphs_values = "<option value=0>all</option>";
  foreach ( $max_graphs_options as $key => $value ) {
      if ( $max_graphs == $value )
$max_graphs_values .= "<option selected>" . $value . "</option>";
      else
$max_graphs_values .= "<option>" . $value . "</option>";

  }

  $data->assign("additional_filter_options", 'Show only nodes matching <input name=host_regex ' .$set_host_regex_value . '>'
   . '<input class=submit_button type="SUBMIT" VALUE="Filter">'
   . '&nbsp;<span class="nobr">Max graphs to show <select onChange="ganglia_submit();" name="max_graphs">' . $max_graphs_values . '</select></span>'
    );
} else
  $data->assign("additional_filter_options", '');

if($conf['auth_system'] == 'enabled') {
  $data->assign('auth_system_enabled', true);
  $username = sanitize( GangliaAuth::getInstance()->getUser() );
  $data->assign('username', $username);
} else {
  $data->assign('auth_system_enabled', false);
  $data->assign('username', null);
}


if ( $conf['overlay_events'] == true )
  $data->assign('overlay_events', true);

$data->assign('selected_tab', $user['selected_tab']);
$data->assign('view_name', $user['viewname']);

$additional_buttons = "";
if ($context == "views" || $context == "decompose_graph") {
  $additional_buttons = '<input title="Hide/Show Events" type="checkbox" id="show_all_events" onclick="showAllEvents(this.checked)"/><label for="show_all_events">Hide/Show Events</label>';
}
$data->assign('additional_buttons', $additional_buttons);

# Make sure that no data is cached..
header ("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); # Date in the past
header ("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); # always modified
header ("Cache-Control: no-cache, must-revalidate"); # HTTP/1.1
header ("Pragma: no-cache"); # HTTP/1.0

$dwoo->output($tpl, $data);

?>
