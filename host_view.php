<?php
$tpl = new Dwoo_Template_File( template("host_view.tpl") );
$data = new Dwoo_Data();
$data->assign("extra", template("host_extra.tpl"));

$data->assign("cluster", $clustername);
$data->assign("host", $hostname);
$data->assign("may_edit_cluster", checkAccess( $clustername, GangliaAcl::EDIT, $conf ) );
$data->assign("may_edit_views", checkAccess( GangliaAcl::ALL_VIEWS, GangliaAcl::EDIT, $conf) );
$data->assign("node_image", node_image($metrics));
$data->assign("sort",$sort);
$data->assign("range",$range);
$data->assign("hostname", $hostname);
$data->assign("graph_engine", $conf['graph_engine']);

$metric_groups_initially_collapsed = isset($conf['metric_groups_initially_collapsed']) ? $conf['metric_groups_initially_collapsed'] : TRUE;

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

$host_file = $conf['conf_dir'] . "/host_" . $hostname . ".json";
$override_reports = array("included_reports" => array(), "excluded_reports" => array());
if ( is_file($host_file) ) {
  $override_reports = array_merge($override_reports, json_decode(file_get_contents($host_file), TRUE));
}

// Merge arrays
$reports["included_reports"] = array_merge( $default_reports["included_reports"] , $override_reports["included_reports"]);
$reports["excluded_reports"] = array_merge($default_reports["excluded_reports"] , $override_reports["excluded_reports"]);

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
    //$optional_reports .= "<a name=metric_" . $report_name . ">
    $optional_reports .= "<a href=\"./graph_all_periods.php?$graph_args&amp;g=" . $report_name . "&amp;z=large&amp;c=$cluster_url\">";

    if ( $conf['graph_engine'] == "flot" ) {
      $optional_reports .= '<div class="flotheader"><span class="flottitle">' . $report_name . '</span>';
      if(checkAccess(GangliaAcl::ALL_VIEWS, GangliaAcl::EDIT, $conf)) {
        $optional_reports .= "<button class=\"cupid-green\" title=\"Metric Actions - Add to View, etc\" onclick=\"metricActions('" . $hostname . "','" . $report_name ."','graph','');  return false;\">+</button>";
      } 
      $optional_reports .= " <button title=\"Export to CSV\" class=\"cupid-green\" onClick=\"javascript:location.href='./graph.php?$graph_args&amp;g=" . $report_name . "&amp;z=large&amp;c=$cluster_url&amp;csv=1';return false;\">CSV</button>
      <button title=\"Export to JSON\" class=\"cupid-green\" onClick=\"javascript:location.href='./graph.php?$graph_args&amp;g=" . $report_name . "&amp;z=large&amp;c=$cluster_url&amp;json=1';return false;\">JSON</button></div>";
      $optional_reports .= '<div id="placeholder_' . $graph_args . '&amp;g=' . $report_name .'&amp;z=medium&amp;c=' . $cluster_url . '" class="flotgraph2 img_view"></div>';
      $optional_reports .= '<div id="placeholder_' . $graph_args . '&amp;g=' . $report_name .'&amp;z=medium&amp;c=' . $cluster_url . '_legend" class="flotlegend"></div>';
    } else {
      $optional_reports .= "<div class='img_view'>";
      if(checkAccess(GangliaAcl::ALL_VIEWS, GangliaAcl::EDIT, $conf)) {
        $optional_reports .= "<button class=\"cupid-green\" title=\"Metric Actions - Add to View, etc\" onclick=\"metricActions('" . $hostname . "','" . $report_name ."','graph','');  return false;\">+</button>";
      } 
      $optional_reports .= " <button title=\"Export to CSV\" class=\"cupid-green\" onClick=\"javascript:location.href='./graph.php?$graph_args&amp;g=" . $report_name . "&amp;z=large&amp;c=$cluster_url&amp;csv=1';return false;\">CSV</button>
      <button title=\"Export to JSON\" class=\"cupid-green\" onClick=\"javascript:location.href='./graph.php?$graph_args&amp;g=" . $report_name . "&amp;z=large&amp;c=$cluster_url&amp;json=1';return false;\">JSON</button>
      <button title=\"Inspect Graph\" onClick=\"inspectGraph('" . $graph_args . "&amp;g=" . $report_name . "&amp;z=large&amp;c=" . $cluster_url . "'); return false;\" class=\"shiny-blue\">Inspect</button>     
      <br /><img $additional_cluster_img_html_args border=\"0\" title=\"$cluster_url\" SRC=\"./graph.php?$graph_args&amp;g=" . $report_name ."&amp;z=medium&amp;c=$cluster_url\" style=\"padding:2px;\" />
      </div>
      ";
    }

    $optional_reports .= "</a>";

  }

}

$data->assign("optional_reports", $optional_reports);



if($hosts_up)
      $data->assign("node_msg", "This host is up and running."); 
else
      $data->assign("node_msg", "This host is down."); 

$cluster_url=rawurlencode($clustername);
$data->assign("cluster_url", $cluster_url);
$data->assign("graphargs", $graph_args);

# For the node view link.
$data->assign("node_view","./?p=2&amp;c=$cluster_url&amp;h=$hostname");

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
             $s_metrics[$name] = $v;
          }
       elseif ($v['SLOPE'] == "zero" or
               (isset($always_constant[$name]) and $always_constant[$name]))
          {
             $c_metrics[$name] = $v;
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

# Add the uptime metric for this host. Cannot be done in ganglia.php,
# since it requires a fully-parsed XML tree. The classic contructor problem.
$s_metrics['uptime']['TYPE'] = "string";
$s_metrics['uptime']['VAL'] = uptime($cluster['LOCALTIME'] - $metrics['boottime']['VAL']);
$s_metrics['uptime']['TITLE'] = "Uptime";

# Add the gmond started timestamps & last reported time (in uptime format) from
# the HOST tag:
$s_metrics['gmond_started']['TYPE'] = "timestamp";
$s_metrics['gmond_started']['VAL'] = $hosts_up['GMOND_STARTED'];
$s_metrics['gmond_started']['TITLE'] = "Gmond Started";
$s_metrics['last_reported']['TYPE'] = "string";
$s_metrics['last_reported']['VAL'] = uptime($cluster['LOCALTIME'] - $hosts_up['REPORTED']);
$s_metrics['last_reported']['TITLE'] = "Last Reported";

$s_metrics['ip_address']['TITLE'] = "IP Address";
$s_metrics['ip_address']['VAL'] = $hosts_up['IP'];
$s_metrics['ip_address']['TYPE'] = "string";
$s_metrics['location']['TITLE'] = "Location";
$s_metrics['location']['VAL'] = $hosts_up['LOCATION'];
$s_metrics['location']['TYPE'] = "string";

# Show string metrics
if (is_array($s_metrics))
   {
      $s_metrics_data = array();
      ksort($s_metrics);
      foreach ($s_metrics as $name => $v )
      {
        # RFM - If units aren't defined for metric, make it be the empty string
        ! array_key_exists('UNITS', $v) and $v['UNITS'] = "";
        if (isset($v['TITLE']))
           {
              $s_metrics_data[$name]["name"] = $v['TITLE'];
           }
        else
           {
              $s_metrics_data[$name]["name"] = $name;
           }
        if( $v['TYPE']=="timestamp" or (isset($always_timestamp[$name]) and $always_timestamp[$name]))
           {
              $s_metrics_data[$name]["value"] = date("r", $v['VAL']);
           }
        else
           {
              $s_metrics_data[$name]["value"] = $v['VAL'] . " " . $v['UNITS'];
           }
     }
   }
$data->assign("s_metrics_data", $s_metrics_data);

# Add the average node utilization to the time & string metrics section:
$avg_cpu_num = find_avg($clustername, $hostname, "cpu_num");
if ($avg_cpu_num == 0) $avg_cpu_num = 1;
$cluster_util = sprintf("%.0f", ((double) find_avg($clustername, $hostname, "load_one") / $avg_cpu_num ) * 100);
$data->assign("name", "Avg Utilization (last $range)");
$data->assign("value", "$cluster_util%");

# Show constant metrics.
$c_metrics_data = null;
if (isset($c_metrics) and is_array($c_metrics))
   {
      $c_metrics_data = array();
      ksort($c_metrics);
      foreach ($c_metrics as $name => $v )
     {
        if (isset($v['TITLE']))
           {
              $c_metrics_data[$name]["name"] =  $v['TITLE'];
           }
        else
           {
              $c_metrics_data[$name]["name"] = $name;
           }
        $c_metrics_data[$name]["value"] = "$v[VAL] $v[UNITS]";
     }
   }
$data->assign("c_metrics_data", $c_metrics_data);

$open_groups = NULL;
if (isset($_GET['metric_group']) && ($_GET['metric_group'] != "")) {
  $open_groups = explode ("_|_", $_GET['metric_group']);
} else {
  if (isset($_SESSION['metric_group']) && ($_SESSION['metric_group'] != ""))
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
$dwoo->output($tpl, $data);
?>
