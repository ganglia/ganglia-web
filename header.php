<?php
session_start();

if (isset($_GET['date_only'])) {
  $d = date("r");
  echo $d;
  exit(0);
}

function make_size_menu($clustergraphsize, $context) {
  global $conf;

  $size_menu = '<SELECT NAME="z" OnChange="ganglia_form.submit();">';
      
  $size_arr = $conf['graph_sizes_keys'];
  foreach ($size_arr as $size) {
    if ($size == "default")
      continue;
    $size_menu .= "<OPTION VALUE=\"$size\"";
    if ((isset($clustergraphsize) && ($size === $clustergraphsize)) || 
	(!isset($clustergraphsize) && ($size === 'small')) || 
	(!isset($_GET['z']) && ($context == 'host') && ($size == "medium"))) {
      $size_menu .= " SELECTED";
    }
    $size_menu .= ">$size</OPTION>\n";
  }
  $size_menu .= "</SELECT>\n";
  return $size_menu;
}

function make_cols_menu() {
  global $conf;

  $cols_menu = "<SELECT NAME=\"hc\" OnChange=\"ganglia_form.submit();\">\n";

  foreach(range(0,25) as $cols) {
    $cols_menu .= "<OPTION VALUE=$cols ";
    if ($cols == $conf['hostcols'])
      $cols_menu .= "SELECTED";
    $cols_menu .= ">$cols\n";
  }
  $cols_menu .= "</SELECT>\n";
  return $cols_menu;
}

function make_metric_cols_menu() {
  global $conf;

  $metric_cols_menu = "<select name=\"mc\" OnChange=\"ganglia_form.submit();\">\n";

  foreach(range(1,25) as $metric_cols) {
    $metric_cols_menu .= "<option value=$metric_cols ";
    if ($metric_cols == $conf['metriccols'])
      $metric_cols_menu .= "selected";
    $metric_cols_menu .= ">$metric_cols\n";
  }
  $metric_cols_menu .= "</select>\n";
  return $metric_cols_menu;
}

function make_sort_menu($context, $sort) {
  $sort_menu = "";
  if ($context == "meta" or $context == "cluster") {
    $context_sorts[] = "ascending";
    $context_sorts[] = "descending";
    $context_sorts[] = "by name";

    // Show sort order options for meta context only:

    if ($context == "meta" ) {
      $context_sorts[] = "by hosts up";
      $context_sorts[] = "by hosts down";
    }

    $sort_menu = "Sorted&nbsp;&nbsp;";
    foreach ($context_sorts as $v) {
      $url = rawurlencode($v);
      if ($v == $sort)
	$checked = "checked=\"checked\"";
      else
	$checked = "";
      $sort_menu .= "<input OnChange=\"ganglia_submit();\" type=\"radio\" id=\"radio-" .str_replace(" ", "_", $v) . "\" name=\"s\" value=\"$v\" $checked/><label for=\"radio-" . str_replace(" ", "_", $v) . "\">$v</label>";
    }
  }
  return $sort_menu;
}

function make_range_menu($physical, $jobrange, $cs, $ce, $range) {
  global $conf;

  $range_menu = "";
  if (!$physical) {
    $context_ranges = array_keys($conf['time_ranges']);
    if ($jobrange)
      $context_ranges[] = "job";
    if ($cs or $ce)
      $context_ranges[] = "custom";

    $range_menu = "Last&nbsp;&nbsp;";
    foreach ($context_ranges as $v) {
      $url = rawurlencode($v);
      if ($v == $range)
	$checked = "checked=\"checked\"";
      else
	$checked = "";
      $range_menu .= "<input OnChange=\"ganglia_form.submit();\" type=\"radio\" id=\"range-$v\" name=\"r\" value=\"$v\" $checked/><label for=\"range-$v\">$v</label>";
    }
  }
  return $range_menu;
}

function make_alt_view($context, $clustername, $hostname, $get_metric_string) {
  global $conf;

  $cluster_url = rawurlencode($clustername);
  $node_url = rawurlencode($hostname);

  $alt_view = "";

  if ($context == "cluster") {
    $alt_view = "<button class=\"header_btn\" onclick=\"window.location='./?p=2&amp;c=$cluster_url';return false;\">Physical View</button>";
  } elseif ($context == "physical") {
    $alt_view = "<button class=\"header_btn\" onclick=\"window.location='./?c=$cluster_url';return false;\">Full View</button>";
  } elseif ($context=="node") {
    $alt_view = "<button class=\"header_btn\" onclick=\"window.location='./?c=$cluster_url&amp;h=$node_url&amp;$get_metric_string';return false;\">Host View</button>";
  } elseif ($context=="host") {
    $alt_view = "<button class=\"header_btn\" onclick=\"window.location='./?p=2&amp;c=$cluster_url&amp;h=$node_url';return false;\">Node View</button>";
  } elseif ($context == "views") {
    if (checkAccess(GangliaAcl::ALL_VIEWS, GangliaAcl::EDIT, $conf)) {
      $alt_view = '<button onclick="return false" id="create_view_button">Create View</button>';
      $alt_view .= '&nbsp;&nbsp;<button onclick="return false" id="delete_view_button">Delete View</button>';
    }
  }
  return $alt_view;
}

function make_node_menu($self,
			$context,
			$grid,
			$parentgrid,
			$parentlink,
			$gridstack_url,
			$clustername,
			$hostname,
			$get_metric_string,
			$showhosts,
			$hosts_up,
			$hosts_down) {
  global $conf;

  $node_menu = "";

  if ($parentgrid) {
    $node_menu .= "<b><a href=\"$parentlink?gw=back&amp;gs=$gridstack_url&amp;$get_metric_string\">" . "$parentgrid ${conf['meta_designator']}</a></b> ";
    $node_menu .= "<b>&gt;</b>\n";
  }

  # Show grid.
  if ((($self != "unspecified") && !$parentgrid) ||
      $conf['always_display_grid_view']) {
    $mygrid = ($self == "unspecified") ? "" : $self;
    $node_menu .= "<b><a href=\"./?$get_metric_string\">$mygrid ${conf['meta_designator']}</a></b> ";
    $node_menu .= "<b>&gt;</b>\n";
  }

  /////////////////////////////////////////////////////////////////////////////
  // Cluster name has been specified. It comes right after
  // Grid >
  /////////////////////////////////////////////////////////////////////////////
  if ($clustername) {
    $url = rawurlencode($clustername);
    $node_menu .= "<b><a href=\"./?c=$url&amp;$get_metric_string\">$clustername</a></b> ";
    $node_menu .= "<b>&gt;</b>\n";
    $node_menu .= hiddenvar("c", $clustername);
  } else if ($context == "decompose_graph") {
    $node_menu .= '<input type="hidden" name="dg" value="1">';
    $node_menu .= "Decompose Graph";
  } else {
    # No cluster has been specified, so drop in a list
    $node_menu .= "<select name=\"c\" OnChange=\"ganglia_form.submit();\">\n";
    $node_menu .= "<option value=\"\">--Choose a Source\n";
    ksort($grid);
    foreach ($grid as $k => $v) {
      if ($k == $self) continue;
      if (isset($v['GRID']) and $v['GRID']) {
        $url = $v['AUTHORITY'];
        $node_menu .="<option value=\"$url\">$k ${conf['meta_designator']}\n";
      } else {
        $url = rawurlencode($k);
        $node_menu .="<option value=\"$url\">$k\n";
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
      $node_menu .= "<b>No Hosts</b>\n";
    }
  } else {
    $node_menu .= "<b>$hostname</b>\n";
    $node_menu .= hiddenvar("h", $hostname);
  }
  return $node_menu;
}

# RFM - These definitions are here to eliminate "undefined variable"
# error messages in ssl_error_log.
!isset($initgrid) and $initgrid = 0;
!isset($metricname) and $metricname = "";
!isset($context_metrics) and $context_metrics = "";

if ($context == "control" && $controlroom < 0)
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
  $data->assign("page", $page);
else
  $data->assign("page","./");

#
# Used when making graphs via graph.php. Included in most URLs
#
$sort_url = rawurlencode($sort);

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

# Make some information available to templates.
$data->assign("cluster_url", $cluster_url);

$alt_view = make_alt_view($context, 
			  $clustername, 
			  $hostname, 
			  $get_metric_string);
$data->assign("alt_view", $alt_view);

# Build the node_menu
$node_menu = "";
if (($context != 'views') && ($context != 'compare_hosts')) {
  $node_menu = make_node_menu($self,
			      $context,
			      $grid,
			      $parentgrid,
			      $parentlink,
			      $gridstack_url,
			      $clustername,
			      $hostname,
			      $get_metric_string,
			      $showhosts,
			      $hosts_up,
			      $hosts_down);
  # Save other CGI variables
  if ($physical)
    $node_menu .= hiddenvar("p", $physical);
  $node_menu .= hiddenvar("cr", $controlroom);
  $node_menu .= hiddenvar("js", $jobstart);
  $node_menu .= hiddenvar("jr", $jobrange);
}
$data->assign("node_menu", $node_menu);

//////////////////// Build the metric menu ////////////////////////////////////

if (count($metrics)) {
  foreach ($metrics as $firsthost => $bar) {
      foreach ($metrics[$firsthost] as $m => $foo)
        $context_metrics[$m] = $m;
  }
  foreach ($reports as $r => $foo)
    $context_metrics[] = $r;
}

#
# If there are graphs present, show ranges.
#
$range_menu = make_range_menu($physical, $jobrange, $cs, $ce, $range);
$data->assign("range_menu", $range_menu);

#
# Only compute metric-picker options if we have some, and are in cluster context.
#
if (is_array($context_metrics) and $context == "cluster") {
  $picker_metrics = array();

  # Find all the optional reports
  if ($handle = opendir($conf['gweb_root'] . '/graph.d')) {

    // If we are using RRDtool reports can be json or PHP suffixes
    if ( $conf['graph_engine'] == "rrdtool" )
      $report_suffix = "php|json";
    else
      $report_suffix = "json";

    while (false !== ($file = readdir($handle))) {
      if ( preg_match("/(.*)(_report)\.(" . $report_suffix .")/", $file, $out) ) {
        if ( ! in_array($out[1] . "_report", $context_metrics) )
          $context_metrics[] = $out[1] . "_report";
      }
    }

    closedir($handle);
  }

  sort($context_metrics);

  foreach ($context_metrics as $key) {
    $url = rawurlencode($key);
    $picker_metrics[] = "<option value=\"$url\">$key</option>";
  }

  $data->assign("picker_metrics", join("", $picker_metrics));
  $data->assign("is_metrics_picker_disabled", "");  
} else {
  // We have to disable the sort_menu if we are not in the cluster context
  $data->assign("is_metrics_picker_disabled", '$("#sort_menu").toggle(); ');
  $data->assign("picker_metrics", "" );
}

#
# Show sort order if there is more than one physical machine present.
#
$sort_menu = make_sort_menu($context, $sort);
$data->assign("sort_menu", $sort_menu );
   
if ($context == "physical" or $context == "cluster" or $context == 'host') {
  $cols_menu = make_cols_menu();
  $size_menu = make_size_menu($clustergraphsize, $context);
}

if ($context == "host") {
  $metric_cols_menu = make_metric_cols_menu();
}

$custom_time = "";

if ( in_array($context , array ("meta", "cluster", "host", "views", "decompose_graph", "compare_hosts") ) ) {
   $examples = "Feb 27 2007 00:00, 2/27/2007, 27.2.2007, now -1 week,"
      . " -2 days, start + 1 hour, etc.";
   $custom_time = "or <span class=\"nobr\">from <input type=\"TEXT\" title=\"$examples\" NAME=\"cs\" ID=\"datepicker-cs\" SIZE=\"17\"";
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
   . '<input class="header_btn" type="SUBMIT" VALUE="Filter">'
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

$data->assign('selected_tab', htmlspecialchars($user['selected_tab']) );
$data->assign('view_name', $user['viewname']);

$additional_buttons = "";
if ($context == 'views' || $context == 'decompose_graph' || $context == 'host') {
  $additional_buttons = '<input title="Hide/Show Events" type="checkbox" id="show_all_events" onclick="showAllEvents(this.checked)"/><label for="show_all_events">Hide/Show Events</label>';
}
$data->assign('additional_buttons', $additional_buttons);

# Make sure that no data is cached..
header ("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); # Date in the past
header ("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); # always modified
header ("Cache-Control: no-cache, must-revalidate"); # HTTP/1.1
header ("Pragma: no-cache"); # HTTP/1.0

if (file_exists("./templates/${conf['template_name']}/user_header.tpl"))
  $data->assign('user_header', "1");

$dwoo->output($tpl, $data);

?>
