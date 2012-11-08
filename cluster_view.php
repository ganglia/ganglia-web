<?php
$refresh = isset($_GET['refresh']);


include_once "./eval_conf.php";
// ATD - function.php must be included before get_context.php.  
// It defines some needed functions.
include_once "./functions.php";
include_once "./get_context.php";
include_once "./ganglia.php";
include_once "./get_ganglia.php";
include_once "./dwoo/dwooAutoload.php";

if ($refresh) {
  try {
    $dwoo = new Dwoo($conf['dwoo_compiled_dir'], $conf['dwoo_cache_dir']);
  } catch (Exception $e) {
    print "<H4>There was an error initializing the Dwoo PHP Templating Engine: " .
      $e->getMessage() . 
      "<br><br>The compile directory should be owned and writable by the apache user.</H4>";
    exit;
  }
 }

function get_load($host, $metrics) {
  if (isset($metrics[$host]["cpu_num"]['VAL']) and 
      $metrics[$host]["cpu_num"]['VAL'] != 0 ) {
    $cpus = $metrics[$host]["cpu_num"]['VAL'];
  } else {
    $cpus = 1;
  }

  if (isset($metrics[$host]["load_one"]['VAL']) ){
    $load_one = $metrics[$host]["load_one"]['VAL'];
  } else {
    $load_one = 0;
  }
  $load = ((float) $load_one) / $cpus;
  return $load;
}

function get_load_pie($showhosts, 
		      $hosts_up, 
		      $hosts_down, 
		      $user, 
		      $conf,
		      $metrics, 
		      $cluster,
		      $name,
		      $data) {
  if ($showhosts) {
    $percent_hosts = array();
    foreach ($hosts_up as $host => $val) {
      // If host_regex is defined
      if (isset($user['host_regex']) && 
          ! preg_match("/" .$user['host_regex'] . "/", $host))
        continue;
      
      $load = get_load($host, $metrics);

      if (isset($percent_hosts[load_color($load)])) { 
        $percent_hosts[load_color($load)] += 1;
      } else {
        $percent_hosts[load_color($load)] = 1;
      }
    }
         
    foreach ($hosts_down as $host => $val) {
      $load = -1.0;
      if (isset($percent_hosts[load_color($load)])) {
        $percent_hosts[load_color($load)] += 1;
      } else {
        $percent_hosts[load_color($load)] = 1;
      }
    }

    // Show pie chart of loads
    $pie_args = "title=" . rawurlencode("Cluster Load Percentages");
    $pie_args .= "&amp;size=250x150";
    foreach ($conf['load_colors'] as $name => $color) {
      if (!array_key_exists($color, $percent_hosts))
        continue;
      $n = $percent_hosts[$color];
      $name_url = rawurlencode($name);
      $pie_args .= "&amp;$name_url=$n,$color";
    }
    $data->assign("pie_args", $pie_args);
  } else {
    // Show pie chart of hosts up/down
    $pie_args = "title=" . rawurlencode("Host Status");
    $pie_args .= "&amp;size=250x150";
    $up_color = $conf['load_colors']["25-50"];
    $down_color = $conf['load_colors']["down"];
    $pie_args .= "&amp;Up=$cluster[HOSTS_UP],$up_color";
    $pie_args .= "&amp;Down=$cluster[HOSTS_DOWN],$down_color";
    $data->assign("pie_args", $pie_args);
  }
}

function get_host_metric_graphs($showhosts, 
                                $hosts_up, 
                                $hosts_down, 
                                $user, 
                                $conf,
                                $metrics, 
                                $metricname,
                                $sort,
                                $clustername,
                                $get_metric_string,
                                $cluster,
                                $always_timestamp,
                                $reports_metricname,
                                $clustergraphsize,
                                $range,
                                $cs,
                                $ce,
                                $vlabel,
			        $data) {
  $sorted_hosts = array();
  $down_hosts = array();
  if ($showhosts) {
    foreach ($hosts_up as $host => $val) {
      // If host_regex is defined
      if (isset($user['host_regex']) && 
          ! preg_match("/" .$user['host_regex'] . "/", $host))
        continue;
      
      $load = get_load($host, $metrics);
      $host_load[$host] = $load;

      if ($metricname == "load_one")
        $sorted_hosts[$host] = $load;
      else if (isset($metrics[$host][$metricname]))
        $sorted_hosts[$host] = $metrics[$host][$metricname]['VAL'];
      else
        $sorted_hosts[$host] = "";
    } // foreach hosts_up
         
    foreach ($hosts_down as $host => $val) {
      $down_hosts[$host] = -1.0;
    }

    $data->assign("node_legend", 1);
  }

  if (!is_array($hosts_up) or !$showhosts)
    return;

  switch ($sort) {
  case "descending":
    arsort($sorted_hosts);
    break;
  case "by name":
    uksort($sorted_hosts, "strnatcmp");
    break;
  default:
  case "ascending":
    asort($sorted_hosts);
    break;
  }

  $sorted_hosts = array_merge($down_hosts, $sorted_hosts);

  if (isset($user['max_graphs']))
    $max_graphs = $user['max_graphs'];
  else
    $max_graphs = $conf['max_graphs'];

  // First pass to find the max value in all graphs for this
  // metric. The $start,$end variables comes from get_context.php, 
  // included in index.php.
  // Do this only if person has not selected a maximum set of graphs to display
  if ($max_graphs == 0 && $showhosts == 2 )
    list($min, $max) = find_limits($sorted_hosts, $metricname);

  // Second pass to output the graphs or metrics.
  $i = 1;

  // Initialize overflow list
  $overflow_list = array();
  $overflow_counter = 1;
  $cluster_url = rawurlencode($clustername);
  $size = isset($clustergraphsize) ? $clustergraphsize : 'small';
  if ($conf['hostcols'] == 0) // enforce small size in multi-host report
    $size = 'small';
  // set host zoom class based on the size of the graph shown
  if (isset($conf['zoom_support']) && $conf['zoom_support'] === true)
    $additional_host_img_html_args = "class=host_${size}_zoomable";

  foreach ($sorted_hosts as $host => $value) {
    if (isset($hosts_down[$host]) and $hosts_down[$host] && isset($conf['cluster_hide_down_hosts']) && $conf['cluster_hide_down_hosts']) {
      // If we're hiding DOWN hosts, we skip to next iteration of the loop.
      continue;
    }
    $host_url = rawurlencode($host);
    
    $host_link="\"?c=$cluster_url&amp;h=$host_url&amp;$get_metric_string\"";
    $textval = "";

    //echo "$host: $value, ";

    if (isset($hosts_down[$host]) and $hosts_down[$host]) {
      $last_heartbeat = $cluster['LOCALTIME'] - $hosts_down[$host]['REPORTED'];
      $age = $last_heartbeat > 3600 ? 
        uptime($last_heartbeat) : "${last_heartbeat}s";

      $class = "down";
      $textval = "down <br>&nbsp;<font size=\"-2\">Last heartbeat $age ago</font>";
    } else {
      if (isset($metrics[$host][$metricname]))
        $val = $metrics[$host][$metricname];
      else
        $val = NULL;
      $class = "metric";
      
      if ($val['TYPE']=="timestamp" or 
          (isset($always_timestamp[$metricname]) and
           $always_timestamp[$metricname])) {
        $textval = date("r", $val['VAL']);
      } elseif ($val['TYPE']=="string" or 
                $val['SLOPE']=="zero" or
                (isset($always_constant[$metricname]) and
                 $always_constant[$metricname] or
                 ($max_graphs > 0 and $i > $max_graphs))) {
        if (isset($reports_metricname) and $reports_metricname)
          // No "current" values available for reports
          $textval = "N/A";
        else
          $textval = "$val[VAL]";
        if (isset($val['UNITS']))
          $textval .= " $val[UNITS]";
      }
    }
    
    $graphargs = "z=$size&amp;c=$cluster_url&amp;h=$host_url";
    
    if (isset($host_load[$host])) {
      $load_color = load_color($host_load[$host]);
      $graphargs .= "&amp;l=$load_color&amp;v=$val[VAL]";
    }
    $graphargs .= "&amp;r=$range&amp;su=1&amp;st=$cluster[LOCALTIME]";
    if ($cs)
      $graphargs .= "&amp;cs=" . rawurlencode($cs);
    if ($ce)
      $graphargs .= "&amp;ce=" . rawurlencode($ce);
    
    // If we want scaling to be the same in clusterview we need to set $max and $min
    // values
    if ($showhosts == 2 && $max_graphs == 0 )
      $graphargs .= "&amp;x=$max&amp;n=$min";
    
    if (isset($vlabel))
      $graphargs .= "&amp;vl=" . urlencode($vlabel);
    
    if ($textval) {
      $cell = "<td class=$class>" .
	"<b><a href=$host_link>$host</a></b><br>" .
	"<i>$metricname:</i> <b>$textval</b></td>";
    } else {
      $cell = "<td><div><font style='font-size: 8px'>$host</font><br><a href=$host_link><img $additional_host_img_html_args src=\"./graph.php?";
      $cell .= (isset($reports_metricname) and 
                $reports_metricname) ? "g=$metricname" : "m=$metricname";
      $cell .= "&amp;$graphargs\" title=\"$host\" border=0 style=\"padding:2px;\"></a></div></td>";
    }

    if ($conf['hostcols'] == 0) {
      $pre = "<td><a href=$host_link><img src=\"./graph.php?g=";
      $post = "&amp;$graphargs\" $additional_host_img_html_args title=\"$host\" border=0 style=\"padding:2px;\"></a></td>";
      $cell .= $pre . "load_report" . $post;
      $cell .= $pre . "mem_report" . $post;
      $cell .= $pre . "cpu_report" . $post;
      $cell .= $pre . "network_report" . $post;
    }
    
    // Check if max_graphs is set. 
    // If it put cells in an overflow list since that one is hidden by default
    if ($max_graphs > 0 and $i > $max_graphs ) {
      $overflow_list[$host]["metric_image"] = $cell;
      if (! ($overflow_counter++ % $conf['hostcols']) ) {
        $overflow_list[$host]["br"] = "</tr><tr>";
      } else {
        $overflow_list[$host]["br"] = "";
      }
    } else {
      $sorted_list[$host]["metric_image"] = $cell;
      if (! ($i++ % $conf['hostcols']) ) {
        $sorted_list[$host]["br"] = "</tr><tr>";
      } else {
        $sorted_list[$host]["br"] = "";
      }
    } // end of if ($max_graphs > 0 and $i > $max_graphs ) {
  } // foreach sorted_hosts
  
  $data->assign("sorted_list", $sorted_list);
  
  // If there is an overflow list. These are hosts for which we don't show graphs
  // just names
  if (sizeof($overflow_list) > 0) {
    $data->assign("overflow_list_header", '<p><table width=80%><tr><td align=center class=metric>
    <a href="#" id="overflow_list_button"onclick="$(\'#overflow_list\').toggle();" class="button ui-state-default ui-corner-all" title="Toggle overflow list">Show more hosts (' 
    . ($overflow_counter - 1) .')</a>
    </td></tr></table>
    <div style="display: none;" id="overflow_list"><table>
    <tr>
    ');
    $data->assign("overflow_list_footer", "</div></tr></table></div>");
  } else {
    $data->assign("overflow_list_header", "");
    $data->assign("overflow_list_footer", "");
  }
  $data->assign("overflow_list", $overflow_list);
}

function get_cluster_overview($showhosts, 
                              $metrics,
                              $cluster,
                              $range, 
                              $clustername, 
                              $data) {
  $cpu_num = !$showhosts ? $metrics["cpu_num"]['SUM'] : 
                           cluster_sum("cpu_num", $metrics);
  $data->assign("cpu_num", $cpu_num);

  if (isset($cluster['HOSTS_UP'])) {
    $data->assign("num_nodes", intval($cluster['HOSTS_UP']));
  } else {
    $data->assign("num_nodes", 0);
  }

  if (isset($cluster['HOSTS_DOWN'])) {
    $data->assign("num_dead_nodes", intval($cluster['HOSTS_DOWN']));
  } else {
    $data->assign("num_dead_nodes", 0);
  }

  $load_one_sum = !$showhosts ? $metrics["load_one"]['SUM'] : 
                                cluster_sum("load_one", $metrics);
  $load_five_sum = !$showhosts ? $metrics["load_five"]['SUM'] : 
                                 cluster_sum("load_five", $metrics);
  $load_fifteen_sum = !$showhosts ? $metrics["load_fifteen"]['SUM'] : 
                                    cluster_sum("load_fifteen", $metrics);

  if (!$cpu_num) 
    $cpu_num = 1;
  $cluster_load15 = sprintf("%.0f", 
                            ((double) $load_fifteen_sum / $cpu_num) * 100);
  $cluster_load5 = sprintf("%.0f", ((double) $load_five_sum / $cpu_num) * 100);
  $cluster_load1 = sprintf("%.0f", ((double) $load_one_sum / $cpu_num) * 100);
  $data->assign("cluster_load", 
                "$cluster_load15%, $cluster_load5%, $cluster_load1%");

  $avg_cpu_num = find_avg($clustername, "", "cpu_num");
  if ($avg_cpu_num == 0) 
    $avg_cpu_num = 1;
  $cluster_util = sprintf("%.0f", 
	  		  ((double) find_avg($clustername, 
                                             "",
					     "load_one") / $avg_cpu_num ) * 100);
  $data->assign("cluster_util", "$cluster_util%");
  $data->assign("range", $range);
}

function get_cluster_optional_reports($conf, 
                                      $clustername, 
                                      $get_metric_string,
                                      $localtime,
                                      $data,
                                      $metrics) {
  $cluster_url = rawurlencode($clustername);
  $graph_args = "c=$cluster_url&amp;$get_metric_string&amp;st=$localtime";

  $optional_reports = "";

  // If we want zoomable support on graphs we need to add correct zoomable 
  // class to every image
  $additional_cluster_img_html_args = "";
  if (isset($conf['zoom_support']) && $conf['zoom_support'] === true)
    $additional_cluster_img_html_args = "class=cluster_zoomable";

  $data->assign("additional_cluster_img_html_args", $additional_cluster_img_html_args);

###############################################################################
# Let's find out what optional reports are included
# First we find out what the default (site-wide) reports are then look
# for host specific included or excluded reports
###############################################################################
  $default_reports = array("included_reports" => array(), "excluded_reports" => array());
 if (is_file($conf['conf_dir'] . "/default.json")) {
   $default_reports = array_merge(
     $default_reports,
     json_decode(file_get_contents($conf['conf_dir'] . "/default.json"), TRUE));
 }

 $cluster_file = $conf['conf_dir'] . 
   "/cluster_" . 
   str_replace(" ", "_", $clustername) . 
   ".json";

 $override_reports = array("included_reports" => array(), "excluded_reports" => array());
 if (is_file($cluster_file)) {
   $override_reports = array_merge($override_reports, 
				   json_decode(file_get_contents($cluster_file), TRUE));
 }

# Merge arrays
 $reports["included_reports"] = 
   array_merge($default_reports["included_reports"],$override_reports["included_reports"]);
 $reports["excluded_reports"] = 
   array_merge($default_reports["excluded_reports"],$override_reports["excluded_reports"]);

# Remove duplicates
 $reports["included_reports"] = array_unique($reports["included_reports"]);
 $reports["excluded_reports"] = array_unique($reports["excluded_reports"]);

 $cluster_url = rawurlencode($clustername);

 foreach ($reports["included_reports"] as $index => $report_name ) {
   if (! in_array( $report_name, $reports["excluded_reports"])) {
     # Only show metrics that actually exist for this cluster (we'll use
     # the first host in the cluster as our sample)
     if (isset($conf['report_to_metric'][$report_name]) &&
         !isset($metrics[key($metrics)][$conf['report_to_metric'][$report_name]]))
       continue;
     $optional_reports .= "<A HREF=\"./graph_all_periods.php?$graph_args&amp;g=" . $report_name . "&amp;z=large\">
    <IMG BORDER=0 style=\"padding:2px;\" $additional_cluster_img_html_args title=\"$cluster_url\" SRC=\"./graph.php?$graph_args&amp;g=" . $report_name ."&amp;z=medium\"></A>
";
   }
 }
 $data->assign("optional_reports", $optional_reports);

 $data->assign("graph_args", $graph_args);

 if (!isset($conf['optional_graphs']))
   $conf['optional_graphs'] = array();
 $optional_graphs_data = array();
 foreach ($conf['optional_graphs'] as $g) {
   $optional_graphs_data[$g]['name'] = $g;
   $optional_graphs_data[$g]['graph_args'] = $graph_args;
 }
 $data->assign('optional_graphs_data', $optional_graphs_data);
}

function get_load_heatmap($hosts_up, $user, $metrics, $data) {
  foreach ($hosts_up as $host => $val) {
    // If host_regex is defined
    if (isset($user['host_regex']) && 
        ! preg_match("/" .$user['host_regex'] . "/", $host))
      continue;
    
    $load = get_load($host, $metrics);
    $host_load[$host] = $load;
  }

  $num_hosts = count($host_load);

  $matrix = ceil(sqrt($num_hosts));

  $xindex = 0;
  $yindex = 0;

  foreach ($host_load as $key => $value) {
    if ($xindex >= $matrix) {
      $string_array[] = "[" . join(",", $matrix_array[$yindex]) . "]";
      $yindex++;
      $xindex = 0;
    }
    
    $matrix_array[$yindex][$xindex] = $value;
    $xindex++;
  }

  $string_array[] = "[" . join(",", $matrix_array[$yindex]) . "]";

  $conf['heatmap_size'] = 200;

  $heatmap = join(",", $string_array);

  $data->assign("heatmap", $heatmap);
  $data->assign("heatmap_size", floor($conf['heatmap_size'] / $matrix));
}

$fn = "cluster_" . ($refresh ? "refresh" : "view") . ".tpl";
$tpl = new Dwoo_Template_File(template($fn));

$data = new Dwoo_Data();

if (! $refresh) {
  $data->assign("php_gd", 
		(function_exists('imagegif') or function_exists('imagepng')));
  
  $data->assign("extra", template("cluster_extra.tpl"));
  
  $data->assign("user_may_edit", 
		checkAccess( $clustername, GangliaAcl::EDIT, $conf ) );
  
  $data->assign("graph_engine", $conf['graph_engine']);
 }

$data->assign("cluster", $clustername);

$data->assign("localtimestamp", $cluster['LOCALTIME']);

$data->assign("localtime", date("Y-m-d H:i", $cluster['LOCALTIME']));

get_cluster_overview($showhosts, 
		     $metrics,
		     $cluster,
		     $range, 
		     $clustername, 
		     $data);

if (! $refresh) {
  get_cluster_optional_reports($conf, 
			       $clustername, 
			       $get_metric_string,
			       $cluster[LOCALTIME],
			       $data,
			       $metrics);
  
  ///////////////////////////////////////////////////////////////////////////////
  // Begin Host Display Controller
  ///////////////////////////////////////////////////////////////////////////////
  
  // Correct handling of *_report metrics
  
  if (!$showhosts) {
    if (array_key_exists($metricname, $metrics))
      $units = $metrics[$metricname]['UNITS'];
  } else {
    if (array_key_exists($metricname, $metrics[key($metrics)]))
      if (isset($metrics[key($metrics)][$metricname]['UNITS']))
	$units = $metrics[key($metrics)][$metricname]['UNITS'];
      else
        $units = '';
  }
  
  // Correctly handle *_report cases and blank (" ") units
  
  if (isset($units)) {
    $vlabel = $units;
    if ($units == " ")
      $units = "";
    else
      $units=$units ? "($units)" : "";
  } else {
    $units = "";
  }
  $data->assign("metric","$metricname $units");
  $data->assign("metric_name","$metricname");
  $data->assign("sort", $sort);
  $data->assign("range", $range);
  
  $showhosts_levels = array(2 => array('checked'=>'', 'name'=>'Auto'),
			    1 => array('checked'=>'', 'name'=>'Same'),
			    0 => array('checked'=>'', 'name'=>'None'),
			    );
  $showhosts_levels[$showhosts]['checked'] = 'checked';
  $data->assign("showhosts_levels", $showhosts_levels);
  
  if ($showhosts) {
    $data->assign("columns_size_dropdown", 1);
    $data->assign("cols_menu", $cols_menu);
    $data->assign("size_menu", $size_menu);
  }
  ///////////////////////////////////////////////////////////////////////////////
  // End Host Display Controller
  ///////////////////////////////////////////////////////////////////////////////
 }

if (!(isset($conf['heatmaps_enabled']) and $conf['heatmaps_enabled'] == 1))
  get_load_pie($showhosts, 
	       $hosts_up, 
	       $hosts_down, 
	       $user, 
	       $conf,
	       $metrics, 
	       $cluster,
	       $name,
	       $data);

get_host_metric_graphs($showhosts, 
                       $hosts_up, 
                       $hosts_down, 
                       $user, 
                       $conf,
                       $metrics, 
                       $metricname,
                       $sort,
                       $clustername,
                       $get_metric_string,
                       $cluster,
                       $always_timestamp,
                       $reports[$metricname],
                       $clustergraphsize,
                       $range,
                       $cs,
                       $ce,
                       $vlabel,
		       $data);

// No reason to go on if we have no up hosts.
if (!is_array($hosts_up) or !$showhosts) {
  $dwoo->output($tpl, $data);
  return;
}

///////////////////////////////////////////////////////////////////////////////
// Creates a heatmap
///////////////////////////////////////////////////////////////////////////////
if (isset($conf['heatmaps_enabled']) and $conf['heatmaps_enabled'] == 1)
  get_load_heatmap($hosts_up, $user, $metrics, $data);

///////////////////////////////////////////////////////////////////////////////
// Show stacked graphs
///////////////////////////////////////////////////////////////////////////////
if (isset($conf['show_stacked_graphs']) and 
    $conf['show_stacked_graphs'] == 1  and 
    ! preg_match("/_report$/", $metricname)) {
  $cluster_url = rawurlencode($clustername);
  $stacked_args = "m=$metricname&amp;c=$cluster_url&amp;r=$range&amp;st=$cluster[LOCALTIME]";
  if (isset($user['host_regex']))
    $stacked_args .= "&amp;host_regex=" . $user['host_regex'];
  $data->assign("stacked_graph_args", $stacked_args);
}

$dwoo->output($tpl, $data);
?>
