<?php
session_start();

if (isset($_GET['tz'])) {
  $_SESSION['tz'] = $_GET['tz'];
}

if (isset($_GET['date_only'])) {
  $d = date("r");
  echo $d;
  exit(0);
}

function make_size_menu($clustergraphsize, $context) {
  global $conf;

  $size_menu = '<select name="z" onchange="ganglia_form.submit();">';

  $size_arr = $conf['graph_sizes_keys'];
  foreach ($size_arr as $size) {
    if ($size == "default")
      continue;
    $size_menu .= "<option value=\"$size\"";
    if ((isset($clustergraphsize) && ($size === $clustergraphsize)) ||
	(!isset($clustergraphsize) && ($size === 'small')) ||
	(!isset($_GET['z']) && ($context == 'host') && ($size == "medium"))) {
      $size_menu .= " selected";
    }
    $size_menu .= ">$size</option>\n";
  }
  $size_menu .= "</select>\n";
  return $size_menu;
}

function make_cols_menu() {
  global $conf;

  $cols_menu = "<SELECT NAME=\"hc\" OnChange=\"ganglia_form.submit();\">\n";

  foreach(range(0, 25) as $cols) {
    $cols_menu .= "<OPTION VALUE=$cols ";
    if ($cols == $conf['hostcols'])
      $cols_menu .= "SELECTED";
    $cols_menu .= ">$cols\n";
  }
  $cols_menu .= "</SELECT>\n";
  return $cols_menu;
}

function make_sort_menu($context, $sort) {
  $sort_menu = "";
  if ($context == "meta" or $context == "cluster") {
    $context_sorts[] = array("ascending",
			     "Sort hosts by ascending metric value");
    $context_sorts[] = array("descending",
			     "Sort hosts by descending metric value");
    $context_sorts[] = array("by name",
			     "Sort hosts by name");

    // Show sort order options for meta context only:

    if ($context == "meta") {
      $context_sorts[] = array("by hosts up",
			       "Display hosts in UP state first");
      $context_sorts[] = array("by hosts down",
			       "Display hosts in DOWN state first");
    }

    $sort_menu = "Sorted&nbsp;&nbsp;";
    $sort_menu .= "<div id=\"sort_menu\">";
    foreach ($context_sorts as $v) {
      $label = $v[0];
      $title = $v[1];
      $url = rawurlencode($label);
      if ($label == $sort)
	$checked = "checked=\"checked\"";
      else
	$checked = "";
      $sort_menu .= "<input OnChange=\"ganglia_submit();\" type=\"radio\" id=\"radio-" . str_replace(" ", "_", $label) . "\" name=\"s\" value=\"$label\" $checked/><label title=\"$title\" for=\"radio-" . str_replace(" ", "_", $label) . "\">$label</label>";
    }
    $sort_menu .= "</div>";
  }
  return $sort_menu;
}

function make_range_menu($context_ranges, $range) {
  $range_menu = "Last&nbsp;&nbsp;<div id=\"range_menu\">";
  foreach ($context_ranges as $v) {
    if ($v == $range)
      $checked = "checked=\"checked\"";
    else
      $checked = "";
    $range_menu .= "<input OnChange=\"ganglia_form.submit();\" type=\"radio\" id=\"range-$v\" name=\"r\" value=\"$v\" $checked/><label for=\"range-$v\">$v</label>";
  }
  return $range_menu . "</div>";
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
      if ($k == $self and isset($v['GRID']) and $v['GRID']) continue;
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

if (isset($_GET["hide-hf"]) &&
    filter_input(INPUT_GET,
		 "hide-hf",
		 FILTER_VALIDATE_BOOLEAN,
		 array("flags" => FILTER_NULL_ON_FAILURE))) {
  $data->assign("hide_header", true);
}

// Server timezone used in generating pretty dates and times when zooming
$data->assign("server_timezone", date_default_timezone_get());

$data->assign("page_title", $title);
$data->assign("refresh", $conf['default_refresh']);

# Templated Logo image
$data->assign("images", "./templates/${conf['template_name']}/images");

$data->assign( "date", date("r"));

# The page to go to when "Get Fresh Data" is pressed.
if (isset($page))
  $data->assign("page", $page);
else
  $data->assign("page", "./");

#
# Used when making graphs via graph.php. Included in most URLs
#
$sort_url = rawurlencode($sort);

$get_metric_string = "m={$user['metricname']}&amp;r=$range&amp;s=$sort_url&amp;hc=${conf['hostcols']}&amp;mc=${conf['metriccols']}";
if ($jobrange and $jobstart)
    $get_metric_string .= "&amp;jr=$jobrange&amp;js=$jobstart";
if ($cs)
    $get_metric_string .= "&amp;cs=" . rawurlencode($cs);
if ($ce)
    $get_metric_string .= "&amp;ce=" . rawurlencode($ce);

# Store time ranges in javascript header
$js_time_ranges = array();
foreach ($conf['time_ranges'] as $id => $val) {
  $js_time_ranges[] = "rng_" . $id . ": " . $val;
}
$js_time_ranges = "{" . join(", ", $js_time_ranges) . "}";
$data->assign("time_ranges", $js_time_ranges);

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

  # Save other CGI variables
  $node_menu .= hiddenvar("cr", $controlroom);
  $node_menu .= hiddenvar("js", $jobstart);
  $node_menu .= hiddenvar("jr", $jobrange);
}
$data->assign("node_menu", $node_menu);

#
# If there are graphs present, show ranges.
#
$range_menu = "";
if (!$physical) {
  $context_ranges = array_keys($conf['time_ranges']);
  if ($jobrange)
    $context_ranges[] = "job";
  if ($range == "custom" && $cs && $ce)
    $context_ranges[] = "custom";
  $range_menu = make_range_menu($context_ranges, $range);
}
$data->assign("range_menu", $range_menu);

if ($context == 'meta') {
  $sort_menu = make_sort_menu($context, $sort);
  $data->assign("sort_menu", $sort_menu );
}

if ($context == "physical" or $context == "cluster" or $context == 'host') {
  $cols_menu = make_cols_menu();
  $size_menu = make_size_menu($clustergraphsize, $context);
}

if (in_array($context, array ("meta",
			      "cluster",
			      "cluster-summary",
			      "host",
			      "views",
			      "decompose_graph",
			      "compare_hosts"))) {
  #$tpl->assign("custom_time_head", $calendar_head);
  $data->assign("custom_time_head", "");
} else {
   $data->assign("custom_time_head", "");
}

$data->assign("cs", $cs ? $cs : NULL);
$data->assign("ce", $ce ? $ce : NULL);

if (isset($_SESSION['tz']) && ($_SESSION['tz'] != '')) {
  $data->assign("timezone_option", "browser");
  $data->assign("timezone_value", $_SESSION['tz']);
} else {
  $data->assign("timezone_option", "server");
  $data->assign("timezone_value", "");
}

if($conf['auth_system'] == 'enabled') {
  $data->assign('auth_system_enabled', true);
  $username = sanitize( GangliaAuth::getInstance()->getUser() );
  $data->assign('username', $username);
} else {
  $data->assign('auth_system_enabled', false);
  $data->assign('username', null);
}


if ( $conf['overlay_events'] == true ) {
  $data->assign('overlay_events', true);
}

# Check whether we should use Cubism
if ( $conf['cubism_enabled'] ) {
  $data->assign('cubism', true);
}

if ( $conf['picker_autocomplete'] == true ) {
  $data->assign('picker_autocomplete', true);
}

$data->assign('selected_tab', htmlspecialchars($user['selected_tab']) );
$data->assign('view_name', $user['viewname']);
$data->assign('conf', $conf);

# Make sure that no data is cached..
header ("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); # Date in the past
header ("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); # always modified
header ("Cache-Control: no-cache, must-revalidate"); # HTTP/1.1
header ("Pragma: no-cache"); # HTTP/1.0

if (file_exists("./templates/${conf['template_name']}/user_header.tpl"))
  $data->assign('user_header', "1");

$data->assign('context', $context);
$data->assign("metric_name", "{$user['metricname']}");

$dwoo->output($tpl, $data);

?>
