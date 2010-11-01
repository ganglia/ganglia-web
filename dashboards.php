<?php

$ganglia_dir = dirname(__FILE__);

// Load the metric caching code
require_once('./cache.php');

if ( !isset($_GET['dashboard_name']) ) {
  $dashboard_name = "default";
} else {
  $dashboard_name = $_GET['dashboard_name'];
}

# Read in the Dashboard config file
$dashboards_config = $ganglia_dir . "/conf/dashboards.json";

if ( ! is_file ($dashboards_config) ) {
  die("Can't read the dashboards config file");
}

$dashboards = json_decode(file_get_contents($dashboards_config), TRUE);

foreach ( $dashboards as $dash_id => $dashboard ) {
   if ( $dashboard['dashboard_name'] == $dashboard_name ) {
      foreach ( $dashboard['items'] as $item_id => $item ) {

	// Is it a metric or a graph(report)
	if ( isset($item['metric']) ) {
	  $graph_args_array[] = "m=" . $item['metric'];
	} else {
	  $graph_args_array[] = "g=" . $item['graph'];
	}

	$hostname = $item['hostname'];
	$cluster = $index_array['cluster'][$hostname];
	$graph_args_array[] = "h=$hostname";
	$graph_args_array[] = "c=$cluster";

	$graph_args = join("&", $graph_args_array);

	print "
	  <A HREF=\"./graph_all_periods.php?$graph_args&z=large\">
	  <IMG BORDER=0 SRC=\"./graph.php?$graph_args&z=medium\"></A>";

	unset($graph_args_array);

      } // end of foreach
   }  // end of if ( $dashboard['dashboard_name'] == $dashboard_name
}

?>