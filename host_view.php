<?php
include_once("./global.php");

function make_metric_cols_menu($metriccols) {
  $metric_cols_menu =
    "<select name=\"mc\" OnChange=\"ganglia_form.submit();\">\n";

  foreach (range(1, 25) as $metric_cols) {
    $metric_cols_menu .= "<option value=$metric_cols ";
    if ($metric_cols == $metriccols)
      $metric_cols_menu .= "selected";
    $metric_cols_menu .= ">$metric_cols\n";
  }
  $metric_cols_menu .= "</select>\n";
  return $metric_cols_menu;
}

$tpl = new Dwoo_Template_File( template("host_view.tpl") );
$data = new Dwoo_Data();

$data->assign("cluster", $user['clustername']);
$data->assign("may_edit_cluster", checkAccess( $user['clustername'],
					       GangliaAcl::EDIT,
					       $conf ) );
$data->assign("may_edit_views", checkAccess( GangliaAcl::ALL_VIEWS,
					     GangliaAcl::EDIT,
					     $conf) );
$data->assign("sort", $user['sort']);
$data->assign("range", $user['range']);
$data->assign("hostname", $user['hostname']);
$data->assign("graph_engine", $conf['graph_engine']);

function dump_var($var, $varId) {
  ob_start();
  var_dump($var);
  error_log($varId . " = ". ob_get_clean());
}

function getOptionalReports($hostname,
			    $clustername,
                            $conf_dir,
                            $optional_cluster_graphs_for_host_view,
                            $range,
                            $cs,
                            $ce,
                            $zoom_support,
                            $graph_engine,
                            $may_edit_views,
                            $default_optional_graph_size) {
  global $GRAPH_BASE_ID;
  global $SHOW_EVENTS_BASE_ID;

  $optional_reports = "";

  $cluster_enc = rawurlencode($clustername);

  $graph_args = "h=$hostname&amp;c=$cluster_enc&amp;r=$range";
  if ($cs)
    $graph_args .= "&amp;cs=" . rawurlencode($cs);
  if ($ce)
    $graph_args .= "&amp;ce=" . rawurlencode($ce);

  // If we want zoomable support on graphs we need to add correct zoomable
  // class to every image
  $additional_graph_img_html_args = "";
  if (isset($zoom_support) && $zoom_support === true)
    $additional_graph_img_html_args = "class=cluster_zoomable";

  ///////////////////////////////////////////////////////////////////////////
  // Let's find out what optional reports are included
  // First we find out what the default (site-wide) reports are then look
  // for host specific included or excluded reports
  ///////////////////////////////////////////////////////////////////////////
  $default_reports = array("included_reports" => array(),
			   "excluded_reports" => array());
  if ( is_file($conf_dir . "/default.json") ) {
    $default_reports = array_merge(
      $default_reports,
      json_decode(file_get_contents($conf_dir . "/default.json"),
		  TRUE));
  }
  if ( is_file($conf_dir . "/default_host.json") ) {
    $default_reports = array_merge(
      $default_reports,
      json_decode(file_get_contents($conf_dir . "/default_host.json"),
		  TRUE));
  }

  $cluster_file = $conf_dir .
    "/cluster_" .
    str_replace(" ", "_", $clustername) .
    ".json";

  $cluster_override_reports = array("included_reports" => array(),
				    "excluded_reports" => array());

  if ($optional_cluster_graphs_for_host_view) {
    if (is_file($cluster_file)) {
      $cluster_override_reports = array_merge(
        $cluster_override_reports,
        json_decode(file_get_contents($cluster_file), TRUE));
    }
  }

  $host_file = $conf_dir . "/host_" . $hostname . ".json";
  $override_reports = array("included_reports" => array(),
			    "excluded_reports" => array());
  if ( is_file($host_file) ) {
    $override_reports = array_merge(
      $override_reports,
      json_decode(file_get_contents($host_file), TRUE));
  } else {
    // If there is no host file, look for a default cluster file
    $cluster_file = $conf_dir . "/cluster_" . $clustername . ".json";
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
    $default_reports["excluded_reports"],
    $cluster_override_reports["excluded_reports"],
    $override_reports["excluded_reports"]);

  // Remove duplicates
  $reports["included_reports"] = array_unique($reports["included_reports"]);
  $reports["excluded_reports"] = array_unique($reports["excluded_reports"]);

  foreach ( $reports["included_reports"] as $index => $report_name ) {
    if ( ! in_array( $report_name, $reports["excluded_reports"] ) ) {
      $graph_anchor =
	"<a href=\"./graph_all_periods.php?$graph_args&amp;g=" . $report_name .
	"&amp;z=large\">";

      $addMetricBtn = "<button class=\"cupid-green\" title=\"Metric Actions - Add to View, etc\" onclick=\"metricActions('{$hostname}','{$report_name}','graph','');  return false;\">+</button>";

      $csvBtn = "<button title=\"Export to CSV\" class=\"cupid-green\" onClick=\"javascript:location.href='./graph.php?$graph_args&amp;g={$report_name}&amp;&amp;csv=1';return false;\">CSV</button>";

      $jsonBtn = "<button title=\"Export to JSON\" class=\"cupid-green\" onClick=\"javascript:location.href='./graph.php?$graph_args&amp;g={$report_name}&amp;json=1';return false;\">JSON</button>";

      if ($graph_engine == "flot") {
	$optional_reports .= $graph_anchor . "</a>";
	$optional_reports .= '<div class="flotheader"><span class="flottitle">' . $report_name . '</span>';
	if ($may_edit_views)
	  $optional_reports .= $addMetricBtn. '&nbsp;';
	$optional_reports .= $csvBtn . '&nbsp;' . $jsonBtn . "</div>";
	$optional_reports .= '<div id="placeholder_' . $graph_args . '&amp;g=' . $report_name .'&amp;z=medium" class="flotgraph2 img_view"></div>';
	$optional_reports .= '<div id="placeholder_' . $graph_args . '&amp;g=' . $report_name .'&amp;z=medium_legend" class="flotlegend"></div>';
      } else {
	$optional_reports .= "<div class='img_view'>";
	$graphId = $GRAPH_BASE_ID . $report_name;
	$inspectBtn = "<button title=\"Inspect Graph\" onClick=\"inspectGraph('{$graph_args}&amp;g={$report_name}'); return false;\" class=\"shiny-blue\">Inspect</button>";
	$showEventBtn = '<input title="Hide/Show Events" type="checkbox" id="' . $SHOW_EVENTS_BASE_ID . $report_name . '" onclick="showEvents(\'' . $graphId . '\', this.checked)"/><label class="show_event_text" for="' . $SHOW_EVENTS_BASE_ID . $report_name . '">Hide/Show Events</label>';
	if ($may_edit_views)
	  $optional_reports .= $addMetricBtn . '&nbsp;';
	$optional_reports .= $csvBtn . '&nbsp;' . $jsonBtn . '&nbsp;' .$inspectBtn . '&nbsp;' . $showEventBtn . "<br />" . $graph_anchor . "<img id=\"" . $graphId . "\" $additional_graph_img_html_args border=\"0\" title=\"$cluster_enc\" src=\"./graph.php?$graph_args&amp;g=" . $report_name ."&amp;z=" . $default_optional_graph_size . "\" style=\"margin-top:5px;\" /></a></div>";
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
    $open_groups = explode ("_|_", htmlentities($_GET['metric_group']));
  } else {
    if ($remember_open_metric_groups &&
	isset($_SESSION['metric_group']) &&
	($_SESSION['metric_group'] != ""))
      $open_groups = explode ("_|_", htmlentities($_SESSION['metric_group']));
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
	asort($metric_array, SORT_NATURAL);
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
	  $metrics_group_data[$group]["metrics"][$name]["new_row"] = "</tr><tr>";
      }
    }
  }

  $data->assign("host_metrics_count", $host_metrics);
  $_SESSION['metric_group'] = $new_open_groups;
  $data->assign("g_metrics_group_data", $metrics_group_data);
  $data->assign("g_open_metric_groups", $new_open_groups);
}

$optional_reports = getOptionalReports(
  $user['hostname'],
  $user['clustername'],
  $conf['conf_dir'],
  $conf['optional_cluster_graphs_for_host_view'],
  $user['range'],
  $user['cs'],
  $user['ce'],
  $conf['zoom_support'],
  $conf['graph_engine'],
  checkAccess( GangliaAcl::ALL_VIEWS,
               GangliaAcl::EDIT,
               $conf),
  $conf['default_optional_graph_size']);
// dump_var($optional_reports, "optional_reports");

$data->assign("optional_reports", $optional_reports);

getHostOverViewData($user['hostname'],
                    $metrics,
                    $cluster,
                    $hosts_up,
                    $hosts_down,
                    $always_timestamp,
                    $always_constant,
                    $data);

# When this node is down, only show data when show_host_data_if_down is True
if ($hosts_down && !$conf['show_host_data_if_down']) {
  $dwoo->output($tpl, $data);
  return;
}

$data->assign('columns_dropdown', 1);
$data->assign("metric_cols_menu", make_metric_cols_menu($conf['metriccols']));
$data->assign("size_menu", $size_menu);

$size = isset($user['clustergraphsize']) ?
  $user['clustergraphsize'] : 'default';
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

$baseGraphArgs = "c=$cluster_url&amp;h=" . $user['hostname']
  . "&amp;r=" . $user['range'] . "&amp;z=$size&amp;jr=$jobrange"
  . "&amp;js=$jobstart&amp;st=$cluster[LOCALTIME]";
if ($user['cs'])
  $baseGraphArgs .= "&amp;cs=" . rawurlencode($user['cs']);
if ($user['ce'])
  $baseGraphArgs .= "&amp;ce=" . rawurlencode($user['ce']);

$data->assign("baseGraphArgs", htmlspecialchars_decode($baseGraphArgs));

getMetricGroups($metrics,
		$always_timestamp,
		$always_constant,
		$user['hostname'],
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
