<?php
include_once("./global.php");

function make_metric_cols_menu($conf_metriccols) {
  $metric_cols_menu = 
    "<select name=\"mc\" OnChange=\"ganglia_form.submit();\">\n";

  foreach (range(1,25) as $metric_cols) {
    $metric_cols_menu .= "<option value=$metric_cols ";
    if ($metric_cols == $conf_metriccols)
      $metric_cols_menu .= "selected";
    $metric_cols_menu .= ">$metric_cols\n";
  }
  $metric_cols_menu .= "</select>\n";
  return $metric_cols_menu;
}

$tpl = new Dwoo_Template_File( template("host_view.tpl") );
$data = new Dwoo_Data();

$data->assign("cluster", $clustername);
$data->assign("may_edit_cluster", checkAccess( $clustername,
					       GangliaAcl::EDIT,
					       $conf ) );
$data->assign("may_edit_views", checkAccess( GangliaAcl::ALL_VIEWS,
					     GangliaAcl::EDIT,
					     $conf) );
$data->assign("sort", $sort);
$data->assign("range", $range);
$data->assign("hostname", $hostname);
$data->assign("graph_engine", $conf['graph_engine']);

$graph_args = "h=$hostname&amp;$get_metric_string&amp;st=$cluster[LOCALTIME]";

function dump_var($var, $varId) {
  ob_start();
  var_dump($var);
  error_log($varId . " = ". ob_get_clean());
}

function getOptionalReports($hostname,
			    $clustername,
			    $graph_args) {
  global $conf;
  global $GRAPH_BASE_ID;
  global $SHOW_EVENTS_BASE_ID;

  $optional_reports = "";

  $cluster_url = rawurlencode($clustername);

  // If we want zoomable support on graphs we need to add correct zoomable 
  // class to every image
  $additional_cluster_img_html_args = "";
  if ( isset($conf['zoom_support']) && $conf['zoom_support'] === true )
    $additional_cluster_img_html_args = "class=cluster_zoomable";

  ///////////////////////////////////////////////////////////////////////////
  // Let's find out what optional reports are included
  // First we find out what the default (site-wide) reports are then look
  // for host specific included or excluded reports
  ///////////////////////////////////////////////////////////////////////////
  $default_reports = array("included_reports" => array(), 
			   "excluded_reports" => array());
  if ( is_file($conf['conf_dir'] . "/default.json") ) {
    $default_reports = array_merge(
      $default_reports,
      json_decode(file_get_contents($conf['conf_dir'] . "/default.json"), 
		  TRUE));
  }

  $cluster_file = $conf['conf_dir'] .
    "/cluster_" .
    str_replace(" ", "_", $clustername) .
    ".json";

  $cluster_override_reports = array("included_reports" => array(),
				    "excluded_reports" => array());

  if ($conf['optional_cluster_graphs_for_host_view']) {
    if (is_file($cluster_file)) {
      $cluster_override_reports = array_merge(
        $cluster_override_reports,
        json_decode(file_get_contents($cluster_file), TRUE));
    } 
  } 

  $host_file = $conf['conf_dir'] . "/host_" . $hostname . ".json";
  $override_reports = array("included_reports" => array(),
			    "excluded_reports" => array());
  if ( is_file($host_file) ) {
    $override_reports = array_merge(
      $override_reports, 
      json_decode(file_get_contents($host_file), TRUE));
  } else {
    // If there is no host file, look for a default cluster file
    $cluster_file = $conf['conf_dir'] . "/cluster_" . $clustername . ".json";
    if ( is_file($cluster_file) ) {
      $override_reports = array_merge(
        $override_reports, 
	json_decode(file_get_contents($cluster_file), TRUE));
    }
  }

  // Merge arrays
  $reports["included_reports"] = array_merge( 
    $default_reports["included_reports"], 
    $cluster_override_reports["included_reports"], 
    $override_reports["included_reports"]);

  $reports["excluded_reports"] = array_merge(
    $default_reports["excluded_reports"] , 
    $cluster_override_reports["excluded_reports"],  
    $override_reports["excluded_reports"]);

  // Remove duplicates
  $reports["included_reports"] = array_unique($reports["included_reports"]);
  $reports["excluded_reports"] = array_unique($reports["excluded_reports"]);

  foreach ( $reports["included_reports"] as $index => $report_name ) {
    if ( ! in_array( $report_name, $reports["excluded_reports"] ) ) {
      $graph_anchor = 
	"<a href=\"./graph_all_periods.php?$graph_args&amp;g=" . $report_name . 
	"&amp;z=large&amp;c=$cluster_url\">";

      $addMetricBtn = "<button class=\"cupid-green\" title=\"Metric Actions - Add to View, etc\" onclick=\"metricActions('{$hostname}','{$report_name}','graph','');  return false;\">+</button>";

      $csvBtn = "<button title=\"Export to CSV\" class=\"cupid-green\" onClick=\"javascript:location.href='./graph.php?$graph_args&amp;g={$report_name}&amp;z=large&amp;c=$cluster_url&amp;csv=1';return false;\">CSV</button>";

      $jsonBtn = "<button title=\"Export to JSON\" class=\"cupid-green\" onClick=\"javascript:location.href='./graph.php?$graph_args&amp;g={$report_name}&amp;z=large&amp;c=$cluster_url&amp;json=1';return false;\">JSON</button>";

      if ( $conf['graph_engine'] == "flot" ) {
	$optional_reports .= $graph_anchor . "</a>";
	$optional_reports .= '<div class="flotheader"><span class="flottitle">' . $report_name . '</span>';
	if(checkAccess(GangliaAcl::ALL_VIEWS, GangliaAcl::EDIT, $conf))
	  $optional_reports .= $addMetricBtn. '&nbsp;';
	$optional_reports .= $csvBtn . '&nbsp;' . $jsonBtn . "</div>";
	$optional_reports .= '<div id="placeholder_' . $graph_args . '&amp;g=' . $report_name .'&amp;z=medium&amp;c={$cluster_url}" class="flotgraph2 img_view"></div>';
	$optional_reports .= '<div id="placeholder_' . $graph_args . '&amp;g=' . $report_name .'&amp;z=medium&amp;c={$cluster_url}_legend" class="flotlegend"></div>';
      } else {
	$optional_reports .= "<div class='img_view'>";
	$graphId = $GRAPH_BASE_ID . $report_name;
	$inspectBtn = "<button title=\"Inspect Graph\" onClick=\"inspectGraph('{$graph_args}&amp;g={$report_name}&amp;z=large&amp;c={$cluster_url}'); return false;\" class=\"shiny-blue\">Inspect</button>";
	$showEventBtn = '<input title="Hide/Show Events" type="checkbox" id="' . $SHOW_EVENTS_BASE_ID . $report_name . '" onclick="showEvents(\'' . $graphId . '\', this.checked)"/><label class="show_event_text" for="' . $SHOW_EVENTS_BASE_ID . $report_name . '">Hide/Show Events</label>';
	if(checkAccess(GangliaAcl::ALL_VIEWS, GangliaAcl::EDIT, $conf))
	  $optional_reports .= $addMetricBtn . '&nbsp;';
	$optional_reports .= $csvBtn . '&nbsp;' . $jsonBtn . '&nbsp;' .$inspectBtn . '&nbsp;' . $showEventBtn . "<br />" . $graph_anchor . "<img id=\"" . $graphId . "\" $additional_cluster_img_html_args border=\"0\" title=\"$cluster_url\" SRC=\"./graph.php?$graph_args&amp;g=" . $report_name ."&amp;z=" . $conf['default_optional_graph_size'] . "&amp;c=$cluster_url\" style=\"margin-top:5px;\" /></a></div>";
      }
    }
  } // foreach
  return $optional_reports;
}

function getMetricGroups($metrics,
			 $always_timestamp,
			 $always_constant,
			 $hostname,
			 $baseGraphArgs,
			 $data) {
  global $conf;

  $metric_groups_initially_collapsed = 
    isset($conf['metric_groups_initially_collapsed']) ? 
    $conf['metric_groups_initially_collapsed'] : TRUE;
  $remember_open_metric_groups = 
    isset($conf['remember_open_metric_groups']) ?
    $conf['remember_open_metric_groups'] : TRUE;

  $open_groups = NULL;
  if (isset($_GET['metric_group']) && ($_GET['metric_group'] != "")) {
    $open_groups = explode ("_|_", $_GET['metric_group']);
  } else {
    if ($remember_open_metric_groups && 
	isset($_SESSION['metric_group']) && 
	($_SESSION['metric_group'] != ""))
      $open_groups = explode ("_|_", $_SESSION['metric_group']);
  }

  // Updated definition of currently open metric groups
  $new_open_groups = ""; 
  $host_metrics = 0;
  $metrics_group_data = array();

  list($metricMap, $metricGroupMap) = 
    buildMetricMaps($metrics,
		    $always_timestamp,
		    $always_constant,
		    $baseGraphArgs);

  // dump_var($metricMap, "metricMap");
  // dump_var($metricGroupMap, "metricGroupMap");

  if (is_array($metricMap) && is_array($metricGroupMap)) {
    ksort($metricGroupMap);

    if ($open_groups == NULL) {
      if ($metric_groups_initially_collapsed)
	$new_open_groups = "NOGROUPS";
      else
	$new_open_groups = "ALLGROUPS";
    } else
      $new_open_groups .= $open_groups[0];

    foreach ($metricGroupMap as $group => $metric_array) {
      if ($group == "") {
	$group = "no_group";
      }

      $num_metrics = count($metric_array);
      $metrics_group_data[$group]["group_metric_count"] = $num_metrics;
      $host_metrics += $num_metrics;
      if ($open_groups == NULL) {
	$metrics_group_data[$group]["visible"] = 
	  ! $metric_groups_initially_collapsed;
      } else {
	$inList = in_array($group, $open_groups);
	$metrics_group_data[$group]["visible"] = 
	  ((($open_groups[0] == "NOGROUPS") && $inList) || 
	   ($open_groups[0] == "ALLGROUPS" && !$inList));
      }

      $visible = $metrics_group_data[$group]["visible"];
      if (($visible && ($open_groups[0] == "NOGROUPS")) ||
	  (!$visible && ($open_groups[0] == "ALLGROUPS")))
	$new_open_groups .= "_|_" . $group;
 
      if (function_exists("sort_metric_group_metrics")) {
	$metric_array = sort_metric_group_metrics($group, $metric_array);
      } else {
	// Sort by metric_name
	asort($metric_array);
      }

      $i = 0;
      foreach ($metric_array as $name) {
	$metrics_group_data[$group]["metrics"][$name]["graphargs"] = $metricMap[$name]['graph'];
	$metrics_group_data[$group]["metrics"][$name]["alt"] = "$hostname $name";
	$metrics_group_data[$group]["metrics"][$name]["host_name"] = $hostname;
	$metrics_group_data[$group]["metrics"][$name]["metric_name"] = $name;
	$metrics_group_data[$group]["metrics"][$name]["title"] = $metricMap[$name]['title'];
	$metrics_group_data[$group]["metrics"][$name]["desc"] = $metricMap[$name]['description'];
	$metrics_group_data[$group]["metrics"][$name]["new_row"] = "";
	if (!(++$i % $conf['metriccols']) && ($i != $num_metrics))
	  $metrics_group_data[$group]["metrics"][$name]["new_row"] = "</TR><TR>";
      }
    }
  }

  $data->assign("host_metrics_count", $host_metrics);
  $_SESSION['metric_group'] = $new_open_groups;
  $data->assign("g_metrics_group_data", $metrics_group_data);
  $data->assign("g_open_metric_groups", $new_open_groups);
}

$optional_reports = getOptionalReports($hostname,
				       $clustername,
				       $graph_args);
// dump_var($optional_reports, "optional_reports");

$data->assign("optional_reports", $optional_reports);

getHostOverViewData($hostname, 
                    $metrics, 
                    $cluster,
                    $hosts_up, 
                    $hosts_down, 
                    $always_timestamp, 
                    $always_constant, 
                    $data);

# No reason to go on if this node is down.
if ($hosts_down) {
  $dwoo->output($tpl, $data);
  return;
}

$data->assign('columns_dropdown', 1);
$data->assign("metric_cols_menu", make_metric_cols_menu($conf['metriccols']));
$data->assign("size_menu", $size_menu);

$size = isset($clustergraphsize) ? $clustergraphsize : 'default';
//set to 'default' to preserve old behavior
$size = ($size == 'medium') ? 'default' : $size; 

// set host zoom class based on the size of the graph shown
$additional_host_img_css_classes = "";
if ( isset($conf['zoom_support']) && $conf['zoom_support'] === true )
  $additional_host_img_css_classes = "host_${size}_zoomable";

$data->assign("additional_host_img_css_classes",
	      $additional_host_img_css_classes);

# in case this is not defined, set to LOCALTIME so uptime will be 0 
# in the display
if (!isset($metrics['boottime']['VAL'])) { 
  $metrics['boottime']['VAL'] = $cluster['LOCALTIME'];
}

$cluster_url = rawurlencode($clustername);

$baseGraphArgs = "c=$cluster_url&amp;h=$hostname"
  . "&amp;r=$range&amp;z=$size&amp;jr=$jobrange"
  . "&amp;js=$jobstart&amp;st=$cluster[LOCALTIME]";
if ($cs)
  $baseGraphArgs .= "&amp;cs=" . rawurlencode($cs);
if ($ce)
  $baseGraphArgs .= "&amp;ce=" . rawurlencode($ce);

$data->assign("baseGraphArgs", htmlspecialchars_decode($baseGraphArgs));

getMetricGroups($metrics,
		$always_timestamp,
		$always_constant,
		$hostname,
		$baseGraphArgs,
		$data);

if ( $conf['graph_engine'] == "flot" ) {
  $data->assign("graph_height", $conf['graph_sizes'][$size]["height"] + 50);
  $data->assign("graph_width", $conf['graph_sizes'][$size]["width"]);
}

$data->assign('GRAPH_BASE_ID', $GRAPH_BASE_ID);
$data->assign('SHOW_EVENTS_BASE_ID', $SHOW_EVENTS_BASE_ID);
$data->assign('TIME_SHIFT_BASE_ID', $TIME_SHIFT_BASE_ID);

$dwoo->output($tpl, $data);
?>
