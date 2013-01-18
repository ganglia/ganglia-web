<?php

include_once("./eval_conf.php");
include_once("./functions.php");

if( ! checkAccess(GangliaAcl::ALL_VIEWS, GangliaAcl::VIEW, $conf) ) {
  die("You do not have access to view views.");
}

// Load the metric caching code we use if we need to display graphs
retrieve_metrics_cache();

$base = isset($_GET['base']) ? rawurlencode($_GET['base']) . "/" : "";
?>

<html>
<head>
<script type="text/javascript" src="<?php print $base; ?>js/jquery-1.8.2.min.js"></script>
<script type="text/javascript" src="<?php print $base; ?>js/jquery-ui-1.9.1.custom.min.js"></script>
<link type="text/css" href="css/smoothness/jquery-ui-1.9.1.custom.min.css" rel="stylesheet"/>
<link rel="stylesheet" href="./styles.css" type="text/css">
</head>
<body>
  <div>

<?php
  $available_views = get_available_views();
  $view_name = $_GET['vn'];

  // Let's find the view definition
  foreach ($available_views as $view_id => $view) {
    if ($view['view_name'] == $view_name) {
      $view_elements = get_view_graph_elements($view);
      $range_args = "";
      if (isset($_GET['r']) && $_GET['r'] != "") 
	$range_args .= "&amp;r=" . rawurlencode($_GET['r']);
      if (isset($_GET['cs']) && isset($_GET['ce'])) 
	$range_args .= "&amp;cs=" . rawurlencode($_GET['cs']) . "&amp;ce=" . rawurlencode($_GET['ce']);

      if (count($view_elements) != 0) {
	foreach ($view_elements as $id => $element) {
	  $legend = isset($element['hostname']) ? $element['hostname'] : "Aggregate graph";
          $base = isset($_GET['base']) ? rawurlencode($_GET['base']) : '.';
	  print "<a href=\"" . $base . "/graph_all_periods.php?" . htmlentities($element['graph_args']) ."&amp;z=large\"><img title=\"" . $legend . " - " . $element['name'] . "\" border=0 src=\"" . $base . "/graph.php?" . htmlentities($element['graph_args']) . "&amp;z=small" . $range_args .  "\" style=\"padding:2px;\"></a>";
	}
      } else {
	print "No graphs defined for this view. Please add some";
      }
    }
  } 
?>
  </div>
</body>
</html>

