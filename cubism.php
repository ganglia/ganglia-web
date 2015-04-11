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
<script src="<?php print $conf['cubism_d3_path']; ?>"></script>
<script src="<?php print $conf['cubism_js_path']; ?>"></script>
<?php

$min = (isset($_REQUEST['min']) and is_numeric($_REQUEST['min'])) ? $_REQUEST['min'] : 0;
$max = (isset($_REQUEST['max']) and is_numeric($_REQUEST['max'])) ? $_REQUEST['max'] : 10;

$height = (isset($_REQUEST['height']) and is_numeric($_REQUEST['height'])) ? $_REQUEST['height'] : 30;

?>
</head>
<body>
   <div class="navbar navbar-fixed-top">
      <div class="navbar-inner">
      <form>
      Host Regex: <input size=30 value="<?php print htmlentities($_REQUEST['hreg']); ?>" name="hreg">
      Metric: <input size=30 value="<?php print htmlentities($_REQUEST['mreg']); ?>" name="mreg">
      Min: <input size=12 value="<?php print htmlentities($_REQUEST['min']); ?>" name="min">
      Max: <input size=12 value="<?php print htmlentities($_REQUEST['max']); ?>" name="max">
      <input type=submit>
      </form>
      <?php
      
      ?>

      </div>
    </div>

<?php
      if ( ! (isset($_REQUEST['hreg']) ) ) {
	exit(1);
      }

      
retrieve_metrics_cache();

?>
<script>
var context = cubism.context()
    .serverDelay(15 * 1000) // allow 15 seconds of collection lag
    .step(15000) // 20 seconds per value
    .size(1024); // fetch 1440 values (720p)
var ganglia = context.gangliaWeb( { 
  "host": '<?php print $_SERVER["REQUEST_SCHEME"] ?>://<?php print $_SERVER["HTTP_HOST"] ?>', 
  "uriPathPrefix": '<?php print $_SERVER["CONTEXT_PREFIX"] ?>/'} );

<?php

# Most of the cubism graphs are like aggregate graphs so let's avoid redoing
# the logic
$mreg = $_REQUEST['mreg'];
$hreg = $_REQUEST['hreg'];

$graph_config = build_aggregate_graph_config ("line", 
                                       1, 
                                       $hreg,
                                       $mreg,
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
      .height(30)
      );
context.on("focus", function(i) {
  d3.selectAll(".value").style("right", i == null ? null : context.size() - 1 - i + "px");
});
</script>
