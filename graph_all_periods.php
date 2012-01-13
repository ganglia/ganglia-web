<html>
<head>
<title>Ganglia: Graph all periods</title>
<link rel="stylesheet" href="./styles.css" type="text/css" />
<style>
.img_view {
  float: left;
  margin: 0 0 10px 10px;
}
</style>
<div id="inspect-graph-dialog" style="display: none" title="Inspect Graph">
  <div id="inspect-graph-dialog-content">
  </div>
</div>
<?php
if ( ! isset($_GET['embed'] ) ) {
?>
<script TYPE="text/javascript" SRC="js/jquery-1.7.1.min.js"></script>
<script type="text/javascript" src="js/jquery-ui-1.8.14.custom.min.js"></script>
<script type="text/javascript" src="js/jquery.liveSearch.js"></script>
<script type="text/javascript" src="js/ganglia.js"></script>
<script type="text/javascript" src="js/jquery.gangZoom.js"></script>
<script type="text/javascript" src="js/jquery.cookie.js"></script>
<script type="text/javascript" src="js/jquery-ui-timepicker-addon.js"></script>
<script type="text/javascript" src="js/jquery.ba-bbq.min.js"></script>
<link type="text/css" href="css/smoothness/jquery-ui-1.8.14.custom.min.css" rel="stylesheet" />
<div id="metric-actions-dialog" style="display: none" title="Metric Actions">
<div id="metric-actions-dialog-content">
	Available Metric actions.
</div>
</div>
<script>
var SHOW_EVENTS_BASE_ID = "show_events_";
var SHOW_EVENTS_BASE_ID_LEN = SHOW_EVENTS_BASE_ID.length;
var GRAPH_BASE_ID = "graph_img_";

function showAllEvents(show) {
  $("[id^=" + SHOW_EVENTS_BASE_ID + "]").each(function() {
      if (show)
        $(this).attr("checked", 'checked');
      else
        $(this).removeAttr("checked");
      $(this).button('refresh');
      var graphId = GRAPH_BASE_ID + 
	$(this).attr('id').slice(SHOW_EVENTS_BASE_ID_LEN);
      showEvents(graphId, show);
    });
}

function showEvents(graphId, show) {
    var graph = $("#" + graphId);
    var src = graph.attr("src");
    if (src.indexOf("graph.php") != 0)
      return;
    var paramStr = "&event=";
    paramStr += show ? "show" : "hide"
    var d = new Date();
    paramStr += "&_=" + d.getTime();
    src = jQuery.param.querystring(src, paramStr);
    graph.attr("src", src);
  }

  $(function() {
    $( "#inspect-graph-dialog" ).dialog({ autoOpen: false, minWidth: 850 });
    $("[id^=" + SHOW_EVENTS_BASE_ID + "]").each(function() {
        $(this).button();
        $(this).attr("checked", 'checked');
	$(this).button('refresh');
    });
    $("#show_all_events").button();
    $("#show_all_events").attr("checked", 'checked');
    $("#show_all_events").button('refresh');
  });
</script>
<?php
} // end of if ( ! isset($_GET['embed'] ) ) {

include_once "./eval_conf.php";

// build a query string but drop r and z since those designate time window and size. Also if the 
// get arguments are an array rebuild them. For example with hreg (host regex)
$ignore_keys_list = array("r", "z", "st", "cs", "ce", "hc");

$SHOW_EVENTS_BASE_ID = "show_events_";
$GRAPH_BASE_ID = "graph_img_";

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
$query_string = "&" . join("&", $query_string_array);

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


if ( $conf['graph_engine'] == "flot" ) {
?>
<style>
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
  var base_url = "<?php print 'graph.php?flot=1&' . $_GET['m'] . $query_string ?>" + "&r=" + default_time;
</script>
<script type="text/javascript" src="js/create-flot-graphs.js"></script>
<?php
} // end of if ( $conf['graph_engine'] == "flot" ) {
?>
</head>

<body>
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
  <b>Host/Cluster/Host Regex: </b><?php print $description ?>&nbsp;<b>Metric/Graph/Metric Regex: </b><?php 
  print $metric_description; 
  ?>&nbsp;&nbsp;<input title="Hide/Show Events" type="checkbox" id="show_all_events" onclick="showAllEvents(this.checked)"/><label class="show_event_text" for="show_all_events">Hide/Show Events</label><br />
<?php
}


if ( preg_match("/aggregate=1/", $query_string) )
  $is_aggregate = true;
else
  $is_aggregate = false;

foreach ( $conf['time_ranges'] as $key => $value ) {

  print '<div class="img_view">
  <span style="padding-left: 4em; padding-right: 4em; text-weight: bold;">' . $key . '</span>';
  
  // Check if it's an aggregate graph
  if ( $is_aggregate  ) {
    print '<button class="cupid-green" title="Metric Actions - Add to View, etc" onclick="metricActionsAggregateGraph(\'' .$query_string . '\'); return false;">+</button>';
  }

   print  ' <a href="./graph.php?r=' . $key . $query_string .'&csv=1"><button title="Export to CSV" class="cupid-green">CSV</button></a> ' .
   ' <a href="./graph.php?r=' . $key . $query_string .'&json=1"><button title="Export to JSON" class="cupid-green">JSON</button></a> ';

   // Check if it's an aggregate graph
  if ( $is_aggregate  ) {
      print ' <button title="Decompose aggregate graph" class="shiny-blue" onClick="openDecompose(\'?r=' . $key . $query_string  . '&dg=1\');return false;">Decompose</button>';
  }
 
  print ' <button title="Inspect Graph" onClick="inspectGraph(\'r=' . $key . $query_string  . '\'); return false;" class="shiny-blue">Inspect</button>';

  $graphId = $GRAPH_BASE_ID . $key;

  print ' <input title="Hide/Show Events" type="checkbox" id="' . $SHOW_EVENTS_BASE_ID . $key . '" onclick="showEvents(\'' . $graphId . '\', this.checked)"/><label class="show_event_text" for="' . $SHOW_EVENTS_BASE_ID . $key . '">Hide/Show Events</label>' .
  '<br />';

  // If we are using flot we need to use a div instead of an image reference
  if ( $conf['graph_engine'] == "flot" ) {

    print '<div id="placeholder_' . $key . '" class="flotgraph img_view"></div>';
    print '<div id="placeholder_' . $key . '_legend" class="flotlegend"></div>';

  } else {

    print '<a href="./graph.php?r=' . $key . '&z=' . $xlargesize . $query_string . '"><img class="noborder" id="' . $graphId . '" style="margin-top:5px;" title="Last ' . $key . '" src="graph.php?r=' . $key . '&z=' . $largesize . $query_string . '"></a>';

  }

  print "</div>";

}
// The div below needs to be added to clear float left since in aggregate view things
// will start looking goofy
?>
<div style="clear: left"></div>
<script>
  $(function() {
    $( "#inspect-graph-dialog" ).dialog({ autoOpen: false, minWidth: 850 });
  });

  function openDecompose($url) {
    $.cookie("ganglia-selected-tab-" + window.name, 0);
    location.href="./index.php" + $url + "&tab=m";
  }

</script>

</body>
</html>
