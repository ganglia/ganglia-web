<?php
include_once "./eval_conf.php";
include_once "./global.php";
include_once "./functions.php";
include_once "./dwoo/dwooAutoload.php";

$tpl = new Dwoo_Template_File(template("graph_all_periods.tpl"));
$data = new Dwoo_Data();

$data->assign("conf", $conf);
$data->assign("embed",
              isset($_REQUEST['embed']) ? $_REQUEST['embed'] : NULL);
$data->assign("mobile",
              isset($_REQUEST['mobile']) ? $_REQUEST['mobile'] : NULL);
$data->assign("g", isset($_GET['g']) ? $_GET['g'] : NULL);
$data->assign("m", isset($_GET['m']) ? $_GET['m'] : NULL);
$data->assign("html_g",
              isset($_GET['g']) ? htmlspecialchars($_GET['g']) : NULL);
$data->assign("html_m",
              isset($_GET['m']) ? htmlspecialchars($_GET['m']) : NULL);
$data->assign("standalone",
              !isset($_REQUEST['embed']) && !isset($_REQUEST['mobile']));

// build a query string but drop r and z since those designate time
// window and size. Also if the get arguments are an array rebuild them.
// For example with hreg (host regex)
$ignore_keys_list = array("r", "z", "st", "cs", "ce", "hc");

foreach ($_GET as $key => $value) {
  if (!in_array($key, $ignore_keys_list) && !is_array($value))
    $query_string_array[] = rawurlencode($key) . "=" . urlencode($value);

  // $_GET argument is an array. Rebuild it to pass it on
  if (is_array($value)) {
    foreach ($value as $value2)
      $query_string_array[] = rawurlencode($key) . "[]=" . urlencode($value2);
  }
}

// If we are in the mobile mode set the proper graph sizes
$data->assign("largesize", isset($_GET['mobile']) ? "mobile" : "large");
$data->assign("xlargesize", isset($_GET['mobile']) ? "mobile" : "xlarge");

// Join all the query_string arguments
$query_string = join("&amp;", $query_string_array);
$data->assign("query_string", $query_string);

// Descriptive host/aggregate graph
if (isset($_GET['h']) && ($_GET['h'] != ''))
  $description = htmlspecialchars($_GET['h']);
else if (isset($_GET['c']) && ($_GET['c'] != ''))
  $description = htmlspecialchars($_GET['c']);
else if (isset($_GET['hreg']) && is_array($_GET['hreg']))
  $description = htmlspecialchars(join(",", $_GET['hreg']));
else
  $description = "Unknown";

$data->assign("description", $description);

if (isset($_GET['g']))
  $metric_description = htmlspecialchars($_GET['g']);
else if ( isset($_GET['m'] ))
  $metric_description = htmlspecialchars($_GET['m']);
else if (isset($_GET['mreg']) && is_array($_GET['mreg']) )
  $metric_description = htmlspecialchars(join(",", $_GET['mreg']));
else
  $metric_description = "Unknown";

$data->assign("metric_description", $metric_description);

# Determine if it's aggregate graph
$is_aggregate = preg_match("/aggregate=1/", $query_string) ? TRUE : FALSE;

$graph_actions = array();
$graph_actions['timeshift'] = !$is_aggregate && !isset($_GET['g']);
$graph_actions['metric_actions'] = $is_aggregate;
$graph_actions['decompose'] = $is_aggregate;
$data->assign('graph_actions', $graph_actions);

$data->assign('GRAPH_BASE_ID', $GRAPH_BASE_ID);
$data->assign('SHOW_EVENTS_BASE_ID', $SHOW_EVENTS_BASE_ID);
$data->assign('TIME_SHIFT_BASE_ID', $TIME_SHIFT_BASE_ID);

if ($conf['graph_engine'] == 'flot') {
  $data->assign('flot_graph_height',
                $conf['graph_sizes'][$largesize]["height"]);
  $data->assign('flot_graph_width',
                $conf['graph_sizes'][$largesize]["width"]);
}

if (!isset($dwoo)) {
  try {
    $dwoo = new Dwoo($conf['dwoo_compiled_dir'], $conf['dwoo_cache_dir']);
  } catch (Exception $e) {
    print
      "<H4>There was an error initializing the Dwoo PHP Templating Engine: ".
      $e->getMessage() . "<br><br>The compile directory should be owned " .
      "and writable by the apache user.</H4>";
    exit;
  }
}

$dwoo->output($tpl, $data);
?>
