<!DOCTYPE html>
<meta charset="utf-8">
<title>Ganglia Cubism Dashboard</title>
<style>
@import url(css/cubism.css);
</style>
<?php

include_once("./eval_conf.php");
require_once('./functions.php');

?>
<script src="<?php print $conf['d3_js_path']; ?>"></script>
<script src="<?php print $conf['cubism_js_path']; ?>"></script>
<?php

$min = (isset($_REQUEST['min']) and is_numeric($_REQUEST['min'])) ? $_REQUEST['min'] : 0;
$max = (isset($_REQUEST['max']) and is_numeric($_REQUEST['max'])) ? $_REQUEST['max'] : 10;

$mreg = $_REQUEST['mreg'];
$hreg = $_REQUEST['hreg'];

$height = (isset($_REQUEST['height']) and is_numeric($_REQUEST['height'])) ? $_REQUEST['height'] : 30;

$step = (isset($_REQUEST['step']) and is_numeric($_REQUEST['step'])) ? $_REQUEST['step'] : 15;
$step_in_ms = $step * 1000;

?>
</head>
<body>
   <div>
      <form id="cubism-form">
      Host Regex: <input size=25 value="<?php print htmlentities($hreg); ?>" name="hreg">
      Metric Regex: <input size=25 value="<?php print htmlentities($mreg); ?>" name="mreg">
      Min: <input size=10 value="<?php print htmlentities($min); ?>" name="min">
      Max: <input size=10 value="<?php print htmlentities($max); ?>" name="max">
      Height: <input size=2 value="<?php print htmlentities($height); ?>" name="height">
      Step: <input size=2 value="<?php print htmlentities($step); ?>" name="step">
      <input type="submit" value="Submit">
      </form>
   </div>
   <div class="navbar navbar-fixed-top">
      <div class="navbar-inner">
      </div>
    </div>

<script>
var context = cubism.context()
    .serverDelay(15 * 1000) // allow 15 seconds of collection lag
    .step(<?php print $step_in_ms;?>) // what step to use
    .size(1024); // How many values to fetch
var ganglia = context.gangliaWeb( { 
  "host": '<?php
     if ( isset($conf['ganglia_url_prefix']) )
	print $conf['ganglia_url_prefix'];
     else
	print (isset($_SERVER["HTTPS"]) ? "https" : "http") . "://" . $_SERVER["SERVER_NAME"];
	   ?>',
  "uriPathPrefix": '<?php
     if ( isset($conf['ganglia_url_suffix']) )
	print $conf['ganglia_url_suffix'];
     else
	print dirname($_SERVER["SCRIPT_NAME"]);
	   ?>/'} );

<?php

# Most of the cubism graphs are like aggregate graphs so let's avoid redoing
# the logic

$graph_config = build_aggregate_graph_config ("line", 
                                       1, 
                                       array($hreg),
                                       array($mreg),
                                       "hide",
                                       false);

?>
var load_metrics = [
<?php

$items = array();

foreach ( $graph_config['series'] as $index => $item ) {

  $cluster = $item['clustername'];
  $host = $item['hostname'];
  $metric = $item['metric'];
  $label = $host . "_" . $metric;
  $items[] = 'ganglia.metric( { 
    "clusterName": "' . $cluster . '", 
    "hostName": "' . $host .'", 
    "metricName": "' . $metric . '",
    "isReport": false} ).alias("' . $label . '")';

}

print join(",\n", $items);

?>
];

var horizon = context.horizon().colors(["#08519c", "#*82bd", "#6baed6", "#fee6ce", "#fdae6b", "#e6550d" ]);
d3.select("body").selectAll(".axis")
    .data(["top", "bottom"])
  .enter().append("div").attr("class", "fluid-row")
    .attr("class", function(d) { return d + " axis"; })
    .each(function(d) { d3.select(this).call(context.axis().ticks(12).orient(d)); });
d3.select("body").append("div")
    .attr("class", "rule")
    .call(context.rule());
d3.select("body").selectAll(".horizon")
    .data(load_metrics)
    .enter().insert("div", ".bottom")
    .attr("class", "horizon")
    .call(
      horizon.extent([<?php print $min . "," . $max;?>])
      .height(<?php print $height; ?>)
      );
context.on("focus", function(i) {
  d3.selectAll(".value").style("right", i == null ? null : context.size() - 1 - i + "px");
});
</script>
