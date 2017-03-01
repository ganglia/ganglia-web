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

function get_picker_metrics($metrics, $reports, $gweb_root, $graph_engine) {
  $context_metrics = array();
  if (count($metrics)) {
    foreach ($metrics as $host_metrics) {
      foreach ($host_metrics as $metric_name => $metric_value) {
	$context_metrics[$metric_name] = rawurldecode($metric_name);
      }
    }
    foreach ($reports as $report_name => $report_value)
      $context_metrics[] = $report_name;
  }

  if (!is_array($context_metrics))
    return NULL;

  $picker_metrics = array();

  // Find all the optional reports
  if ($handle = opendir($gweb_root . '/graph.d')) {
    // If we are using RRDtool reports can be json or PHP suffixes
    $report_suffix = ($graph_engine == "rrdtool") ? "php|json" : "json";

    while (false !== ($file = readdir($handle))) {
      if (preg_match("/(.*)(_report)\.(" . $report_suffix .")/",
		     $file,
		     $out)) {
        if (!in_array($out[1] . "_report", $context_metrics))
          $context_metrics[] = $out[1] . "_report";
      }
    }
    closedir($handle);
  }

  sort($context_metrics);

  foreach ($context_metrics as $metric) {
    $url = rawurlencode($metric);
    $picker_metrics[] = "<option value=\"$url\">$metric</option>";
  }
  return $picker_metrics;
}

function get_load($host, $metrics) {
  if (isset($metrics[$host]["cpu_num"]['VAL']) and
      $metrics[$host]["cpu_num"]['VAL'] != 0 ) {
    $cpus = $metrics[$host]["cpu_num"]['VAL'];
  } else {
    $cpus = 1;
  }

  if (isset($metrics[$host]["load_one"]['VAL'])) {
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
		      $load_colors,
		      $metrics,
		      $cluster,
		      $name,
		      $tpl_data) {
  if ($showhosts != 0) {
    $percent_hosts = array();
    foreach ($hosts_up as $host => $val) {
      // If host_regex is defined
      if (isset($user['host_regex']) &&
          ! preg_match("/" . $user['host_regex'] . "/", $host))
        continue;

      $load_color = load_color(get_load($host, $metrics));

      if (isset($percent_hosts[$load_color])) {
        $percent_hosts[$load_color] += 1;
      } else {
        $percent_hosts[$load_color] = 1;
      }
    }

    $num_hosts_down = count($hosts_down);
    if ($num_hosts_down > 0)
      $percent_hosts[load_color(-1)] = $num_hosts_down;

    // Show pie chart of loads
    $pie_args = "title=" . rawurlencode("Cluster Load Percentages");
    $pie_args .= "&amp;size=250x150";
    foreach ($load_colors as $name => $color) {
      if (!array_key_exists($color, $percent_hosts))
        continue;
      $n = $percent_hosts[$color];
      $name_url = rawurlencode($name);
      $pie_args .= "&amp;$name_url=$n,$color";
    }
    $tpl_data->assign("pie_args", $pie_args);
  } else {
    // Show pie chart of hosts up/down
    $pie_args = "title=" . rawurlencode("Host Status");
    $pie_args .= "&amp;size=250x150";
    $up_color = $load_colors["25-50"];
    $down_color = $load_colors["down"];
    $pie_args .= "&amp;Up=$cluster[HOSTS_UP],$up_color";
    $pie_args .= "&amp;Down=$cluster[HOSTS_DOWN],$down_color";
    $tpl_data->assign("pie_args", $pie_args);
  }
}

function get_host_metric_graphs($showhosts,
				$hosts_up,
                                $hosts_down,
				$host_regex,
				$max_graphs,
                                $conf,
                                $metrics,
                                $metricname,
                                $sort,
                                $clustername,
                                $get_metric_string,
                                $cluster_localtime,
                                $always_timestamp,
                                $reports_metricname,
                                $clustergraphsize,
                                $range,
				$start,
				$end,
                                $cs,
                                $ce,
                                $vlabel,
			        $tpl_data) {
  $sorted_hosts = array();
  $down_hosts = array();

  if ($showhosts == 0)
    return;

  foreach ($hosts_up as $host => $val) {
    // If host_regex is defined
    if ($host_regex &&
	! preg_match("/" . $host_regex . "/", $host))
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

  $tpl_data->assign("node_legend", 1);

  if (!is_array($hosts_up))
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

  // First pass to find the max value in all graphs for this
  // metric. The $start,$end variables comes from get_context.php,
  // included in index.php.
  // Do this only if person has not selected a maximum set of graphs to display
  if ($max_graphs == 0 && $showhosts == 1) {
    if ($cs and (is_numeric($cs) or strtotime($cs)))
      $start = $cs;

    if ($ce and (is_numeric($ce) or strtotime($ce)))
      $end = $ce;

    list($min, $max) = find_limits($clustername,
				   $sorted_hosts,
				   $metricname,
				   $start,
				   $end,
				   $metrics,
				   $conf,
				   $rrd_options);
  }

  // Second pass to output the graphs or metrics.
  $i = 1;

  // Initialize overflow list
  $overflow_list = array();
  $overflow_counter = 1;

  $cluster_url = rawurlencode($clustername);

  $size = isset($clustergraphsize) ? $clustergraphsize : 'small';
  if ($conf['hostcols'] == 0) // enforce small size in multi-host report
    $size = 'small';

  $zoom_support = isset($conf['zoom_support']) &&
                  $conf['zoom_support'] === true;

  foreach ($sorted_hosts as $host => $value) {
    if (isset($hosts_down[$host]) &&
	$hosts_down[$host] &&
	isset($conf['cluster_hide_down_hosts']) &&
	$conf['cluster_hide_down_hosts']) {
      // If we're hiding DOWN hosts, we skip to next iteration of the loop.
      continue;
    }

    $host_url = ($case_sensitive_hostnames) ?
      rawurlencode($host) : strtolower(rawurlencode($host));

    $host_link="\"?c=$cluster_url&amp;h=$host_url&amp;$get_metric_string\"";
    $textval = NULL;

    //echo "$host: $value, ";

    if (isset($hosts_down[$host]) and $hosts_down[$host]) {
      $last_heartbeat = $cluster_localtime - $hosts_down[$host]['REPORTED'];
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
    $graphargs .= "&amp;r=$range&amp;su=1&amp;st=$cluster_localtime";
    if ($cs)
      $graphargs .= "&amp;cs=" . rawurlencode($cs);
    if ($ce)
      $graphargs .= "&amp;ce=" . rawurlencode($ce);

    $report_graphargs = $graphargs;

    // If we want scaling to be the same in clusterview we need to set
    // $max and $min values
    if ($showhosts == 1 && $max_graphs == 0 )
      $graphargs .= "&amp;x=$max&amp;n=$min";

    if (isset($vlabel))
      $graphargs .= "&amp;vl=" . urlencode($vlabel);

    $host_item = array();
    $host_item['name'] = $host;
    $host_item['host_link'] = $host_link;
    $host_item['textval'] = $textval;
    $host_item['class'] = $class;
    $host_item['report_graphargs'] = $report_graphargs;
    $host_item['metric_graphargs'] = $graphargs;
    $host_item['size'] = $size;
    $host_item['zoom_support'] = $zoom_support;

    // Check if max_graphs is set.
    // If it put cells in an overflow list since that one is hidden by default
    if ($max_graphs > 0 and $i++ > $max_graphs ) {
      $overflow_list[] = $host_item;
    } else {
      $sorted_list[] = $host_item;
    }
  } // foreach sorted_hosts

  $tpl_data->assign("sorted_list", $sorted_list);

  // If there is an overflow list. These are hosts for which we don't
  // show graphs just names
  $overflow = array();
  $overflow['list'] = $overflow_list;
  $overflow['count'] = count($overflow_list);
  $tpl_data->assign("overflow", $overflow);
}

function get_cluster_overview($showhosts,
                              $metrics,
                              $cluster,
                              $range,
                              $clustername,
                              $tpl_data) {
  $cpu_num = !$showhosts ? $metrics["cpu_num"]['SUM'] :
    cluster_sum("cpu_num", $metrics);

  $overview = array();

  $overview["cpu_num"] = $cpu_num;

  $overview["num_nodes"] =
    isset($cluster['HOSTS_UP']) ? intval($cluster['HOSTS_UP']) : 0;

  $overview["num_dead_nodes"] =
    isset($cluster['HOSTS_DOWN']) ? intval($cluster['HOSTS_DOWN']) : 0;

  if (!$cpu_num)
    $cpu_num = 1;

  $cluster_load = array();
  foreach (array("load_fifteen", "load_five", "load_one") as $load_metric) {
    $load_sum = !$showhosts ? $metrics[$load_metric]['SUM'] :
      cluster_sum($load_metric, $metrics);
    $cluster_load[] = sprintf("%.0f%%",
			      ((double) $load_sum / $cpu_num) * 100);
  }
  $overview["cluster_load"] = join(", ", $cluster_load);

  $avg_cpu_num = find_avg($clustername, "", "cpu_num");
  if ($avg_cpu_num == 0)
    $avg_cpu_num = 1;
  $cluster_util =
    sprintf("%.0f%%",
	    ((double) find_avg($clustername,
			       "",
			       "load_one") / $avg_cpu_num ) * 100);
  $overview["cluster_util"] = "$cluster_util";
  $overview["range"] = $range;
  $tpl_data->assign("overview", $overview);
}

function get_cluster_optional_reports($clustername,
                                      $conf_dir,
                                      $optional_graphs,
                                      $range,
                                      $cs,
                                      $ce,
                                      $zoom_support,
                                      $default_optional_graph_size,
                                      $localtime,
                                      $tpl_data) {
  $graph_args = "c=" . rawurlencode($clustername) .
    "&amp;r=$range&amp;st=$localtime";
  if ($cs)
    $graph_args .= "&amp;cs=" . rawurlencode($cs);
  if ($ce)
    $graph_args .= "&amp;ce=" . rawurlencode($ce);

  // If we want zoomable support on graphs we need to add correct zoomable
  // class to every image
  $zoom_support = isset($zoom_support) && $zoom_support === true;

  ##############################################################################
  # Let's find out what optional reports are included
  # First we find out what the default (site-wide) reports are then look
  # for host specific included or excluded reports
  ##############################################################################
  $default_reports = array("included_reports" => array(),
                           "excluded_reports" => array());
  if (is_file($conf_dir . "/default.json")) {
    $default_reports = array_merge(
      $default_reports,
      json_decode(file_get_contents($conf_dir . "/default.json"), TRUE));
  }

  $cluster_file = $conf_dir .
    "/cluster_" .
    str_replace(" ", "_", $clustername) .
    ".json";

  $override_reports = array("included_reports" => array(),
			    "excluded_reports" => array());
  if (is_file($cluster_file)) {
    $override_reports =
      array_merge($override_reports,
		  json_decode(file_get_contents($cluster_file), TRUE));
  }

  # Merge arrays
  foreach (array('included_reports', 'excluded_reports') as $report_type) {
    $reports[$report_type] =
      array_merge($default_reports[$report_type],
                  $override_reports[$report_type]);
    $reports[$report_type] = array_unique($reports[$report_type]);
  }

  $optional_reports = array();
  foreach ($reports["included_reports"] as $report_name ) {
    if (! in_array( $report_name, $reports["excluded_reports"])) {
      $report = array();
      $report['name'] = $report_name;
      $report['graph_args'] = $graph_args;
      $report['zoom_support'] = $zoom_support;
      $report['size'] = $default_optional_graph_size;
      $optional_reports[] = $report;
    }
  }
  $tpl_data->assign("optional_reports", $optional_reports);


  $tpl_optional_graphs = array();
  if (isset($optional_graphs)) {
    foreach ($optional_graphs as $g) {
      $tpl_optional_graphs[$g]['name'] = $g;
      $tpl_optional_graphs[$g]['graph_args'] = $graph_args;
      $tpl_optional_graphs[$g]['zoom_support'] = $zoom_support;
    }
  }
  $tpl_data->assign('optional_graphs', $tpl_optional_graphs);
}

function show_stacked_graphs($clustername,
			     $metricname,
			     $range,
			     $localtime,
			     $host_regex,
                             $cs,
                             $ce,
			     $tpl_data) {
  $stacked_args = "m=$metricname&amp;c=" . rawurlencode($clustername) .
    "&amp;r=$range&amp;st=$localtime";
  if ($host_regex)
    $stacked_args .= "&amp;host_regex=" . $host_regex;
  if ($cs)
    $stacked_args .= "&amp;cs=" . rawurlencode($cs);
  if ($ce)
    $stacked_args .= "&amp;ce=" . rawurlencode($ce);
  $tpl_data->assign("stacked_graph_args", $stacked_args);
}

function get_load_heatmap($hosts_up, $host_regex, $metrics, $tpl_data, $sort) {
  foreach ($hosts_up as $host => $val) {
  // If host_regex is defined
  if (isset($host_regex) &&
    ! preg_match("/" . $host_regex . "/", $host))
      continue;

    $load = get_load($host, $metrics);
    $host_load[$host] = $load;
  }

  $num_hosts = count($host_load);
  if ($num_hosts == 0)
    return;

  switch ($sort) {
  case "descending":
    arsort($host_load);
    break;
  case "by name":
    uksort($host_load, "strnatcmp");
    break;
  default:
  case "ascending":
    asort($host_load);
  break;
  }

  $num_cols = ceil(sqrt($num_hosts));

  $col_index = 0;
  $row_index = 0;
  $heatmap = '[';
  foreach ($host_load as $host => $load) {
    if ($col_index == 0) {
      if ($row_index > 0)
	$heatmap .= ',';
      $heatmap .= '[';
    }

    if ($col_index > 0)
      $heatmap .= ',';

    $heatmap .= "{host:\"$host\",load:$load}";

    if ($col_index == $num_cols - 1) {
      $heatmap .= ']';
      $col_index = 0;
      $row_index++;
    } else
      $col_index++;
  }

  for( $i = $row_index; $i < $num_cols; $i++ ) {
    for( $j = $col_index; $j < $num_cols; $j++ ) {
      if ($j == 0) {
        if ($i > 0)
	  $heatmap .= ',';
        $heatmap .= '[';
      } 
      if ($j > 0) {
        $heatmap .= ',';
      }
     
      $heatmap .= "{host:\"unused\",load:0}";
    }
    $heatmap .= ']';
    $col_index = 0;
  }
  $heatmap .= ']';

  $tpl_data->assign("heatmap_data", $heatmap);
}

$fn = "cluster_" . ($refresh ? "refresh" : "view") . ".tpl";
$tpl = new Dwoo_Template_File(template($fn));

$tpl_data = new Dwoo_Data();

if (! $refresh) {
  $tpl_data->assign(
    "php_gd",
    (function_exists('imagegif') or function_exists('imagepng')));

  $tpl_data->assign("extra", template("cluster_extra.tpl"));

  $tpl_data->assign("user_may_edit",
                    checkAccess($user['clustername'],
                                GangliaAcl::EDIT, $conf ) );

  $tpl_data->assign("graph_engine", $conf['graph_engine']);
}

$tpl_data->assign("cluster", $user['clustername']);

$tpl_data->assign("localtimestamp", $cluster['LOCALTIME']);

$tpl_data->assign("localtime", date("Y-m-d H:i", $cluster['LOCALTIME']));

get_cluster_overview($user['showhosts'],
		     $metrics,
                     $cluster,
		     $user['range'],
		     $user['clustername'],
		     $tpl_data);

$user_metricname = $user['metricname'];
if (!$user['showhosts']) {
  if (array_key_exists($user_metricname, $metrics))
    $units = $metrics[$user_metricname]['UNITS'];
} else {
  if (array_key_exists($user_metricname, $metrics[key($metrics)]))
    if (isset($metrics[key($metrics)][$user_metricname]['UNITS']))
      $units = $metrics[key($metrics)][$user_metricname]['UNITS'];
  else
    $units = '';
}

if (isset($units)) {
  $vlabel = $units;
}

if (! $refresh) {
  get_cluster_optional_reports($user['clustername'],
                               $conf['conf_dir'],
                               $conf['optional_graphs'],
                               $user['range'],
                               $user['cs'],
                               $user['ce'],
                               $conf['zoom_support'],
                               $conf['default_optional_graph_size'],
                               $cluster[LOCALTIME],
                               $tpl_data);

  //////////////////////////////////////////////////////////////////////////////
  // Begin Host Display Controller
  //////////////////////////////////////////////////////////////////////////////

  // Correctly handle *_report cases and blank (" ") units

  if (isset($units)) {
    if ($units == " ")
      $units = "";
    else
      $units = $units ? "($units)" : "";
  } else {
    $units = "";
  }

  $tpl_data->assign("metric", "{$user['metricname']} $units");
  $tpl_data->assign("metric_name", "{$user['metricname']}");
  $tpl_data->assign("sort", $user['sort']);
  $tpl_data->assign("range", $user['range']);

  $showhosts_levels = array(2 => array('checked' => '', 'name' => 'Auto'),
                            1 => array('checked' => '', 'name' => 'Same'),
                            0 => array('checked' => '', 'name' => 'None'));
  $showhosts_levels[$user['showhosts']]['checked'] = 'checked';
  $tpl_data->assign("showhosts_levels", $showhosts_levels);

  if ($user['showhosts']) {
    $tpl_data->assign("columns_size_dropdown", 1);
    $tpl_data->assign("cols_menu", $cols_menu);
    $tpl_data->assign("size_menu", $size_menu);
  }

  $set_host_regex_value =
    (isset($user['host_regex']) &&
     $user['host_regex'] != "") ?
    "value='" . htmlentities($user['host_regex'], ENT_QUOTES) . "'" : "";

  // In some clusters you may have thousands of hosts which may load
  // for a long time. For those cases we have the ability to display
  // only the max amount of graphs and put place holders for the rest ie.
  // it will say only print host name without an image
  $max_graphs_options = array(1000,500,200,100,50,25,20,15,10);

  if (isset($user['max_graphs']) && is_numeric($user['max_graphs']))
    $max_graphs = $user['max_graphs'];
  else
    $max_graphs = $conf['max_graphs'];

  $max_graphs_values = "<option value=0>all</option>";
  foreach ($max_graphs_options as $value) {
    if ($max_graphs == $value)
      $max_graphs_values .= "<option selected>" . $value . "</option>";
    else
      $max_graphs_values .= "<option>" . $value . "</option>";
  }

  $tpl_data->assign("additional_filter_options", "");
  if ($user['showhosts']) {
    $tpl_data->assign(
      "additional_filter_options",
      'Show only nodes matching <input name=host_regex ' .
      $set_host_regex_value . '>' .
      '<input class="header_btn" type="SUBMIT" VALUE="Filter">' .
      '<div style="display:inline;padding-left:10px;" class="nobr">Max graphs to show <select onChange="ganglia_submit();" name="max_graphs">' .
      $max_graphs_values .
      '</select></div>' .
      '<div style="display:inline;padding-left:10px;" class="nobr">' .
      make_sort_menu($context, $user['sort']) .
      '</div>');
  }

  //////////////////////////////////////////////////////////////////////////////
  // End Host Display Controller
  //////////////////////////////////////////////////////////////////////////////
}

if ((count($hosts_up) == 0) ||
    !(isset($conf['heatmaps_enabled']) and $conf['heatmaps_enabled'] == 1))
  get_load_pie($user['showhosts'],
	       $hosts_up,
	       $hosts_down,
	       $user,
	       $conf['load_colors'],
	       $metrics,
	       $cluster,
	       $name,
	       $tpl_data);

if ($user['showhosts'] != 0)
  get_host_metric_graphs($user['showhosts'],
			 $hosts_up,
			 $hosts_down,
			 isset($user['host_regex']) ? $user['host_regex'] : NULL,
			 isset($user['max_graphs']) ? $user['max_graphs'] : $conf['max_graphs'],
			 $conf,
			 $metrics,
			 $user['metricname'],
			 $user['sort'],
			 $user['clustername'],
			 $get_metric_string,
			 $cluster['LOCALTIME'],
			 $always_timestamp,
			 $reports[$user['metricname']],
			 $clustergraphsize,
			 $user['range'],
			 $start,
			 $end,
			 $user['cs'],
			 $user['ce'],
			 $vlabel,
			 $tpl_data);

///////////////////////////////////////////////////////////////////////////////
// Creates a heatmap
///////////////////////////////////////////////////////////////////////////////
if (isset($conf['heatmaps_enabled']) and
    $conf['heatmaps_enabled'] == 1 and
    (count($hosts_up) > 0))
  get_load_heatmap($hosts_up, $user['host_regex'], $metrics, $tpl_data, $user['sort']);

$tpl_data->assign("conf", $conf);
$tpl_data->assign("showhosts", $user['showhosts']);

error_log("reports = " . print_r($reports, TRUE));
$tpl_data->assign("reports_metricname",
                  isset($reports[$user['metricname']]) &&
                  $reports[$user['metricname']]);

$tpl_data->assign("hostcols", $conf['hostcols']);

// No reason to go on if we are not displaying individual hosts
if (!is_array($hosts_up) or !$user['showhosts']) {
  $dwoo->output($tpl, $tpl_data);
  return;
}

///////////////////////////////////////////////////////////////////////////////
// Show stacked graphs
///////////////////////////////////////////////////////////////////////////////
if (isset($conf['show_stacked_graphs']) and
    $conf['show_stacked_graphs'] == 1  and
    ! preg_match("/_report$/", $user['metricname']))
  show_stacked_graphs($user['clustername'],
		      $user['metricname'],
		      $user['range'],
		      $cluster[LOCALTIME],
		      $user['host_regex'],
		      $user['cs'],
		      $user['ce'],
		      $tpl_data);


if ($conf['picker_autocomplete'] == true) {
  $tpl_data->assign('picker_autocomplete', true);
} else {
  $picker_metrics = get_picker_metrics($metrics,
				       $reports,
				       $conf['gweb_root'],
				       $conf['graph_engine']);
  if ($picker_metrics != NULL)
    $tpl_data->assign("picker_metrics", join("", $picker_metrics));
}

$dwoo->output($tpl, $tpl_data);
?>
