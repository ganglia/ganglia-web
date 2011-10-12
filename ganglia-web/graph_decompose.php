<!DOCTYPE html> 
<html>
<head>
<title>Ganglia: Decompose</title>
<link rel="stylesheet" href="./styles.css" type="text/css" />
<style>
.img_view {
  float: left;
  margin: 0 0 10px 10px;
}
</style>
<?php
if ( ! isset($_GET['embed'] ) ) {
?>
<script TYPE="text/javascript" SRC="js/jquery-1.6.2.min.js"></script>
<script type="text/javascript" src="js/jquery-ui-1.8.14.custom.min.js"></script>
<script type="text/javascript" src="js/jquery.liveSearch.js"></script>
<script type="text/javascript" src="js/ganglia.js"></script>
<script type="text/javascript" src="js/jquery.gangZoom.js"></script>
<script type="text/javascript" src="js/jquery.cookie.js"></script>
<link type="text/css" href="css/smoothness/jquery-ui-1.8.14.custom.css" rel="stylesheet" />
<div id="metric-actions-dialog" title="Metric Actions">
<div id="metric-actions-dialog-content">
	Available Metric actions.
</div>
</div>
<?php
} // end of if ( ! isset($_GET['embed'] ) ) {

require_once "./eval_conf.php";
require_once "./functions.php";

// build a query string but drop r and z since those designate time window and size. Also if the 
// get arguments are an array rebuild them. For example with hreg (host regex)
$ignore_keys_list = array("z", "st", "cs", "ce", "hc", "aggregate", "mreg", "hreg", "title", "decompose");

foreach ($_GET as $key => $value) {
  if ( ! in_array($key, $ignore_keys_list) && ! is_array($value))
    $query_string_array[] = "$key=$value";

  if ( $key = "r")
    $period = $value;

}

$graph_type = "line";
$line_width = "2";
$graph_config = build_aggregate_graph_config ($graph_type, $line_width, $_GET['hreg'], $_GET['mreg']);

$key = "hour";
// If we are in the mobile mode set the proper graph sizes
if ( isset($_GET['mobile'])) {
  $largesize = "mobile";
  $xlargesize = "mobile";
} else {
  $largesize = "large";
  $xlargesize = "xlarge";  
}

foreach ( $graph_config['series'] as $index => $item ) {
    $args = join("&", $query_string_array) . "&h=" . $item['hostname'] . "&c=" . $item['clustername'] . "&m=" . $item['metric'];
    print '<a href="./graph.php?z=' . $xlargesize . "&". $args . '"><img class="noborder" title="Last ' . $period . '" src="graph.php?z=' . $largesize . "&title=Last $period&". $args . '"></a><p>';

}

?>
</body>
</html>
