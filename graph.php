<?php
// vim: tabstop=2:softtabstop=2:shiftwidth=2:expandtab

session_start();

include_once "./eval_conf.php";
include_once "./get_context.php";
include_once "./functions.php";

function add_total_to_cdef($cdef,
			   $total_ids,
			   $graph_config_scale) {
  // Percentage calculation for cdefs, if required
  $total = " CDEF:'total'=";
  if (count($total_ids) == 0) {
    // Handle nothing gracefully, do nothing
    ; //PHPCS
  } else if (count($total_ids) == 1) {
    // Concat just that id, leave it at that (100%)
    $total .= $total_ids[0];
    if (isset($graph_config_scale))
      $total .= ",${graph_config_scale},*";
    $cdef = $total . ' ' . $cdef;
  } else {
    $total .= $total_ids[0];
    foreach ($total_ids as $total_id)
      $total .= ',' . $total_id . ',ADDNAN';

    if (isset($graph_config['scale']))
      $total .= ",${graph_config_scale},*";

    // Prepend total calculation
    $cdef = $total . ', ' . $cdef;
  }
  return $cdef;
}

function graphdef_add_series($graphdef,
			     $series,
			     $series_type,
			     $graph_config_percent,
			     $graph_config_scale,
			     $stack_counter,
			     $series_id,
			     $max_label_length,
			     $conf_graphreport_stats,
			     $conf_graphreport_stat_items) {
  static $line_widths = array("1", "2", "3");

  $label = str_pad(sanitize($series['label']), $max_label_length);
  switch ($series_type) {
  case "line":
    // Make sure it's a recognized line type
    $line_width = isset($series['line_width']) &&
      in_array($series['line_width'], $line_widths) ?
      $series['line_width'] : '1';
    $graphdef .= "LINE" . $line_width;
    $graphdef .= ":'";
    if (isset($graph_config_percent) &&
	$graph_config_percent == '1') {
      $graphdef .= "p";
    } else if (isset($graph_config_scale)) {
      $graphdef .= "s";
    }
    $graphdef .= "${series_id}'#${series['color']}:'${label}' ";
    break;

  case "stack":
  case "percent":
    // First element in a stack has to be AREA
    if ($stack_counter == 0) {
      $graphdef .= "AREA";
      $stack_counter++;
    } else {
      $graphdef .= "STACK";
    }
    $graphdef .= ":'";
    if (isset($graph_config_percent) &&
	$graph_config_percent == '1') {
      $graphdef .= "p";
    } else if (isset($graph_config_scale)) {
      $graphdef .= "s";
    }
    $graphdef .= "${series_id}'#${series['color']}:'${label}' ";
    break;

    // Percentile lines
  case "percentile":
    $percentile = isset($series['percentile']) ?
      floatval($series['percentile']): 95;
    $graphdef .= "VDEF:t${series_id}=${series_id},${percentile},PERCENT ";
    $line_width = isset($series['line_width']) &&
      in_array($series['line_width'], $line_widths) ?
      $series['line_width'] : '1';
    $graphdef .= "LINE" . $line_width .
      ":'t$series_id'#{$series['color']}:'{$label}':dashes ";
    break;

  case "area":
    $graphdef .= "AREA";
    $graphdef .= ":'$series_id'#${series['color']}:'${label}' ";
    break;
  }

  if ($conf_graphreport_stats) {
    $id = $series_id;
    if (isset($graph_config_percent) &&
	$graph_config_percent == '1') {
      $id = 'p' . $id;
    } else if (isset($graph_config_scale)) {
      $id = 's' . $id;
    }
    $graphdef .= legendEntry($id, $conf_graphreport_stat_items);
  }

  return array($graphdef, $stack_counter);
}

///////////////////////////////////////////////////////////////////////////////
// Populate $rrdtool_graph from $config (from JSON file).
///////////////////////////////////////////////////////////////////////////////
function rrdtool_graph_merge_args_from_json($rrdtool_graph,
					    $graph_config,
					    $context,
					    $size,
					    $conf_rrds,
					    $conf_graphreport_stats,
					    $conf_graphreport_stat_items) {

  $title = sanitize($graph_config['title']);
  $rrdtool_graph['title'] = $title;
  // If vertical label is empty or non-existent set it to space otherwise
  // rrdtool will fail
  if (! isset($graph_config['vertical_label']) ||
      $graph_config['vertical_label'] == "") {
     $rrdtool_graph['vertical-label'] = " ";
  } else {
    $rrdtool_graph['vertical-label'] =
      sanitize($graph_config['vertical_label']);
  }

  $rrdtool_graph['lower-limit'] = '0';

  if (isset($graph_config['height_adjustment']) ) {
    $rrdtool_graph['height'] +=
      ($size == 'medium') ? $graph_config['height_adjustment'] : 0;
  } else {
    $rrdtool_graph['height'] += ($size == 'medium') ? 28 : 0;
  }

  // find longest label length, so we pad the others accordingly to get
  // consistent column alignment
  $max_label_length = 0;
  foreach($graph_config['series'] as $series)
    $max_label_length = max(strlen($series['label']), $max_label_length);

  $seriesdef = '';
  $cdef = '';
  $graphdef = '';
  $stack_counter = 0;
  $total_ids = array();

  // Loop through all the graph series
  foreach ($graph_config['series'] as $series_id => $series) {
    // ignore series if context is not defined in json template
    if (isset($series['contexts']) and
	!in_array($context, $series['contexts']))
      continue;

    $rrd_dir = $conf_rrds . "/" .
      $series['clustername'] . "/" .
      $series['hostname'];

    $metric = sanitize($series['metric']);
    $metric_file = $rrd_dir . "/" . $metric . ".rrd";

    // Make sure metric file exists. Otherwise we'll get a broken graph
    if (is_file($metric_file)) {
      // Need this when defining graphs that may use same metric names
      $unique_id = "a" . $series_id;
      $total_ids[] = $unique_id;

      // use custom DS defined in json template if it's
      // defined (default = 'sum')
      $DS = isset($series['ds']) ? sanitize($series['ds']) : 'sum';
      $seriesdef .= " DEF:'$unique_id'='$metric_file':'$DS':AVERAGE ";

      if (isset($graph_config['scale']))
	$cdef .=
	  " CDEF:'s${unique_id}'=${unique_id},${graph_config['scale']},* ";

      if (isset($graph_config['percent']) && $graph_config['percent'] == '1')
	$cdef .= " CDEF:'p${unique_id}'=${unique_id},total,/,100,* ";

      // By default graph is a line graph
      $series_type = isset($series['type']) ? $series['type'] : "line";

      list($graphdef, $stack_counter) =
	graphdef_add_series($graphdef,
			    $series,
			    $series_type,
			    $graph_config['percent'],
			    $graph_config['scale'],
			    $stack_counter,
			    $unique_id,
			    $max_label_length,
			    $conf_graphreport_stats,
			    $conf_graphreport_stat_items);
    } // end of if (is_file($metric_file))
  }

  $show_total = isset($graph_config['show_total']) &&
    ($graph_config['show_total'] == 1);
  if ($show_total ||
      (isset($graph_config['percent']) &&
       $graph_config['percent'] == '1')) {
    $cdef = add_total_to_cdef($cdef,
			      $total_ids,
			      $graph_config['scale']);
    if ($show_total) {
      $cdef .= " LINE1:'total'#000000:'Total' ";
      if ($conf_graphreport_stats)
	$cdef .= legendEntry('total', $conf_graphreport_stat_items);
    }
  }

  // If we end up with the empty series it means that no RRD files matched.
  // This can happen if we are trying to create a report and metrics for
  // this host were not collected. If that happens we should create an
  // empty graph
  if ($seriesdef == "") {
    $rrdtool_graph['series'] =
      'HRULE:1#FFCC33:"No matching metrics detected or RRDs not readable"';
  } else {
    $rrdtool_graph['series'] = $seriesdef . ' ' . $cdef . ' ' . $graphdef;
  }

  return $rrdtool_graph;
}

function build_aggregate_graph_config_from_url($conf_graph_colors) {
  // If graph type is not specified default to line graph
  $graph_type = isset($_GET["gtype"]) &&
    in_array($_GET["gtype"], array("stack", "line", "percent")) ?
    $_GET["gtype"] : 'line';

  // If line width not specified default to 2
  $line_width = isset($_GET["lw"]) &&
    in_array($_GET["lw"], array("1", "2", "3")) ?
    $_GET["lw"] : '2';

  $graph_legend = isset($_GET["glegend"]) &&
    in_array($_GET["glegend"], array("show", "hide")) ?
    $_GET["glegend"] : 'show';

  /////////////////////////////////////////////////////////////////////////////
  // In order to reduce the load on the machine when someone is doing host
  // compare we look whether host list has been supplied via hl arg.
  // That way we do not have get metric cache
  /////////////////////////////////////////////////////////////////////////////
  if (isset($_GET['hl'])) {
    $counter = 0;
    $color_count = count($conf_graph_colors);
    $metric_name = str_replace("$",
			       "",
			       str_replace("^",
					   "",
					   $_GET['mreg'][0]));
    $host_list = explode(",", $_GET['hl']);
    foreach ($host_list as $host_cluster) {
      $color_index = $counter % $color_count;
      $parts = explode("|", $host_cluster);
      $hostname = $parts[0];
      $clustername = $parts[1];

      $series = array("hostname" => $hostname,
                      "clustername" => $clustername,
                      "fill" => "true",
                      "metric" => $metric_name,
                      "color" => $conf_graph_colors[$color_index],
                      "label" => $hostname,
                      "type" => $graph_type);

      if ($graph_type == "line" || $graph_type == "area") {
        $series['line_width'] = $line_width;
      } else if ($graph_type == "percent") {
        $graph_config['percent'] = "1";
      } else {
        $series['stack'] = "1";
      }
      $graph_config['series'][] = $series;

      $counter++;
    }
  } else {
    $exclude_host_from_legend_label =
      (array_key_exists('lgnd_xh', $_GET) &&
       $_GET['lgnd_xh'] == "true") ? TRUE : FALSE;

    $sortit = true;
    if($_GET['sortit'] == "false") {
      $sortit = false;
    }

    $graph_config =
      build_aggregate_graph_config($graph_type,
				   $line_width,
				   $_GET['hreg'],
				   $_GET['mreg'],
				   $graph_legend,
				   $exclude_host_from_legend_label,
                                   $sortit);
  }

  // Set up
  $graph_config["report_type"] = "standard";
  $graph_config["vertical_label"] =  isset($_GET["vl"]) ?
    sanitize($_GET["vl"])  : NULL;
  $graph_config["graph_scale"] = isset($_GET["gs"]) ?
    sanitize($_GET["gs"]) : NULL;
  $graph_config["scale"] = isset($_GET["scale"]) ?
    sanitize($_GET["scale"]) : NULL;
  $graph_config["show_total"] = isset($_GET["show_total"]) ?
    sanitize($_GET["show_total"]) : NULL;
  if (isset($_GET['title']) && $_GET['title'] != "")
    $graph_config["title"] = sanitize($_GET['title']);
  return $graph_config;
}

###############################################################################
# This builds rrdtool config for composite graphs that are defined directly
# in a view. It requires a view name and item id in order to be able
# to find the graph configuration. item_id needs to be unique otherwise
# only first definition will be used
###############################################################################
function rrdtool_graph_build_view_graph($rrdtool_graph,
					$view_name,
					$item_id,
					$context,
					$size,
					$conf_rrds,
					$conf_graphreport_stats,
					$conf_graphreport_stat_items) {
  $available_views = get_available_views();
  foreach ($available_views as $view) {
    // Find view settings
    if ($view_name == $view['view_name'])
      break;
  }

  foreach ($view['items'] as $view_item) {
    if ($item_id == $view_item['item_id'])
      break;
  }
  $rrdtool_graph =
    rrdtool_graph_merge_args_from_json($rrdtool_graph,
				       $view_item,
				       $context,
				       $size,
				       $conf_rrds,
				       $conf_graphreport_stats,
				       $conf_graphreport_stat_items);
  return array( $rrdtool_graph, $view_item);
}

///////////////////////////////////////////////////////////////////////////////
// Graphite graphs
///////////////////////////////////////////////////////////////////////////////
function build_graphite_series($context, $graph_config, $host_cluster = "") {
  $targets = array();
  $colors = array();

  // Keep track of stacked items
  $stacked = 0;

  foreach ($graph_config['series'] as $item) {
    if (isset($item['contexts']) and
	!in_array($context, $item['contexts']))
      continue;

    if ($item['type'] == "stack")
      $stacked++;

    if (isset($item['hostname']) && isset($item['clustername'])) {
      $host_cluster = $item['clustername'] . "." .
	str_replace(".", "_", $item['hostname']);
    }

    $targets[] =
      "target=". urlencode("alias(" .
			   $host_cluster . "." .
			   $item['metric'] .
			   ".sum,'" . $item['label'] .
			   "')");
    $colors[] = $item['color'];
  }
  $series = implode($targets, '&');
  $series .= "&colorList=" . implode($colors, ',');
  $series .= "&vtitle=" .
    urlencode(isset($graph_config['vertical_label']) ?
	      $graph_config['vertical_label'] : "");

  // Do we have any stacked elements. We assume if there is only one element
  // that is stacked that rest of it is line graphs
  if ($stacked > 0) {
    if ($stacked > 1)
      $series .= "&areaMode=stacked";
    else
      $series .= "&areaMode=first";
  }

  return $series;
}

function build_graphite_url($rrd_graphite_link,
			    $rrd_dir,
			    $conf,
			    $clustername,
			    $host,
			    $size,
			    $height,
			    $width,
			    $graph_config,
			    $context,
			    $metric_name,
			    $cs,
			    $ce,
			    $start,
			    $end,
			    $min,
			    $max,
			    $title,
			    $range) {
  // Check whether the link exists from Ganglia RRD tree to the graphite
  // storage/rrd_dir area
  if (! is_link($rrd_graphite_link)) {
    // Does the directory exist for the cluster. If not create it
    if (! is_dir($conf['graphite_rrd_dir'] . "/" .
		 str_replace(" ", "_", $clustername)) )
      mkdir($conf['graphite_rrd_dir'] . "/" .
	    str_replace(" ", "_", $clustername));
    symlink($rrd_dir, str_replace(" ", "_", $rrd_graphite_link));
  }

  // Generate host cluster string
  if (isset($clustername))
    $host_cluster = str_replace(" ", "_", $clustername) . "." . $host;
  else
    $host_cluster = $host;

  $height += 70;

  if ($size == "small")
    $width += 20;

  // If graph_config is already set we can use it immediately
  if (isset($graph_config)) {
    $target = build_graphite_series($context, $graph_config, "");
  } else {
    if (isset($_GET['g'])) {
      // if it's a report increase the height for additional 30 pixels
      $height += 40;
      $report_name = sanitize($_GET['g']);
      $report_definition_file = $conf['gweb_root'] . "/graph.d/" .
	$report_name . ".json";
      // Check whether report is defined in graph.d directory
      if (is_file($report_definition_file)) {
	$graph_config = json_decode(file_get_contents($report_definition_file),
				    TRUE);
      } else {
	error_log("There is JSON config file specifying $report_name.");
	exit(1);
      }

      if (isset($graph_config)) {
	switch ($graph_config["report_type"]) {
        case "template":
          $target = str_replace("HOST_CLUSTER",
				$conf['graphite_prefix'] . $host_cluster,
				$graph_config["graphite"]);
          break;

        case "standard":
          $target =
	    build_graphite_series($context,
				  $graph_config,
				  $conf['graphite_prefix'] . $host_cluster);
          break;

        default:
          error_log("No valid report_type specified in the " .
                    $report_name .
                    " definition.");
          break;
	}

	$title = $graph_config['title'];
      } else {
	error_log("Configuration file to $report_name exists; " .
		  "however, it doesn't appear it's a valid JSON file");
	exit(1);
      }
    } else {
      // It's a simple metric graph
      $vlabel = isset($_GET["vl"]) ? sanitize($_GET["vl"])  : NULL;
      $target = "target=" . $conf['graphite_prefix'] .
	"$host_cluster.$metric_name.sum&hideLegend=true&vtitle=" .
	urlencode($vlabel) .
	"&areaMode=all&colorList=". $conf['default_metric_color'];
      $title = " ";
    }
  }

  if ($cs)
    $start = date("H:i_Ymd", tzTimeToTimestamp($cs));

  if ($ce)
    $end = date("H:i_Ymd", tzTimeToTimestamp($ce));

  if ($max == 0)
    $max = "";

  $graphite_url = $conf['graphite_url_base'] .
    "?width=$width&height=$height&" .
    $target .
    "&from=" . $start . "&until=" . $end .
    "&yMin=" . $min . "&yMax=" . $max .
    "&bgcolor=FFFFFF&fgcolor=000000&title=" .
    urlencode($title . " last " . $range);

  return $graphite_url;
}

function build_value_for_json($value) {
  if (is_numeric($value))
    $val = floatval($value);
  else
    $val = $value;

  return $val;
}

function get_timestamp($time) {
  $timestamp = NULL;
  if ($time == "-now" || $time == "now") {
    $timestamp = time();
  } else if (preg_match("/\-([0-9]*)(s)/", $time, $out)) {
    $timestamp = time() - $out[1];
  } else if (is_numeric($time)) {
    $timestamp = $time;
  } else {
    $timestamp = tzTimeToTimestamp($time);
  }
  return $timestamp;
}

function get_nagios_events($overlay_nagios_base_url,
			   $host,
			   $start,
			   $end) {
  $nagios_events = array();
  $nagios_pull_url =
    $overlay_nagios_base_url .
    '/cgi-bin/api.cgi?action=host.gangliaevents' .
    '&host=' . urlencode($host) .
    '&start=' . urlencode($start) .
    '&end=' . urlencode($end);
  $raw_nagios_events =
    @file_get_contents(
      $nagios_pull_url,
      0,
      stream_context_create(array('http' => array('timeout' => 5),
				  'https' => array('timeout' => 5))));
  if (strlen($raw_nagios_events) > 3) {
    $nagios_events = json_decode($raw_nagios_events, TRUE);
    // Handle any "ERROR" formatted messages and wipe resulting array.
    if (isset($nagios_events['response_type']) &&
        $nagios_events['response_type'] == 'ERROR') {
      $nagios_events = array();
    }
  }
  return $nagios_events;
}

function rrdgraph_cmd_add_overlay_events($command,
					 $graph_start,
					 $graph_end,
					 $conf_overlay_events_color_map_file,
					 $conf_overlay_events_shade_alpha,
					 $conf_overlay_events_tick_alpha,
					 $conf_overlay_events_line_type,
					 $conf_graph_colors,
					 $nagios_events) {
  $debug = FALSE;

  // In order not to pollute the command line with all the possible VRULEs
  // we need to find the time range for the graph
  $graph_end_timestamp = get_timestamp($graph_end);
  $graph_start_timestamp = get_timestamp($graph_start);

  if (($graph_start_timestamp == NULL) ||
      ($graph_end_timestamp == NULL)) {
    error_log("process_over_events: ".
	      "Start/end timestamp(s) are NULL");
    return $command;
  }

  // Get array of events for time range
  $events_array = ganglia_events_get($graph_start_timestamp,
				     $graph_end_timestamp);

  if (empty($events_array))
    return $command;

  $event_color_json = file_get_contents($conf_overlay_events_color_map_file);
  if ($debug)
    error_log("$event_color_json");
  $event_color_array = json_decode($event_color_json, TRUE);
  $initial_event_color_count = count($event_color_array);
  $event_color_map = array();
  foreach ($event_color_array as $event_color_entry) {
    $event_color_map[$event_color_entry['summary']] =
      $event_color_entry['color'];
    if ($debug)
      error_log("Adding event color to map: " .
		$event_color_entry['summary'] .
		' ' .
		$event_color_entry['color']);
  }

  // Combine the nagios_events array, if it exists
  if (count($nagios_events) > 0) {
    // World's dumbest array merge:
    foreach ($nagios_events as $ne) {
      $events_array[] = $ne;
    }
  }

  foreach ($events_array as $key => $row) {
    $start_time[$key]  = $row['start_time'];
  }

  // Sort events in reverse chronological order
  array_multisort($start_time, SORT_DESC, $events_array);

  // Default to dashed line unless events_line_type is set to solid
  if ($conf_overlay_events_line_type == "solid")
    $overlay_events_line_type = "";
  else
    $overlay_events_line_type = ":dashes";

  // Preserve original rrdtool command. That's the one we'll run regex checks
  // against
  $original_command = $command;

  // Loop through all the events
  $color_count = count($conf_graph_colors);
  $counter = 0;
  $legend_items = array();
  foreach ($events_array as $id => $event) {
    $evt_start = $event['start_time'];
    // Make sure it's a number
    if (! is_numeric($evt_start)) {
      continue;
    }

    unset($evt_end);
    if (array_key_exists('end_time', $event) &&
	is_numeric($event['end_time'])) {
      $evt_end = $event['end_time'];
    }

    // If event start is less than start bail out of the loop since
    // there is nothing more to do since events are sorted in reverse
    // chronological order and these events are not gonna show up in
    // the graph
    $in_graph = (($evt_start >= $graph_start_timestamp) &&
		 ($evt_start <= $graph_end_timestamp)) ||
      (isset($evt_end) &&
       ($evt_end >= $graph_start_timestamp) &&
       ($evt_start <= $graph_end_timestamp));
    if (!$in_graph) {
      if ($debug)
	error_log("process_overlay_events: " .
		  "Event [$evt_start] does not overlap with graph " .
		  "[$graph_start_timestamp, $graph_end_timestamp]");
      continue;
    }

    // Compute the part of the event to be displayed
    $evt_start_in_graph_range = TRUE;
    if ($evt_start < $graph_start_timestamp) {
      $evt_start = $graph_start_timestamp;
      $evt_start_in_graph_range = FALSE;
    }

    $evt_end_in_graph_range = TRUE;
    if (isset($evt_end)) {
      if ($evt_end > $graph_end_timestamp) {
	$evt_end = $graph_end_timestamp;
	$evt_end_in_graph_range = FALSE;
      }
    } else
      $evt_end_in_graph_range = FALSE;

    if (preg_match("/" . $event["host_regex"] . "/", $original_command)) {
      if ($evt_start >= $graph_start_timestamp) {
	// Do we have the end timestamp.
	if (!isset($graph_end_timestamp) ||
	    ($evt_start < $graph_end_timestamp)) {
	  // This is a potential vector since this gets added to the
	  // command line_width TODO: Look over sanitize
	  $summary =
	    isset($event['summary']) ? sanitize($event['summary']) : "";

	  // We need to keep track of summaries so that if we have identical
	  // summaries e.g. Deploy we can use the same color
	  if (array_key_exists($summary, $event_color_map)) {
	    $color = $event_color_map[$summary];
	    if ($debug)
	      error_log("process_overlay_events: " .
			"Found existing color: $summary $color");
	    // Reset summary to empty string if it is already present in
	    // the legend
	    if (array_key_exists($summary, $legend_items))
	      $summary = "";
	    else
	      $legend_items[$summary] = TRUE;
	  } else {
	    // Haven't seen this summary before. Assign it a color
	    $color_index = count($event_color_map) % $color_count;
	    $color = $conf_graph_colors[$color_index];
	    $event_color_map[$summary] = $color;
	    $event_color_array[] = array('summary' => $summary,
					 'color' => $color);
	    if ($debug)
	      error_log("process_overlay_events: " .
			"Adding new event color: $summary $color");
	  }

	  if (isset($evt_end)) {
	    // Attempt to draw a shaded area between start and end points.
	    // Force solid line for ranges
	    $overlay_events_line_type = "";

	    $start_vrule = '';
	    if ($evt_start_in_graph_range)
	      $start_vrule = " VRULE:" . $evt_start .
		"#$color" . $conf_overlay_events_tick_alpha .
		":\"" . $summary . "\"" . $overlay_events_line_type;

	    $end_vrule = '';
	    if ($evt_end_in_graph_range)
	      $end_vrule = " VRULE:" . $evt_end .
		"#$color" . $conf_overlay_events_tick_alpha .
		':""' . $overlay_events_line_type;

	    // We need a dummpy DEF statement, because RRDtool is too stupid
	    // to plot graphs without a DEF statement.
	    // We can't count on a static name, so we have to "find" one.
	    if (preg_match("/DEF:['\"]?(\w+)['\"]?=/", $command, $matches)) {
	      // stupid rrdtool limitation.
	      $area_cdef =
		" CDEF:area_$counter=$matches[1],POP," .
		"TIME,$evt_start,GT,1,UNKN,IF,TIME,$evt_end,LT,1,UNKN,IF,+";
	      $area_shade = $color . $conf_overlay_events_shade_alpha;
	      $area = " TICK:area_$counter#$area_shade:1";
	      if (!$evt_start_in_graph_range)
		$area .= ':"' . $summary . '"';
	      $command .= "$area_cdef $area $start_vrule $end_vrule";
	    } else {
	      error_log("No DEF statements found in \$command?!");
	    }
	  } else {
	    $command .= " VRULE:" . $evt_start . "#" . $color .
	      ":\"" . $summary . "\"" . $overlay_events_line_type;
	  }
	  $counter++;
	} else {
	  if ($debug)
	    error_log("process_overlay_events: " .
		      "Event start [$evt_start] >= graph end " .
		      "[$graph_end_timestamp]");
	}
      } else {
	if ($debug)
	  error_log("process_overlay_events: " .
		    "Event start [$evt_start] < graph start " .
		    "[$graph_start_timestamp]");
      }
    } // end of if ( preg_match ...
    else {
      // error_log("Doesn't match host_regex");
      ; //PHPCS
    }
  } // end of foreach ( $events_array ...

  unset($events_array);
  if (count($event_color_array) > $initial_event_color_count) {
    $event_color_json = json_encode($event_color_array);
    file_put_contents($conf_overlay_events_color_map_file,
		      $event_color_json);
  }
  return $command;
}

function process_graph_arguments($graph) {
  $graph_report = $graph;
  $graph_arguments = array();

  $pos = strpos($graph, ",");
  if ($pos !== FALSE) {
    $graph_report = substr($graph, 0, $pos);
    $args = str_getcsv(substr($graph, $pos + 1), ",", "'");
    /*
      ob_start();
      var_dump($args);
      $result = ob_get_clean();
      error_log("args = $result");
    */
    foreach ($args as $arg) {
      if (is_numeric($arg)) {
	if (ctype_digit($arg))
	  $graph_arguments[] = intval($arg);
	else
	  $graph_arguments[] = floatval($arg);
      } else
	$graph_arguments[] = $arg;
    }
  }
  return array($graph_report, $graph_arguments);
}


##############################################################################
# Build rrdtool command line
##############################################################################
function rrdgraph_cmd_build($rrdtool_graph,
			    $vlabel,
			    $conf,
			    $graph_config,
			    $max,
			    $min,
			    $user_trend_range,
			    $user_trend_history,
			    $user_trend_line,
			    $user_clustername,
			    $rrd_options,
			    $range,
			    $title,
			    $subtitle,
			    $size,
			    $context,
			    $grid,
			    $raw_host,
			    $graph,
			    $graph_arguments) {
  if (! isset($graph_config)) {
    $php_report_file = $conf['graphdir'] . "/" . $graph . ".php";
    $json_report_file = $conf['graphdir'] . "/" . $graph . ".json";

    if (is_file($php_report_file)) {
      // Check for path traversal issues by making sure real path is
      // actually in graphdir
      if (dirname(realpath($php_report_file)) !=  $conf['graphdir']) {
	$rrdtool_graph['series'] =
	  'HRULE:1#FFCC33:"Check \$conf[graphdir] should not be relative path"';
      } else {
	$graph_function = "graph_${graph}";
	if ($conf['enable_pass_in_arguments_to_optional_graphs'] &&
	    count($graph_arguments)) {
	  $rrdtool_graph['arguments'] = $graph_arguments;
	  // Pass by reference call, $rrdtool_graph modified inplace
	  $graph_function($rrdtool_graph);
	  unset($rrdtool_graph['arguments']);
	} else {
	  $graph_function($rrdtool_graph);
	}
      }
    } else if (is_file($json_report_file )) {
      if (dirname(realpath($json_report_file)) !=  $conf['graphdir']) {
	$rrdtool_graph['series'] =
	  'HRULE:1#FFCC33:"Check \$conf[graphdir] should not be relative path"';
      } else {
	$graph_config =
	  json_decode(file_get_contents($json_report_file), TRUE );

	// We need to add hostname and clustername if it's not specified
	foreach (array_keys($graph_config['series']) as $series_id) {
	  if (! isset($graph_config['series'][$index]['hostname'])) {
	    $graph_config['series'][$series_id]['hostname'] = $raw_host;
	    if (isset($grid))
	      $graph_config['series'][$series_id]['clustername'] = $grid;
	    else
	      $graph_config['series'][$series_id]['clustername'] =
		$user_clustername;
	  }
	}

	$rrdtool_graph =
	  rrdtool_graph_merge_args_from_json($rrdtool_graph,
					     $graph_config,
					     $context,
					     $size,
					     $conf['rrds'],
					     $conf['graphreport_stats'],
					     $conf['graphreport_stat_items']);
      }
    }
  } else {
    $rrdtool_graph =
      rrdtool_graph_merge_args_from_json($rrdtool_graph,
					 $graph_config,
					 $context,
					 $size,
					 $conf['rrds'],
					 $conf['graphreport_stats'],
					 $conf['graphreport_stat_items']);
  }

  // We must have a 'series' value, or this is all for naught
  if (!array_key_exists('series', $rrdtool_graph) ||
      !strlen($rrdtool_graph['series'])) {
    $rrdtool_graph['series'] =
      'HRULE:1#FFCC33:"Empty RRDtool command. Likely bad graph config"';
  }

  // Make small graphs (host list) cleaner by removing the too-big
  // legend: it is displayed above on larger cluster summary graphs.
  if (($size == "small" and ! isset($subtitle)) ||
      ($graph_config["glegend"] == "hide"))
    $rrdtool_graph['extras'] = isset($rrdtool_graph['extras']) ?
      $rrdtool_graph['extras'] . " -g" : " -g" ;

  // add slope-mode if rrdtool_slope_mode is set
  if (isset($conf['rrdtool_slope_mode']) &&
      $conf['rrdtool_slope_mode'] == TRUE)
    $rrdtool_graph['slope-mode'] = '';

  if (isset($rrdtool_graph['title']) && isset($title)) {
    if ($conf['decorated_graph_title'])
      $rrdtool_graph['title'] = $title . " " .
	$rrdtool_graph['title'] .
	" last $range";
  }

  $command = '';
  if (isset($_SESSION['tz']) && ($_SESSION['tz'] != ''))
    $command .= "TZ='" . $_SESSION['tz'] . "' ";

  $command .=
    $conf['rrdtool'] .
    " graph" .
    (isset($_GET["verbose"]) ? 'v' : '') .
    " - $rrd_options ";

  // Look ahead six months
  if ($user_trend_line) {
    // We may only want to use last x months of data since for example
    // if we are trending disk we may have added a disk recently which will
    // skew a trend line. By default we'll use 6 months however we'll let
    // user define this if they want to.
    $rrdtool_graph['start'] = "-" . $user_trend_history * 2592000 . "s";
    // Project the trend line this many months ahead
    $rrdtool_graph['end'] = "+" . $user_trend_range * 2592000 . "s";
  }

  if ($max)
    $rrdtool_graph['upper-limit'] = $max;

  if ($min)
    $rrdtool_graph['lower-limit'] = $min;

  if (isset($graph_config['percent']) &&
      $graph_config['percent'] == '1' ) {
    $rrdtool_graph['upper-limit'] = 100;
    $rrdtool_graph['lower-limit'] = 0;
  }

  if ($max ||
      $min ||
      (isset($graph_config['percent']) && $graph_config['percent'] == '1'))
    $rrdtool_graph['extras'] = isset($rrdtool_graph['extras']) ?
      $rrdtool_graph['extras'] . " --rigid" : " --rigid" ;

  if (isset($graph_config['graph_scale'])) {
    // Log scale support
    if ($graph_config['graph_scale'] == 'log') {
      $rrdtool_graph['extras'] = isset($rrdtool_graph['extras']) ?
	$rrdtool_graph['extras'] . " --logarithm --units=si" :
	" --logarithm --units=si" ;
      if (!isset($rrdtool_graph['lower-limit']) ||
	  $rrdtool_graph['lower-limit'] < 1) {
	// With log scale, the lower limit *has* to be 1 or greater.
	$rrdtool_graph['lower-limit'] = 1;
      }
    }
  }
  if ($conf['rrdtool_base_1024'] &&
      in_array($vlabel, array('bytes',
			      'Bytes',
			      'bytes/s',
			      'Bytes/s',
			      'kB',
			      'MB',
			      'GB',
			      'bits',
			      'Bits',
			      'bits/s',
			      'Bits/s'))) {
    // Set graph base value to 1024
    $rrdtool_graph['extras'] = isset($rrdtool_graph['extras']) ?
      $rrdtool_graph['extras'] . " --base=1024" : " --base=1024" ;
  }

  // The order of the other arguments isn't important, except for the
  // 'extras' and 'series' values.  These two require special handling.
  // Otherwise, we just loop over them later, and tack $extras and
  // $series onto the end of the command.
  foreach (array_keys($rrdtool_graph) as $key) {
    if (preg_match('/extras|series/', $key))
      continue;

    $value = $rrdtool_graph[$key];

    if (preg_match('/\W/', $value)) {
      // more than alphanumerics in value, so quote it
      $value = "'$value'";
    }
    $command .= " --$key $value";
  }

  // And finish up with the two variables that need special handling.
  // See above for how these are created
  $command .= array_key_exists('extras', $rrdtool_graph) ?
    ' ' . $rrdtool_graph['extras'] . ' ' : '';
  $command .= " $rrdtool_graph[series]";
  return array($command, $rrdtool_graph);
}


#############################################################################
# Output data in external non-image formats e.g. CSV, JSON etc.
#############################################################################
function output_data_to_external_format($rrdtool_graph_series,
					$rrdtool_graph_start,
					$rrdtool_graph_end,
					$rrdtool_graph_title,
					$rrdtool,
					$graphlot_output,
					$csv_output,
					$flot_output,
					$live_dashboard,
					$json_output,
					$step,
					$rrd_options) {
  // First find RRDtool DEFs by parsing $rrdtool_graph['series']
  preg_match_all("/([^V]DEF|CDEF):(.*)(:AVERAGE|\s)/",
		 " " . $rrdtool_graph_series,
		 $matches);

  $rrdtool_graph_args = "";
  foreach ($matches[0] as $value) {
    $rrdtool_graph_args .= $value . " ";
  }

  preg_match_all("/(LINE[0-9]*|AREA|STACK):\'[^']*\'[^']*\'[^']*\'[^ ]* /",
		 " " . $rrdtool_graph_series,
		 $matches);
  foreach ($matches[0] as $value) {
    if (preg_match("/(LINE[0-9]*:\'|AREA:\'|STACK:\')([^']*)(\')([^']*)(\')([^']*)(')/", $value, $out)) {
      $ds_name = $out[2];
      $metric_type = "line";
      if (preg_match("/(STACK:|AREA:)/", $value, $ignore))
	$metric_type = "stack";
      $metric_name = $out[6];
      $ds_attr = array("ds_name" => $ds_name,
		       "cluster_name" => '',
		       "graph_type" => $metric_type,
		       "host_name" => '',
		       "metric_name" => $metric_name);

      // Add color if it exists
      $pos_hash = strpos($out[4], '#');
      if ($pos_hash !== FALSE) {
	$pos_colon = strpos($out[4], ':');
	if ($pos_colon !== FALSE)
	  $ds_attr['color'] = substr($out[4],
				     $pos_hash,
				     $pos_colon - $pos_hash);
	else
	  $ds_attr['color'] = substr($out[4], $pos_hash);
      }

      if (strpos($value, ":dashes") !== FALSE)
	$ds_attr['dashes'] = 1;

      $output_array[] = $ds_attr;
      $rrdtool_graph_args .=
	" " . "XPORT:'" . $ds_name . "':'" . $metric_name . "' ";
    }
  }

  // This command will export values for the specified format in XML

  $maxRows = '';
  if ($step) {
    /*
      Allow a custom step, if it was specified by the user.
      Also, we need to specify a --maxrows in case the number
      of rows with $step end up being higher than
      rrdxport's default (in which case the step is changed
      to fit inside the default --maxrows), but we also need
      to guard against "underflow" because rrdxport craps out
      when --maxrows is less than 10.
    */

    $maxRows = max(10, round(($rrdtool_graph_end -
			      $rrdtool_graph_start) / $step));
  } else if (isset($_GET['maxrows']) && is_numeric($_GET['maxrows'])) {
    $maxRows = max(10, $_GET['maxrows']);
  }

  $command = '';
  if (isset($_SESSION['tz']) && ($_SESSION['tz'] != ''))
    $command .= "TZ='" . $_SESSION['tz'] . "' ";

  # Get rrdtool version
  $rrdtool_version = array();
  exec($rrdtool, $rrdtool_version);
  $rrdtool_version = explode(" ", $rrdtool_version[0]);
  $rrdtool_version = $rrdtool_version[1];

  $rrdtool_timestamp = '';
  if (version_compare($rrdtool_version, '1.5.999', '>='))
    $rrdtool_timestamp = '-t';
  elseif (version_compare($rrdtool_version, '1.4.999', '>='))
    $rrdtool_timestamp = ''; // timestamp always missing (#672)

  $command .= $rrdtool .
    " xport " . $rrdtool_timestamp . " --start '" . $rrdtool_graph_start .
    "' --end '" .  $rrdtool_graph_end . "' " .
    ($step ? " --step '" . $step . "' " : '') .
    ($maxRows ? " --maxrows '" . $maxRows . "' " : '') .
    $rrd_options . " " .
    $rrdtool_graph_args;

  // Read in the XML
  $string = "";
  if (strlen($command) < 100000) {
    $fp = popen($command, "r");
    while (!feof($fp)) {
      $buffer = fgets($fp, 4096);
      $string .= $buffer;
    }
  } else {
    $tempfile = tempnam("/tmp", "ganglia-graph-json");
    file_put_contents($tempfile, $command);
    exec("/bin/bash $tempfile", $tempout);
    foreach ($tempout as $line) {
      $string .= $line;
    }
    unlink($tempfile);
  }

  // Parse it
  $xml = simplexml_load_string($string);

  // If there are multiple metrics columns will be > 1
  $num_of_metrics = $xml->meta->columns;

  $metric_values = array();
  // Build the metric_values array

  $x = 0;
  $rows = $xml->data->row->count();
  foreach ($xml->data->row as $objects) {
    $values = get_object_vars($objects);

    if (!isset($values['t'])) {
      $values['t'] = $rrdtool_graph_start + $x *
        ($step ? $step : ($rrdtool_graph_end - $rrdtool_graph_start) / ($rows - 1));
      }

    // If $values["v"] is an array we have multiple data sources/metrics and
    // we need to iterate over those
    if (is_array($values["v"])) {
      foreach ($values["v"] as $key => $value) {
	$output_array[$key]["datapoints"][] =
	  array(build_value_for_json($value), intval($values['t']));
      }
    } else {
      $output_array[0]["datapoints"][] =
	array(build_value_for_json($values["v"]), intval($values['t']));
    }
    $x++;
  }

  // If JSON output request simple encode the array as JSON
  if ($json_output) {
    // First let's check if JSON output is requested for
    // Live Dashboard and we are outputting aggregate graph.
    // If so we need to add up all the values
    if ($live_dashboard && count($output_array) > 1) {
      $summed_output = array();
      foreach ($output_array[0]['datapoints'] as $index => $datapoint) {
	// Data point is an array with value and UNIX time stamp. Initialize
	// summed output as 0
	if (is_numeric($datapoint[0]) && is_numeric($datapoint[1])) {
	  $summed_output[$index] = array(0, $datapoint[1]);
	  $output_array_length = count($output_array);
	  for ($i = 0 ; $i < $output_array_length; $i++) {
	    $summed_output[$index][0] +=
	      $output_array[$i]['datapoints'][$index][0];
	  }
	}
      }

      unset($output_array);
      $output_array[0]['datapoints'] = $summed_output;
    }
    header("Content-type: application/json");
    header("Content-Disposition: inline; filename=\"ganglia-metrics.json\"");
    print json_encode($output_array);
  }

  // If Flot output massage the data JSON
  if ($flot_output) {
    foreach ($output_array as $metric_array) {
      foreach ($metric_array['datapoints'] as $values) {
	$data_array[] = array ($values[1] * 1000, $values[0]);
      }

      $gdata = array('label' =>
		     strip_domainname($metric_array['host_name']) .
		     " " .
		     $metric_array['metric_name'],
		     'data' => $data_array);

      if (array_key_exists('color', $metric_array))
	$gdata['color'] = $metric_array['color'];

      if (array_key_exists('dashes', $metric_array)) {
	$gdata['dashes'] = array();
	$gdata['dashes']['show'] = True;
	$gdata['dashes']['dashLength'] = 5;
	$gdata['lines']['show'] = True;
	$gdata['lines']['lineWidth'] = 0;
      }

      if ($metric_array['graph_type'] == "stack")
	$gdata['stack'] = '1';

      $gdata['graph_title'] = $rrdtool_graph_title;

      $flot_array[] = $gdata;

      unset($data_array);
    }
    header("Content-type: application/json");
    print json_encode($flot_array);
  }

  if ($csv_output) {
    header("Content-Type: application/csv");
    header("Content-Disposition: inline; filename=\"ganglia-metrics.csv\"");

    print "Timestamp";

    // Print out headers
    foreach ($output_array as $series) {
      print "," . $series["metric_name"];
    }

    print "\n";

    foreach ($output_array[0]['datapoints'] as $index => $point) {
      print date("c", $point[1]); // timestamp
      // metric values
      foreach ($output_array as $series) {
	print "," . $series["datapoints"][$index][0];
      }
      print "\n";
    }
  }

  // Implement Graphite style Raw Data
  if ($graphlot_output) {
    header("Content-Type: application/json");

    $last_index = count($output_array[0]["datapoints"]) - 1;

    $output_vals['step'] =
      $output_array[0]["datapoints"][1][1] -
      $output_array[0]["datapoints"][0][1];
    $output_vals['name'] = "stats." . $output_array[0]["metric_name"];
    $output_vals['start'] = $output_array[0]["datapoints"][0][1];
    $output_vals['end'] = $output_array[0]["datapoints"][$last_index][1];

    foreach ($output_array[0]["datapoints"] as $array) {
      $output_vals['data'][] = $array[0];
    }

    print json_encode(array($output_vals, $output_vals));
  }
}

function execute_graph_command($graph_engine, $command) {
  global $debug, $user;

  // always modified
  header ("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
  header ("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
  header ("Pragma: no-cache"); // HTTP/1.0
  if ($debug > 2) {
    header ("Content-type: text/html");
    print "<html><body>";

    switch ($graph_engine) {
    case "flot":
    case "rrdtool":
      print htmlentities($command);
    break;
    case "graphite":
      print $command;
      break;
    }
    print "</body></html>";
  } else {
    if ( $user['image_format'] == "svg" )
      header ("Content-type: image/svg+xml");
    else
      header ("Content-type: image/png");
    switch ($graph_engine) {
    case "flot":
    case "rrdtool":
      if (strlen($command) < 100000) {
	my_passthru($command);
      } else {
	$tf = tempnam("/tmp", "ganglia-graph");
	file_put_contents($tf, $command);
	my_passthru("/bin/bash $tf");
	unlink($tf);
      }
    break;
    case "graphite":
      echo file_get_contents($command);
      break;
    }
  }
}

$gweb_root = dirname(__FILE__);

$metric_name = isset($_GET["m"]) ? sanitize($_GET["m"]) : NULL;
// In clusterview we may supply report as metric name so let's make sure it
// doesn't treat it as a metric
if ( preg_match("/_report$/", $metric_name) && !isset($_GET["g"]) ) {
  $graph = $metric_name;
} else {
  // If graph arg is not specified default to metric
  $graph = isset($_GET["g"]) ? sanitize($_GET["g"]) : "metric";
}

if ($conf['enable_pass_in_arguments_to_optional_graphs'])
  list($graph, $graph_arguments) = process_graph_arguments($graph);
else
  $graph_arguments = array();

$grid = isset($_GET["G"]) ? sanitize( $_GET["G"]) : NULL;
$self = isset($_GET["me"]) ? sanitize( $_GET["me"]) : NULL;
$vlabel = isset($_GET["vl"]) ? sanitize($_GET["vl"])  : NULL;
$value = isset($_GET["v"]) ? sanitize ($_GET["v"]) : NULL;
// Max, min, critical and warning values
$max = isset($_GET["x"]) && is_numeric($_GET["x"]) ? $_GET["x"] : NULL;
$min = isset($_GET["n"]) && is_numeric($_GET["n"]) ? $_GET["n"] : NULL;

$summary = isset($_GET["su"]) ? 1 : 0;
$debug = isset($_GET['debug']) ? clean_number(sanitize($_GET["debug"])) : 0;
$user['time_shift'] = isset($_GET["ts"]) ?
  clean_number(sanitize($_GET["ts"])) : 0;

$user['json_output'] = isset($_GET["json"]) ? 1 : NULL;
// Request for live dashboard
if (isset($_REQUEST['live'])) {
  $user['live_dashboard'] = 1;
  $user['json_output'] = 1;
} else {
  $user['live_output'] = NULL;
}
$user['csv_output'] = isset($_REQUEST["csv"]) ? 1 : NULL;
$user['graphlot_output'] = isset($_REQUEST["graphlot"]) ? 1 : NULL;
$user['flot_output'] = isset($_REQUEST["flot"]) ? 1 : NULL;

# Check if user is asking for an alternate image format e.g. SVG
$user['image_format'] = (isset($_REQUEST["image_format"]) and $_REQUEST["image_format"] == "svg") ? "svg" : NULL;


$user['trend_line'] = isset($_REQUEST["trend"]) ? 1 : NULL;
// How many months ahead to extend the trend e.g. 6 months
$user['trend_range'] =
  isset($_GET["trendrange"]) && is_numeric($_GET["trendrange"]) ?
  $_GET["trendrange"] : 6;

$user['trend_history'] =
  isset($_GET["trendhistory"]) && is_numeric($_GET["trendhistory"]) ?
  $_GET["trendhistory"] : 6;

// Get hostname
$raw_host = isset($_GET["h"]) ? sanitize($_GET["h"]) : "__SummaryInfo__";

// For graphite purposes we need to replace all dots with underscore.
// dot separates subtrees in graphite
$graphite_host = str_replace(".", "_", $raw_host);

$size =
  isset($_GET["z"]) &&
  in_array($_GET['z'], $conf['graph_sizes_keys']) ?
  $_GET["z"] : 'default';

if (isset($_GET['height']) && is_numeric($_GET['height']))
  $height = $_GET['height'];
else
  $height  = $conf['graph_sizes'][$size]['height'];

if (isset($_GET['width']) && is_numeric($_GET['width']))
  $width =  $_GET['width'];
else
  $width = $conf['graph_sizes'][$size]['width'];

$fudge_0 = $conf['graph_sizes'][$size]['fudge_0'];
$fudge_1 = $conf['graph_sizes'][$size]['fudge_1'];
$fudge_2 = $conf['graph_sizes'][$size]['fudge_2'];

///////////////////////////////////////////////////////////////////////////
// Set some variables depending on the context. Context is set in
// get_context.php
///////////////////////////////////////////////////////////////////////////
$rrd_dir = $conf['rrds'];
$rrd_graphite_link = $conf['graphite_rrd_dir'];
switch ($context) {
case "meta":
  $rrd_dir .= "/__SummaryInfo__";
  $rrd_graphite_link .= "/__SummaryInfo__";
  $title = "$self ${conf['meta_designator']}";
  break;
case "grid":
  $rrd_dir .= "/$grid/__SummaryInfo__";
  $rrd_graphite_link .= "/$grid/__SummaryInfo__";
  if (preg_match('/grid/i', $gridname))
    $title = $gridname;
  else
    $title = "$gridname ${conf['meta_designator']}";
  break;
case "cluster":
  $rrd_dir .= "/" . $user['clustername'] . "/__SummaryInfo__";
  $rrd_graphite_link .= "/" . $user['clustername'] ."/__SummaryInfo__";
  if (preg_match('/cluster/i', $user['clustername']))
    $title = $user['clustername'];
  else
    $title = $user['clustername'] . " Cluster";
  break;
case "host":
  $rrd_dir .= "/" . $user['clustername'] . "/$raw_host";
  $rrd_graphite_link .= "/" . $user['clustername'] . "/" . $graphite_host;
  // Add hostname to report graphs' title in host view
  if ($graph != 'metric')
    if ($conf['strip_domainname'])
      $title = strip_domainname($raw_host);
    else
      $title = $raw_host;
  break;
default:
  unset($rrd_dir);
  unset($rrd_graphite_link);
  break;
}

$resource = GangliaAcl::ALL_CLUSTERS;
if ($context == "grid") {
  $resource = $grid;
} else if ($context == "cluster" || $context == "host") {
  $resource = $user['clustername'];
}

if (! checkAccess($resource, GangliaAcl::VIEW, $conf)) {
  header("HTTP/1.1 403 Access Denied");
  header("Content-type: image/jpg");
  echo file_get_contents($gweb_root.'/img/access-denied.jpg');
  die();
}

// Aliases for $user['cs'] and $user['ce'] (which are set in get_context.php).
$cs = $user['cs'];
if ($cs and (is_numeric($cs) or strtotime($cs)))
  $start = $cs;

$ce = $user['ce'];
if ($ce and (is_numeric($ce) or strtotime($ce)))
  $end = $ce;

// Set some standard defaults that don't need to change much
$rrdtool_graph = array('start' => $start,
		       'end' => $end,
		       'width' => $width,
		       'height' => $height);

// automatically strip domainname from small graphs where it won't fit
if ($size == "small")
  $conf['strip_domainname'] = TRUE;

$load_color = isset($_GET["l"]) &&
  is_valid_hex_color(rawurldecode($_GET['l'])) ?
  sanitize($_GET["l"]) : NULL;
if (! isset($subtitle) && $load_color)
  $rrdtool_graph['color'] = "BACK#'$load_color'";

if ($debug)
  error_log("Graph [$graph] in context [$context]");

/*
   If we have $graph, then a specific report was requested, such as
   "network_report" or "cpu_report.  These graphs usually have some
   special logic and custom handling required, instead of simply
   plotting a single metric.  If $graph is not set, then we are (hopefully),
   plotting a single metric, and will use the commands in the metric.php file.

   With modular graphs, we look for a "${graph}.php" file, and if it
   exists, we source it, and call a pre-defined function name.
   The current scheme for the function names is:   'graph_' +
   <name_of_report>.  So a 'cpu_report' would call graph_cpu_report(),
   which would be found in the cpu_report.php file.

   These functions take the $rrdtool_graph array as an argument.
   This variable is PASSED BY REFERENCE, and will be modified by the
   various functions.  Each key/value pair represents an option/argument,
   as passed to the rrdtool program.  Thus, $rrdtool_graph['title']
   will refer to the --title option for rrdtool, and pass the array
   value accordingly.

   There are two exceptions to:  the 'extras' and 'series' keys in
   $rrdtool_graph.  These are assigned to $extras and $series
   respectively, and are treated specially.  $series will contain
   the various DEF, CDEF, RULE, LINE, AREA, etc statements that
   actually plot the charts.  The rrdtool program requires that
   this come *last* in the argument string; we make sure that it
   is put in it's proper place.  The $extras variable is used
   for other arguemnts that may not fit nicely for other reasons.
   Complicated requests for --color, or adding --ridgid, for example.
   It is simply a way for the graph writer to add an arbitrary
   options when calling rrdtool, and to forcibly override other
   settings, since rrdtool will use the last version of an
   option passed. (For example, if you call 'rrdtool' with
   two --title statements, the second one will be used.)

   See ${conf['graphdir']}/sample.php for more documentation, and
   details on the common variables passed and used.
 */

// Calculate time range.
$sourcetime = isset($_GET["st"]) ? clean_number(sanitize($_GET["st"])) : NULL;
if ($sourcetime) {
  $end = $sourcetime;
  // Get_context makes start negative.
  $start = $sourcetime + $start;
}

// Fix from Phil Radden, but step is not always 15 anymore.
if ($range == "month")
  $rrdtool_graph['end'] = floor($rrdtool_graph['end'] / 672) * 672;

///////////////////////////////////////////////////////////////////////////////
// Are we generating aggregate graphs
///////////////////////////////////////////////////////////////////////////////
if (isset($_GET["aggregate"]) && $_GET['aggregate'] == 1) {
  // Set start time, assume that start is negative number of seconds
  $start = time() + $start;

  $graph_config = build_aggregate_graph_config_from_url($conf['graph_colors']);

  // Reset graph title
  $title = (isset($_GET['title']) && $_GET['title'] != "") ? "" : "Aggregate";
}

///////////////////////////////////////////////////////////////////////////
// Composite graphs/reports specified in a view
///////////////////////////////////////////////////////////////////////////
$user['view_name'] = isset($_GET["vn"]) ? sanitize ($_GET["vn"]) : NULL;
$user['item_id'] = isset($_GET["item_id"]) ? sanitize ($_GET["item_id"]) : NULL;
if ($user['view_name'] && $user['item_id']) {
  list ($rrdtool_graph, $graph_config) =
    rrdtool_graph_build_view_graph($rrdtool_graph,
				   $user['view_name'],
				   $user['item_id'],
				   $context,
				   $size,
				   $conf['rrds'],
				   $conf['graphreport_stats'],
				   $conf['graphreport_stat_items']);
  # Reset title
  $title ="";
}

//////////////////////////////////////////////////////////////////////////////
// Build graph execution command based graph engine
//////////////////////////////////////////////////////////////////////////////
$graphite_url = '';
$command = '';
switch ($conf['graph_engine']) {
  case "flot":
  case "rrdtool":
    // If graph is based on PHP report file then include it
    // at the global scope
    if (! isset($graph_config)) {
      $php_report_file = $conf['graphdir'] . "/" . $graph . ".php";
      if (is_file($php_report_file) &&
	  (dirname(realpath($php_report_file)) ==  $conf['graphdir'])) {
	if (($graph == "metric") &&
	    isset($_GET['title']) &&
	    $_GET['title'] !== '')
	  $metrictitle = sanitize($_GET['title']);
	include_once $php_report_file;
      }
    }

    list($command,
       $rrdtool_graph) = rrdgraph_cmd_build($rrdtool_graph,
					    $vlabel,
					    $conf,
					    $graph_config,
					    $max,
					    $min,
					    $user['trend_range'],
					    $user['trend_history'],
					    $user['trend_line'],
					    $user['clustername'],
					    $rrd_options,
					    $range,
					    $title,
					    $subtitle,
					    $size,
					    $context,
					    $grid,
					    $raw_host,
					    $graph,
					    $graph_arguments);
    break;

    case "graphite":
    $graphite_url = build_graphite_url($rrd_graphite_link,
				       $rrd_dir,
				       $conf,
				       $user['clustername'],
				       $graphite_host,
				       $size,
				       $height,
				       $width,
				       $graph_config,
				       $context,
				       $metric_name,
				       $cs,
				       $ce,
				       $start,
				       $end,
				       $min,
				       $max,
				       $title,
				       $range);
    break;
}

// Output graph data to external formats
if ($user['json_output'] ||
    $user['csv_output'] ||
    $user['flot_output'] ||
    $user['graphlot_output']) {
  if ($conf['graph_engine'] == "graphite") {
    if ($user['json_output'] == 1) {
      $output_format = "json";
    } elseif ($user['csv_output'] == 1) {
      $output_format = "csv";
    }
    echo file_get_contents($graphite_url . "&format=" . $output_format);
  } else {
    output_data_to_external_format($rrdtool_graph['series'],
				   $rrdtool_graph['start'],
				   $rrdtool_graph['end'],
				   $rrdtool_graph['title'],
				   $conf['rrdtool'],
				   $user['graphlot_output'],
				   $user['csv_output'],
				   $user['flot_output'],
				   $user['live_dashboard'],
				   $user['json_output'],
				   $user['step'],
				   $rrd_options);
  }
  exit(0);
}

//////////////////////////////////////////////////////////////////////////////
// Check whether user wants to plot overlay events on graphs
//////////////////////////////////////////////////////////////////////////////
$showEvents = isset($_GET["event"]) ? sanitize ($_GET["event"]) : "show";
if ($showEvents == "show" &&
    $conf['overlay_events'] &&
    $conf['graph_engine'] == "rrdtool" &&
    ! in_array($range, $conf['overlay_events_exclude_ranges']) &&
    ! $user['trend_line']) {
  $nagios_events = array();
  if ($conf['overlay_nagios_events'])
    $nagios_events = get_nagios_events($conf['overlay_nagios_base_url'],
				       $raw_host,
				       $start,
				       $end);
  $command =
    rrdgraph_cmd_add_overlay_events($command,
				    $rrdtool_graph['start'],
				    $rrdtool_graph['end'],
				    $conf['overlay_events_color_map_file'],
				    $conf['overlay_events_shade_alpha'],
				    $conf['overlay_events_tick_alpha'],
				    $conf['overlay_events_line_type'],
				    $conf['graph_colors'],
				    $nagios_events);
}

////////////////////////////////////////////////////////////////////////////////
// Add a trend line
////////////////////////////////////////////////////////////////////////////////
if ($user['trend_line']) {
  $command .= " VDEF:D2=sum,LSLSLOPE VDEF:H2=sum,LSLINT CDEF:avg2=sum,POP,D2,COUNT,*,H2,+";
  $command .= " 'LINE3:avg2" . $conf['trend_line_color'] . ":Trend:dashes'";
}

////////////////////////////////////////////////////////////////////////////////
// Timeshift is only available to metric graphs
////////////////////////////////////////////////////////////////////////////////
if ($user['time_shift'] && $graph == "metric") {
  preg_match_all("/(DEF|CDEF):((([^ \"'])+)|(\"[^\"]*\")|('[^']*'))+/",
                 " " . $rrdtool_graph['series'],
                 $matches);

  // Only do this for metric graphs
  $start = intval(abs(str_replace("s", "", $rrdtool_graph['start'])));
  $offset = 2 * $start;

  $def = str_replace("DEF:'sum'", "DEF:'sum2'", trim($matches[0][0])) .
    ":start=end-" . $offset;

  $command .= " " . $def . " SHIFT:sum2:" . $start;
  $command .= " 'LINE2:sum2" . $conf['timeshift_line_color'] .
    ":Previous " . $range . ":dashes'";
}

////////////////////////////////////////////////////////////////////////////////
// Add warning and critical lines
////////////////////////////////////////////////////////////////////////////////
$warning = isset($_GET["warn"]) && is_numeric($_GET["warn"]) ?
  $_GET["warn"] : NULL;
if ($warning) {
  $command .= " 'HRULE:" . $warning . "#FFF600:Warning:dashes'";
}

$critical = isset($_GET["crit"]) && is_numeric($_GET["crit"]) ?
  $_GET["crit"] : NULL;
if ($critical) {
  $command .= " 'HRULE:" . $critical . "#FF0000:Critical:dashes'";
}

////////////////////////////////////////////////////////////////////////////////
// Turn on SVG rendering
////////////////////////////////////////////////////////////////////////////////
if ( $user['image_format'] == "svg" )
  $command .= " -a SVG";


if ($debug) {
  error_log("Final rrdtool command:  $command");
}

# Did we generate a command? Run it.
if ($command || $graphite_url)
  execute_graph_command($conf['graph_engine'],
			($conf['graph_engine'] == "graphite") ?
			$graphite_url : $command);

?>
