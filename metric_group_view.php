<?php
include_once("./global.php");
include_once "./eval_conf.php";
include_once "./functions.php";
include_once "./get_context.php";
include_once "./ganglia.php";
include_once "./get_ganglia.php";
include_once "./dwoo/dwooAutoload.php";

if (isset($_GET['metric_group']) && ($_GET['metric_group'] != ""))
  $metric_group = $_GET['metric_group'];
else
  exit(0);

try {
  $dwoo = new Dwoo($conf['dwoo_compiled_dir'], $conf['dwoo_cache_dir']);
} catch (Exception $e) {
  print "<H4>There was an error initializing the Dwoo PHP Templating Engine: " .
    $e->getMessage() . 
    "<br><br>The compile directory should be owned and writable by the apache user.</H4>";
  exit;
}

$tpl = new Dwoo_Template_File(template("metric_group_view.tpl"));
$data = new Dwoo_Data();

$data->assign("may_edit_views", checkAccess( GangliaAcl::ALL_VIEWS,
					     GangliaAcl::EDIT,
					     $conf) );
$data->assign("graph_engine", $conf['graph_engine']);

function dump_var($var, $varId) {
  ob_start();
  var_dump($var);
  error_log($varId . " = ". ob_get_clean());
}

function getMetricGroup($metrics,
			$metric_group,
			$always_timestamp,
			$always_constant,
			$hostname,
			$baseGraphArgs,
			$data) {
  global $conf;

  list($metricMap, $metricGroupMap) = 
    buildMetricMaps($metrics,
		    $always_timestamp,
		    $always_constant,
		    $baseGraphArgs);

  //dump_var($metricMap, "metricMap");
  //dump_var($metricGroupMap, "metricGroupMap");
  
  # There is a special case where if you don't set group when you do gmetric
  # invocation it gets set to no_group which ends up being just [""] array
  if ( $metric_group == "no_group" ) 
    $metric_group = "";

  if (!isset($metricGroupMap[$metric_group])) {
    error_log("Missing metric group: " . $metric_group);
    exit(0);
  }

  $metric_array = $metricGroupMap[$metric_group];
  $num_metrics = count($metric_array);

  if (function_exists("sort_metric_group_metrics")) {
    $metric_array = sort_metric_group_metrics($group, $metric_array);
  } else {
    // Sort by metric_name
    asort($metric_array, SORT_NATURAL);
  }

  $i = 0;
  foreach ($metric_array as $name) {
    $group["metrics"][$name]["graphargs"] = $metricMap[$name]['graph'];
    $group["metrics"][$name]["alt"] = "$hostname $name";
    $group["metrics"][$name]["host_name"] = $hostname;
    $group["metrics"][$name]["metric_name"] = $name;
    $group["metrics"][$name]["title"] = $metricMap[$name]['title'];
    $group["metrics"][$name]["desc"] = $metricMap[$name]['description'];
    $group["metrics"][$name]["new_row"] = "";
    if (!(++$i % $conf['metriccols']) && ($i != $num_metrics))
      $group["metrics"][$name]["new_row"] = "</TR><TR>";
  }

  $data->assign("g_metrics", $group);
}

$size = isset($clustergraphsize) ? $clustergraphsize : 'default';
//set to 'default' to preserve old behavior
$size = ($size == 'medium') ? 'default' : $size; 

// set host zoom class based on the size of the graph shown
$additional_host_img_css_classes = "";
if ( isset($conf['zoom_support']) && $conf['zoom_support'] === true )
  $additional_host_img_css_classes = "host_${size}_zoomable";

$data->assign("additional_host_img_css_classes",
	      $additional_host_img_css_classes);

$cluster_url = rawurlencode($clustername);

$baseGraphArgs = "c=$cluster_url&amp;h=$hostname"
  . "&amp;r=$range&amp;z=$size&amp;jr=$jobrange"
  . "&amp;js=$jobstart&amp;st=$cluster[LOCALTIME]";
if ($cs)
  $baseGraphArgs .= "&amp;cs=" . rawurlencode($cs);
if ($ce)
  $baseGraphArgs .= "&amp;ce=" . rawurlencode($ce);
if (isset($_GET['event']))
  $baseGraphArgs .= "&amp;event=" . rawurlencode($_GET['event']);
if (isset($_GET['ts']))
  $baseGraphArgs .= "&amp;ts=" . rawurlencode($_GET['ts']);

getMetricGroup($metrics,
	       $metric_group,
	       $always_timestamp,
	       $always_constant,
	       $hostname,
	       $baseGraphArgs,
	       $data);

if ( $conf['graph_engine'] == "flot" ) {
  $data->assign("graph_height", $conf['graph_sizes'][$size]["height"] + 50);
  $data->assign("graph_width", $conf['graph_sizes'][$size]["width"]);
}

$data->assign('GRAPH_BASE_ID', $GRAPH_BASE_ID);
$data->assign('SHOW_EVENTS_BASE_ID', $SHOW_EVENTS_BASE_ID);
$data->assign('TIME_SHIFT_BASE_ID', $TIME_SHIFT_BASE_ID);

$dwoo->output($tpl, $data);
?>
