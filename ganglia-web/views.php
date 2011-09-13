<?php

include_once("./eval_conf.php");
include_once("./functions.php");

if( ! checkAccess(GangliaAcl::ALL_VIEWS, GangliaAcl::VIEW, $conf) ) {
  die("You do not have access to view views.");
}

?>
<!--
<div class="ui-widget">
  <div class="ui-state-default ui-corner-all" style="padding: 0 .7em;"> 
    <p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span> 
    <?php echo $output ?></p>
  </div>
</div>

-->
<?php

// Load the metric caching code we use if we need to display graphs
require_once('./cache.php');

$available_views = get_available_views();

// Pop up a warning message if there are no available views
  if ( !isset($_GET['view_name']) ) {
    if ( sizeof($available_views) == 1 )
      $view_name = $available_views[0]['view_name'];
    else
      $view_name = "default";
  } else {
    $view_name = $_GET['view_name'];
  }

  if ( isset($_GET['standalone']) ) {
    ?>
<html><head>
<script TYPE="text/javascript" SRC="js/jquery-1.6.2.min.js"></script>
<script type="text/javascript" src="js/jquery-ui-1.8.14.custom.min.js"></script>
<script type="text/javascript" src="js/ganglia.js"></script>
<script type="text/javascript" src="js/jquery.cookie.js"></script>
<link type="text/css" href="css/smoothness/jquery-ui-1.8.14.custom.css" rel="stylesheet" />
<LINK rel="stylesheet" href="./styles.css" type="text/css">
<?php
if ( isset($_GET['view_name']) ) {

  print "<script type=\"text/javascript\">selectView('" . $_GET['view_name'] . "');</script>";

}
?>
</head>
<body>
  <div id="tabs-views-content">
    <?php
  }

  print "<form id=view_chooser_form>";
  
  if ( ! isset($_GET['just_graphs']) ) {

  ?>
    <table id="views_table">
    <tr><td valign=top>

  <?php
    if(  checkAccess( GangliaAcl::ALL_VIEWS, GangliaAcl::EDIT, $conf ) ) {
       print '<button onclick="return false" id="create_view_button">Create View</button>';
    }
    if ( ! isset($_GET['standalone']) && ! isset($_GET['just_graphs']) ) {
       print '<a href="views.php?standalone=1" id="detach-tab-button">Detach Tab</a>';
    }
  ?>
    <div id="views_menu">
      <p>Existing views:</p>
      <ul id="navlist">
    <?php

    # List all the available views
    foreach ( $available_views as $view_id => $view ) {
      $v = $view['view_name'];
      print '<li><a href="#" id="' . viewId($v) . '" onClick="selectView(\'' . $v . '\'); return false;">' . $v . '</a></li>';
    }
    print '</ul>';

    ?>
<script type="text/javascript">
$(function(){
    $( "#view_range_chooser" ).buttonset();
    <?php
    if ( ! isset($_GET['standalone']) && ! isset($_GET['just_graphs']) ) {
    ?>
    $( "#detach-tab-button").button();
    <?php
    }
    ?>
    $('#view_name').val("default");
});
</script>


    </div></td><td valign=top><div>
    <div id="view_range_chooser">
    <form id="view_timerange_form">
    <input type="hidden" name="view_name" id="view_name" value="">
<?php
   $context_ranges = array_keys( $conf['time_ranges'] );
   if (isset($jobrange))
      $context_ranges[]="job";
   if (isset($cs) or isset($ce))
      $context_ranges[]="custom";

   if ( isset($_GET['r']) ) 
    $range = $_GET['r'];
   else
    $range = "";

   $range_menu = "<B>Last</B>&nbsp;&nbsp;";
   foreach ($context_ranges as $v) {
      $url=rawurlencode($v);
      if ($v == $range)
	$checked = "checked=\"checked\"";
      else
	$checked = "";

      $range_menu .= "<input onChange=\"$.cookie('ganglia-view-range-' + window.name, '" . $v . "'); $('#view-cs').val(''); $('#view-ce').val(''); getViewsContentJustGraphs($('#view_name').val(), '" . $v . "', '','');\" type=\"radio\" id=\"view-range-$v\" name=\"r\" value=\"$v\" $checked/><label for=\"view-range-$v\">$v</label>";

   }
  print $range_menu;
?>
      &nbsp;&nbsp;or <span class="nobr">from 
  <input type="text" title="Feb 27 2007 00:00, 2/27/2007, 27.2.2007, now -1 week, -2 days, start + 1 hour, etc." name="cs" id="view-cs" size="17"> to 
  <input type="text" title="Feb 27 2007 00:00, 2/27/2007, 27.2.2007, now -1 week, -2 days, start + 1 hour, etc." name="ce" id="view-ce" size="17"> 
  <input type="button" onclick="getViewsContentJustGraphs($('#view_name').val(), '', $('#view-cs').val(), $('#view-ce').val() ); return false;" value="Go">
  <input type="button" value="Clear" onclick="$('#view-cs').val(''); $('#view-ce').val('') ; return false;">
		    </span></form><p>&nbsp;</p>
      </div>

  <?php

  } // end of  if ( ! isset($_GET['just_graphs']) 

  ///////////////////////////////////////////////////////////////////////////////////////////////////////
  // Displays graphs in the graphs div
  ///////////////////////////////////////////////////////////////////////////////////////////////////////
  print "<div id=view_graphs>";

  // Let's find the view definition
  foreach ( $available_views as $view_id => $view ) {

   if ( $view['view_name'] == $view_name ) {

      $view_elements = get_view_graph_elements($view);

      $range_args = "";
      if ( isset($_GET['r']) && $_GET['r'] != "" ) 
	    $range_args .= "&amp;r=" . $_GET['r'];
      if ( isset($_GET['cs']) && isset($_GET['ce']) ) 
	    $range_args .= "&amp;cs=" . $_GET['cs'] . "&amp;ce=" . $_GET['ce'];

      if ( count($view_elements) != 0 ) {
	foreach ( $view_elements as $id => $element ) {
	    $legend = isset($element['hostname']) ? $element['hostname'] : "Aggregate graph";
	    print "
	    <a href=\"./graph_all_periods.php?" . htmlentities($element['graph_args']) ."&amp;z=large\">
	    <img title=\"" . $legend . " - " . $element['name'] . "\" border=0 SRC=\"./graph.php?" . htmlentities($element['graph_args']) . "&amp;z=medium" . $range_args .  "\" style=\"padding:2px;\"></A>";

	}
      } else {
	print "No graphs defined for this view. Please add some";
      }

   }  // end of if ( $view['view_name'] == $view_name
  } // end of foreach ( $views as $view_id 

  print "</div>"; 

  if ( ! isset($_GET['just_graphs']) )
    print "</div></td></tr></table></form>";

  if ( isset($_GET['standalone']) ) {
    print "</div></body></html>";
  }

?>
