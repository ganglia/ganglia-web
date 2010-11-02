<?php

$ganglia_dir = dirname(__FILE__);

// Load the metric caching code
require_once('./cache.php');

if ( !isset($_GET['view_name']) ) {
  $view_name = "default";
} else {
  $view_name = $_GET['view_name'];
}

# Read in the Dashboard config file
$views_config = $ganglia_dir . "/conf/views.json";

if ( ! is_file ($views_config) ) {
  die("Can't read the views config file");
}

$views = json_decode(file_get_contents($views_config), TRUE);

foreach ( $views as $dash_id => $view ) {
   if ( $view['view_name'] == $view_name ) {
      foreach ( $view['items'] as $item_id => $item ) {

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
   }  // end of if ( $view['view_name'] == $view_name
}

?>
