<?php
include_once("./global.php");

$tpl = new Dwoo_Template_File( template("host_view.tpl") );
$data = new Dwoo_Data();

$data->assign("cluster", $clustername);
$data->assign("may_edit_cluster", checkAccess( $clustername, GangliaAcl::EDIT, $conf ) );
$data->assign("may_edit_views", checkAccess( GangliaAcl::ALL_VIEWS, GangliaAcl::EDIT, $conf) );
$data->assign("sort",$sort);
$data->assign("range",$range);
$data->assign("hostname", $hostname);
$data->assign("graph_engine", $conf['graph_engine']);

$metric_groups_initially_collapsed = isset($conf['metric_groups_initially_collapsed']) ? $conf['metric_groups_initially_collapsed'] : TRUE;
$remember_open_metric_groups = isset($conf['remember_open_metric_groups']) ? $conf['remember_open_metric_groups'] : TRUE;

$graph_args = "h=$hostname&amp;$get_metric_string&amp;st=$cluster[LOCALTIME]";

$optional_reports = "";

///////////////////////////////////////////////////////////////////////////
// Let's find out what optional reports are included
// First we find out what the default (site-wide) reports are then look
// for host specific included or excluded reports
///////////////////////////////////////////////////////////////////////////
$default_reports = array("included_reports" => array(), "excluded_reports" => array());
if ( is_file($conf['conf_dir'] . "/default.json") ) {
  $default_reports = array_merge($default_reports,json_decode(file_get_contents($conf['conf_dir'] . "/default.json"), TRUE));
}

$cluster_file = $conf['conf_dir'] .
   "/cluster_" .
   str_replace(" ", "_", $clustername) .
   ".json";

$cluster_override_reports = array("included_reports" => array(), "excluded_reports" => array());
if (is_file($cluster_file)) {
   $cluster_override_reports = array_merge($cluster_override_reports,
                                   json_decode(file_get_contents($cluster_file), TRUE));
} 

$host_file = $conf['conf_dir'] . "/host_" . $hostname . ".json";
$override_reports = array("included_reports" => array(), "excluded_reports" => array());
if ( is_file($host_file) ) {
  $override_reports = array_merge($override_reports, json_decode(file_get_contents($host_file), TRUE));
} else {
  // If there is no host file, look for a default cluster file
  $cluster_file = $conf['conf_dir'] . "/cluster_" . $clustername . ".json";
  if ( is_file($cluster_file) ) {
    $override_reports = array_merge($override_reports, json_decode(file_get_contents($cluster_file), TRUE));
  }
}

// Merge arrays
$reports["included_reports"] = array_merge( $default_reports["included_reports"] , $cluster_override_reports["included_reports"], $override_reports["included_reports"]);
$reports["excluded_reports"] = array_merge($default_reports["excluded_reports"] , $cluster_override_reports["excluded_reports"],  $override_reports["excluded_reports"]);

// Remove duplicates
$reports["included_reports"] = array_unique($reports["included_reports"]);
$reports["excluded_reports"] = array_unique($reports["excluded_reports"]);

// If we want zoomable support on graphs we need to add correct zoomable class to every image
$additional_cluster_img_html_args = "";
$additional_host_img_css_classes = "";
if ( isset($conf['zoom_support']) && $conf['zoom_support'] === true )
   $additional_cluster_img_html_args = "class=cluster_zoomable";

$data->assign("additional_cluster_img_html_args", $additional_cluster_img_html_args);

foreach ( $reports["included_reports"] as $index => $report_name ) {
  if ( ! in_array( $report_name, $reports["excluded_reports"] ) ) {
    $graph_anchor = "<a href=\"./graph_all_periods.php?$graph_args&amp;g=" . $report_name . "&amp;z=large&amp;c=$cluster_url\">";

    $addMetricBtn = "<button class=\"cupid-green\" title=\"Metric Actions - Add to View, etc\" onclick=\"metricActions('{$hostname}','{$report_name}','graph','');  return false;\">+</button>";

    $csvBtn = "<button title=\"Export to CSV\" class=\"cupid-green\" onClick=\"javascript:location.href='./graph.php?$graph_args&amp;g={$report_name}&amp;z=large&amp;c=$cluster_url&amp;csv=1';return false;\">CSV</button>";

    $jsonBtn = "<button title=\"Export to JSON\" class=\"cupid-green\" onClick=\"javascript:location.href='./graph.php?$graph_args&amp;g={$report_name}&amp;z=large&amp;c=$cluster_url&amp;json=1';return false;\">JSON</button>";

    if ( $conf['graph_engine'] == "flot" ) {
      $optional_reports .= $graph_anchor . "</a>";
      $optional_reports .= '<div class="flotheader"><span class="flottitle">' . $report_name . '</span>';
      if(checkAccess(GangliaAcl::ALL_VIEWS, GangliaAcl::EDIT, $conf))
        $optional_reports .= $addMetricBtn. '&nbsp;';
      $optional_reports .= $csvBtn . '&nbsp;' . $jsonBtn . "</div>";
      $optional_reports .= '<div id="placeholder_' . $graph_args . '&amp;g=' . $report_name .'&amp;z=medium&amp;c=' . $cluster_url . '" class="flotgraph2 img_view"></div>';
      $optional_reports .= '<div id="placeholder_' . $graph_args . '&amp;g=' . $report_name .'&amp;z=medium&amp;c=' . $cluster_url . '_legend" class="flotlegend"></div>';
    } else {
      $optional_reports .= "<div class='img_view'>";
      $graphId = $GRAPH_BASE_ID . $report_name;
      $inspectBtn = "<button title=\"Inspect Graph\" onClick=\"inspectGraph('{$graph_args}&amp;g={$report_name}&amp;z=large&amp;c={$cluster_url}'); return false;\" class=\"shiny-blue\">Inspect</button>";
      $showEventBtn = '<input title="Hide/Show Events" type="checkbox" id="' . $SHOW_EVENTS_BASE_ID . $report_name . '" onclick="showEvents(\'' . $graphId . '\', this.checked)"/><label class="show_event_text" for="' . $SHOW_EVENTS_BASE_ID . $report_name . '">Hide/Show Events</label>';
      if(checkAccess(GangliaAcl::ALL_VIEWS, GangliaAcl::EDIT, $conf))
        $optional_reports .= $addMetricBtn . '&nbsp;';
      $optional_reports .= $csvBtn . '&nbsp;' . $jsonBtn . '&nbsp;' .$inspectBtn . '&nbsp;' . $showEventBtn . "<br />" . $graph_anchor . "<img id=\"" . $graphId . "\" $additional_cluster_img_html_args border=\"0\" title=\"$cluster_url\" SRC=\"./graph.php?$graph_args&amp;g=" . $report_name ."&amp;z=medium&amp;c=$cluster_url\" style=\"margin-top:5px;\" /></a></div>";
    }
  }
} // foreach

$data->assign("optional_reports", $optional_reports);

$cluster_url=rawurlencode($clustername);
$data->assign("cluster_url", $cluster_url);
$data->assign("graphargs", $graph_args);

# For the node view link.
$data->assign("node_view","./?p=2&amp;c=$cluster_url&amp;h=$hostname");

getHostOverViewData($hostname, 
                    $metrics, 
                    $cluster,
                    $hosts_up, 
                    $hosts_down, 
                    $always_timestamp, 
                    $always_constant, 
                    $data);

# No reason to go on if this node is down.
if ($hosts_down)
   {
      $dwoo->output($tpl, $data);
      return;
   }

$data->assign("ip", $hosts_up['IP']);
$data->assign('columns_dropdown', 1);
$data->assign("metric_cols_menu", $metric_cols_menu);
$data->assign("size_menu", $size_menu);
$g_metrics_group = array();

foreach ($metrics as $name => $v)
   {
       if ($v['TYPE'] == "string" or $v['TYPE']=="timestamp" or
           (isset($always_timestamp[$name]) and $always_timestamp[$name]))
          {
          }
       elseif ($v['SLOPE'] == "zero" or
               (isset($always_constant[$name]) and $always_constant[$name]))
          {
          }
       else if (isset($reports[$name]) and $reports[$metric])
          continue;
       else
          {
             $size = isset($clustergraphsize) ? $clustergraphsize : 'default';
             $size = $size == 'medium' ? 'default' : $size; //set to 'default' to preserve old behavior

             // set host zoom class based on the size of the graph shown
             if ( isset($conf['zoom_support']) && $conf['zoom_support'] === true )
                $additional_host_img_css_classes = "host_${size}_zoomable";

             $data->assign("additional_host_img_css_classes", $additional_host_img_css_classes);

             $graphargs = "c=$cluster_url&amp;h=$hostname&amp;v=$v[VAL]"
               ."&amp;m=$name&amp;r=$range&amp;z=$size&amp;jr=$jobrange"
               ."&amp;js=$jobstart&amp;st=$cluster[LOCALTIME]";
             if ($cs)
                $graphargs .= "&amp;cs=" . rawurlencode($cs);
             if ($ce)
                $graphargs .= "&amp;ce=" . rawurlencode($ce);
             # Adding units to graph 2003 by Jason Smith <smithj4@bnl.gov>.
             if ($v['UNITS']) {
                $encodeUnits = rawurlencode($v['UNITS']);
                $graphargs .= "&amp;vl=$encodeUnits";
             }
             if (isset($v['TITLE'])) {
                $title = $v['TITLE'];
		$encodeTitle = rawurlencode($title);
                $graphargs .= "&amp;ti=$encodeTitle";
             }
             $g_metrics[$name]['graph'] = $graphargs;
             $g_metrics[$name]['description'] = isset($v['DESC']) ? $v['DESC'] : '';
             $g_metrics[$name]['title'] = isset($v['TITLE']) ? $v['TITLE'] : '';

             # Setup an array of groups that can be used for sorting in group view
             if ( isset($metrics[$name]['GROUP']) ) {
                $groups = $metrics[$name]['GROUP'];
             } else {
                $groups = array("");
             }

             foreach ( $groups as $group) {
                if ( isset($g_metrics_group[$group]) ) {
                   $g_metrics_group[$group] = array_merge($g_metrics_group[$group], (array)$name);
                } else {
                   $g_metrics_group[$group] = array($name);
                }
             }
          }
   }

# in case this is not defined, set to LOCALTIME so uptime will be 0 in the display
if ( !isset($metrics['boottime']['VAL']) ){ $metrics['boottime']['VAL'] = $cluster['LOCALTIME'];}
# Add the average node utilization to the time & string metrics section:
$avg_cpu_num = find_avg($clustername, $hostname, "cpu_num");
if ($avg_cpu_num == 0) $avg_cpu_num = 1;
$cluster_util = sprintf("%.0f", ((double) find_avg($clustername, $hostname, "load_one") / $avg_cpu_num ) * 100);
$data->assign("name", "Avg Utilization (last $range)");
$data->assign("value", "$cluster_util%");

$open_groups = NULL;
if (isset($_GET['metric_group']) && ($_GET['metric_group'] != "")) {
  $open_groups = explode ("_|_", $_GET['metric_group']);
} else {
  if ($remember_open_metric_groups && isset($_SESSION['metric_group']) && ($_SESSION['metric_group'] != ""))
    $open_groups = explode ("_|_", $_SESSION['metric_group']);
}

$g_new_open_groups = ""; // Updated definition of currently open metric groups

# Show graphs.
if ( is_array($g_metrics) && is_array($g_metrics_group) )
   {
      $g_metrics_group_data = array();
      ksort($g_metrics_group);
      $host_metrics = 0;

      if ($open_groups == NULL) {
        if ($metric_groups_initially_collapsed)
          $g_new_open_groups = "NOGROUPS";
        else
          $g_new_open_groups = "ALLGROUPS";
      } else
       $g_new_open_groups .= $open_groups[0];

      foreach ( $g_metrics_group as $group => $metric_array )
         {
            if ( $group == "" ) {
               $group = "no_group";
            }
            $c = count($metric_array);
            $g_metrics_group_data[$group]["group_metric_count"] = $c;
            $host_metrics += $c;
            if ($open_groups == NULL)
              $g_metrics_group_data[$group]["visible"] = 
                ! $metric_groups_initially_collapsed;
            else {
              $inList = in_array($group, $open_groups);
              $g_metrics_group_data[$group]["visible"] = 
                ((($open_groups[0] == "NOGROUPS") && $inList) || 
                  ($open_groups[0] == "ALLGROUPS" && !$inList));
            }

            $visible = $g_metrics_group_data[$group]["visible"];
            if (($visible && ($open_groups[0] == "NOGROUPS")) ||
                (!$visible && ($open_groups[0] == "ALLGROUPS")))
              $g_new_open_groups .= "_|_" . $group;

            $i = 0;
            ksort($g_metrics);
            foreach ( $g_metrics as $name => $v )
               {
                  if ( in_array($name, $metric_array) ) {
                     $g_metrics_group_data[$group]["metrics"][$name]["graphargs"] = $v['graph'];
                     $g_metrics_group_data[$group]["metrics"][$name]["alt"] = "$hostname $name";
                     $g_metrics_group_data[$group]["metrics"][$name]["host_name"] = $hostname;
                     $g_metrics_group_data[$group]["metrics"][$name]["metric_name"] = $name;
                     $g_metrics_group_data[$group]["metrics"][$name]["title"] = $v['title'];
                     $g_metrics_group_data[$group]["metrics"][$name]["desc"] = $v['description'];
                     $g_metrics_group_data[$group]["metrics"][$name]["new_row"] = "";
                     if ( !(++$i % $conf['metriccols']) && ($i != $c) )
                        $g_metrics_group_data[$group]["metrics"][$name]["new_row"] = "</TR><TR>";
                  }
               }
         }
      $data->assign("host_metrics_count", $host_metrics);
   }

$_SESSION['metric_group'] = $g_new_open_groups;

if ( $conf['graph_engine'] == "flot" ) {
  $data->assign("graph_height", $conf['graph_sizes'][$size]["height"] + 50);
  $data->assign("graph_width", $conf['graph_sizes'][$size]["width"]);
}
$data->assign("g_metrics_group_data", $g_metrics_group_data);
$data->assign("g_open_metric_groups", $g_new_open_groups);

$data->assign('GRAPH_BASE_ID', $GRAPH_BASE_ID);
$data->assign('SHOW_EVENTS_BASE_ID', $SHOW_EVENTS_BASE_ID);
$data->assign('TIME_SHIFT_BASE_ID', $TIME_SHIFT_BASE_ID);

$dwoo->output($tpl, $data);
?>
