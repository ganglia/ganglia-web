<?php

include_once("./eval_conf.php");
include_once("./functions.php");

if (isset($conf['ad-hoc-views']) && $conf['ad-hoc-views'] === true && isset($_GET['ad-hoc-view'])) {
  $is_ad_hoc = true;
  $ad_hoc_view_json = json_decode(heuristic_urldecode($_GET['ad-hoc-view']), true);
}


//////////////////////////////////////////////////////////////////////////////////////////
// Print out 
//////////////////////////////////////////////////////////////////////////////////////////
if ( ! isset($_REQUEST['view_name']) and ! $is_ad_hoc ) {

  $available_views = get_available_views();

  print "<form action='tasseo.php'><select onchange='this.form.submit();' name=view_name><option value=none>Please choose...</option>";
  foreach ( $available_views as $id => $view ) {
    print "<option value='" . $view['view_name'] . "'>" . $view['view_name'] . "</option>";
  }
  print "</form>";

} else {

  header("Cache-Control: no-cache, must-revalidate");
  header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

  // We need metrics cache in order to derive cluster name particular host
  // belongs to
  retrieve_metrics_cache();

  $available_views = get_available_views();

  $view_found = 0;
  
  $user['view_name'] = $_REQUEST['view_name'];
  
  // I am not quite sure at this point whether I should cache view info so
  // for now I will have to do this
  foreach ( $available_views as $id => $view ) {
    # Find view settings
    if ( $user['view_name'] == $view['view_name'] ) {
      $view_found = 1;
      break;
    }
  }
  unset($available_views);

  if ( ($view_found == 0 || count($view['items']) == 0) && !$is_ad_hoc ) {
      die ("<font color=red size=4>There are no graphs in view you supplied or view does not exist.</font>");
  }

  // Let's get all View graph elements
  if ($is_ad_hoc) {
    $view_elements = get_view_graph_elements($ad_hoc_view_json);
  }
  else {
    $view_elements = get_view_graph_elements($view);
  }
  ?>
<html>
<head>
<title>Live Dashboard for <?php print $user['view_name']; ?></title>
<meta http-equiv="Content-type" content="text/html; charset=utf-8">
<link rel="stylesheet" type="text/css" href="css/tasseo.css" />
<script type="text/javascript" src="<?php print $conf['jquery_js_path']; ?>"></script>
<script type="text/javascript" src="<?php print $conf['d3_js_path']; ?>"></script>
<script type="text/javascript" src="<?php print $conf['rickshaw_js_path']; ?>"></script>
</head>
<body>
   <div id='title'>
      <span><?php print $user['view_name']; ?></span>
      <div id='toolbar'>
        <ul class='timepanel'>
          <li class='timepanel live selected'>
            <a class='play' href='#'>live</a>
          </li>
          <li class='timepanel'>
            <a class='range' href='#' title='60'>1h</a>
          </li>
          <li class='timepanel'>
            <a class='range' href='#' title='180'>3h</a>
          </li>
          <li class='timepanel'>
            <a class='range' href='#' title='1440'>1d</a>
          </li>
          <li class='timepanel'>
            <a class='range' href='#' title='10080'>1w</a>
          </li>
        </ul>
        <ul class='toggle'>
          <li class='toggle-nonum'>
            <a href='#'>
              <img src='img/toggle-number.png' />
            </a>
          </li>
          <li class='toggle-night'>
            <a href='#'>
              <img src='img/toggle-night.png' />
            </a>
          </li>
        </ul>
      </div>
    </div>
<div id="main">
</div>
<script>
   var ganglia_url = "<?php
   $proto = "//";
   $path_parts = pathinfo($_SERVER['SCRIPT_NAME']);
   print $proto . $_SERVER['HTTP_HOST'] .  $path_parts['dirname']; ?>";
</script>
<script>
var metrics =
<?php
foreach ( $view_elements as $index => $element ) {
   # Avoid optional reports and Aggregate graphs until we implement them
   if ( ! preg_match("/_report&/", $element['graph_args']) ) {
      $element['graph_args'] = rawurldecode($element['graph_args']);
      $tasseo_e['graph_args'] = $element['graph_args'];
#      $tasseo_e['hostname'] = $element['hostname'];
#      $tasseo_e['clustername'] = $element['cluster'];
#      $tasseo_e['metricname'] = $element['name'];
      if ( isset($element['aggregate_graph'])) {
        $tasseo_e['name'] = $element['name'];
      } else {
        $tasseo_e['name'] = ($conf['strip_domainname'] ? strip_domainname($element['hostname']) : $element['hostname']) . " " . $element['name'];
      }
      if ( isset($element['alias']))
         $tasseo_e['alias'] = $element['alias'];
      if ( isset($element['warning']))
         $tasseo_e['warning'] = $element['warning'];
      if ( isset($element['critical']))
         $tasseo_e['critical'] = $element['critical'];
      
      $tasseo_element[] = $tasseo_e;
      unset($tasseo_e);
   }
}
print json_encode($tasseo_element)
?>;
</script>
<script type="text/javascript" src="js/tasseo.js"></script>

  <?php
} // end of if (!isset($_GET['view_name']
?>
</body>
</html>
