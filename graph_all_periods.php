<html>
<head>
<title>Ganglia: Graph all periods</title>
<link rel="stylesheet" href="./styles.css" type="text/css" />
<style type="text/css">
.img_view {
  float: left;
  margin: 0 0 10px 10px;
}
</style>
<?php
if ( ! isset($_REQUEST['embed'] ) && ! isset($_REQUEST['mobile']) ) {
?>
<script TYPE="text/javascript" SRC="js/jquery-1.8.2.min.js"></script>
<script type="text/javascript" src="js/jquery-ui-1.9.1.custom.min.js"></script>
<script type="text/javascript" src="js/jquery.livesearch.min.js"></script>
<script type="text/javascript" src="js/ganglia.js"></script>
<script type="text/javascript" src="js/jquery.gangZoom.js"></script>
<script type="text/javascript" src="js/jquery.cookie.js"></script>
<script type="text/javascript" src="js/jquery-ui-timepicker-addon.js"></script>
<script type="text/javascript" src="js/jquery.ba-bbq.min.js"></script>
<link type="text/css" href="css/smoothness/jquery-ui-1.9.1.custom.min.css" rel="stylesheet" />
<link rel="stylesheet" href="css/jquery.multiselect.css" type="text/css" />
<?php
}
?>

<script type="text/javascript">
  function openDecompose($url) {
    $.cookie("ganglia-selected-tab-" + window.name, 0);
    location.href="./index.php" + $url + "&amp;tab=m";
  }

  $(function() {
    initShowEvent();
    initTimeShift();
<?php if ( isset($_GET['embed'] ) ) { ?>
    initMetricActionsDialog();
<?php } ?>
<?php if ( ! isset($_REQUEST['mobile'])) { ?>
    $( "#popup-dialog" ).dialog({ autoOpen: false, minWidth: 850 });
<?php } ?>
  });
</script>

<?php
include_once "./eval_conf.php";
include_once "./global.php";

// build a query string but drop r and z since those designate time window and size. Also if the 
// get arguments are an array rebuild them. For example with hreg (host regex)
$ignore_keys_list = array("r", "z", "st", "cs", "ce", "hc");

foreach ($_GET as $key => $value) {
  if ( ! in_array($key, $ignore_keys_list) && ! is_array($value))
    $query_string_array[] = "$key=" . urlencode($value);

  // $_GET argument is an array. Rebuild it to pass it on
  if ( is_array($value) ) {
    foreach ( $value as $index => $value2 )
      $query_string_array[] = $key . "[]=" . urlencode($value2);

  }
}

// If we are in the mobile mode set the proper graph sizes
if ( isset($_GET['mobile'])) {
  $largesize = "mobile";
  $xlargesize = "mobile";
} else {
  $largesize = "large";
  $xlargesize = "xlarge";  
}

// Join all the query_string arguments
$query_string = "&amp;" . join("&amp;", $query_string_array);

// Descriptive host/aggregate graph
if (isset($_GET['h']) && ($_GET['h'] != ''))
  $description = $_GET['h'];
else if (isset($_GET['c']) && ($_GET['c'] != ''))
  $description = $_GET['c'];
else if (is_array($_GET['hreg']))
  $description = join(",", $_GET['hreg']);
else
  $description = "Unknown";

if (isset($_GET['g'])) 
  $metric_description = $_GET['g'];
else if ( isset($_GET['m'] ))
  $metric_description = $_GET['m'];
else if (is_array($_GET['mreg']) )
  $metric_description = join(",", $_GET['mreg']);
else
  $metric_description = "Unknown";

# Determine if it's aggregate graph
if ( preg_match("/aggregate=1/", $query_string) )
  $is_aggregate = true;
else
  $is_aggregate = false;


if ( $conf['graph_engine'] == "flot" ) {
?>
<style type="text/css">
.flotgraph {
  height: <?php print $conf['graph_sizes'][$largesize]["height"] ?>px;
  width:  <?php print $conf['graph_sizes'][$largesize]["width"] ?>px;
}
</style>
<?php
// Add JQuery and flot loading only if this is not embedded in the Aggregate Graphs Tab
if ( ! isset($_GET['embed'] ) ) {
?>
<!--[if lte IE 8]><script language="javascript" type="text/javascript" src="js/excanvas.min.js"></script><![endif]-->
<script language="javascript" type="text/javascript" src="js/jquery.flot.min.js"></script>
<?php
} // end of if ( ! isset($_GET['embed'] )
?>
<script type="text/javascript">
  var default_time = 'hour';
  var metric = "<?php if (isset($_GET['g'])) echo $_GET['g']; else echo $_GET['m']; ?>";
  var base_url = "<?php print 'graph.php?flot=1&amp;' . $_GET['m'] . $query_string ?>" + "&amp;r=" + default_time;
</script>
<script type="text/javascript" src="js/create-flot-graphs.js"></script>
<?php
} // end of if ( $conf['graph_engine'] == "flot" ) {
?>
</head>

<body onSubmit="return false;">
<?php
if ( ! isset($_REQUEST['mobile']) ) {
?>
<div id="popup-dialog" style="display: none" title="Inspect Graph">
  <div id="popup-dialog-content">
  </div>
</div>
<?php
}
?>
<div id="metric-actions-dialog" style="display: none" title="Metric Actions">
<div id="metric-actions-dialog-content">
	Available Metric actions.
</div>
</div>
<form>
<?php
if ( isset($_REQUEST['mobile'])) {
?>
    <div data-role="page" class="ganglia-mobile" id="view-home">
    <div data-role="header">
      <a href="#" class="ui-btn-left" data-icon="arrow-l" onclick="history.back(); return false">Back</a>
      <h3><?php if (isset($_GET['g'])) echo $_GET['g']; else echo $_GET['m']; ?></h3>
      <a href="#mobile-home">Home</a>
    </div>
    <div data-role="content">
<?php
}

// Skip printing if this is an embedded graph e.g. from Aggregate graph screen
if ( ! isset($_REQUEST['embed'] )  ) {
?>
  <div><b>Host/Cluster/Host Regex: </b><?php print $description ?>&nbsp;<b>Metric/Graph/Metric Regex: </b><?php print $metric_description;?>&nbsp;&nbsp;
<?php
}

if ( ! isset($_REQUEST['mobile'] )  ) {
   print '<input title="Hide/Show Events" type="checkbox" id="show_all_events" onclick="showAllEvents(this.checked)"/><label class="show_event_text" for="show_all_events">Hide/Show Events All Graphs</label>';
   # Make sure it's not aggregate or composite graph
  if ( ! $is_aggregate && ! isset($_GET['g']) )
     print '<input title="Timeshift Overlay" type="checkbox" id="timeshift_overlay" onclick="showTimeshiftOverlay(this.checked)"/><label class="show_timeshift_text" for="timeshift_overlay">Timeshift Overlay</label><br />';
  print "</div>";
} // end of if ( ! isset($_REQUEST['mobile'] )  ) {

if (isset($_GET['embed'])) {
  print "<div style='height:10px;'/>";
}


foreach ( $conf['time_ranges'] as $key => $value ) {

  # Skip job 
  if ( $value == "job" )
    continue;

  print '<div class="img_view">';
  
  if ( ! isset($_REQUEST['mobile']) ) {

  print '<span style="padding-left: 4em; padding-right: 4em; text-weight: bold;">' . $key . '</span>';

  // If this is for mobile hide some of the options
  
    // Check if it's an aggregate graph
    if ( $is_aggregate  ) {
      print '<button class="cupid-green" title="Metric Actions - Add to View, etc" onclick="metricActionsAggregateGraph(\'' . $query_string . '\'); return false;">+</button>';
    }
  
    print ' <button title="Export to CSV" class="cupid-green" onclick="window.location=\'./graph.php?r=' . $key . $query_string . '&amp;csv=1\';return false">CSV</button> ';
  
    print ' <button title="Export to JSON" class="cupid-green" onclick="window.location=\'./graph.php?r=' . $key . $query_string . '&amp;json=1\';return false;">JSON</button> ';
  
     // Check if it's an aggregate graph
    if ( $is_aggregate  ) {
	print ' <button title="Decompose aggregate graph" class="shiny-blue" onClick="openDecompose(\'?r=' . $key . $query_string  . '&amp;dg=1\');return false;">Decompose</button>';
    }
   
    print ' <button title="Inspect Graph" onClick="inspectGraph(\'r=' . $key . $query_string  . '\'); return false;" class="shiny-blue">Inspect</button>';

    $graphId = $GRAPH_BASE_ID . $key;

    print ' <input title="Hide/Show Events" type="checkbox" id="' . $SHOW_EVENTS_BASE_ID . $key . '" onclick="showEvents(\'' . $graphId . '\', this.checked)"/><label class="show_event_text" for="' . $SHOW_EVENTS_BASE_ID . $key . '">Hide/Show Events</label>';
    if ( ! $is_aggregate && ! isset($_GET['g']) )
      print ' <input title="Timeshift Overlay" type="checkbox" id="' . $TIME_SHIFT_BASE_ID . $key . '" onclick="showTimeShift(\'' . $graphId . '\', this.checked)"/><label class="show_timeshift_text" for="' . $TIME_SHIFT_BASE_ID . $key . '">Timeshift</label>';

  } 

  print  '<br />';

  // If we are using flot we need to use a div instead of an image reference
  if ( $conf['graph_engine'] == "flot" ) {

    print '<div id="placeholder_' . $key . '" class="flotgraph img_view"></div>';
    print '<div id="placeholder_' . $key . '_legend" class="flotlegend"></div>';

  } else {

    print '<a href="./graph.php?r=' . $key . '&amp;z=' . $xlargesize . $query_string . '"><img class="noborder" id="' . $graphId . '" style="margin-top:5px;" title="Last ' . $key . '" src="graph.php?r=' . $key . '&amp;z=' . $largesize . $query_string . '"></a>';

  }

  print "</div>";

}
// The div below needs to be added to clear float left since in aggregate view things
// will start looking goofy
?>
<div style="clear: left"></div>
</form>
</body>
</html>
