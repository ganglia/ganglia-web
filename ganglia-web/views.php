<?php

include_once("./conf.php");
include_once("./functions.php");
//////////////////////////////////////////////////////////////////////////////////////////////////////
// Create new view
//////////////////////////////////////////////////////////////////////////////////////////////////////
if ( isset($_GET['create_view']) ) {
  // Check whether the view name already exists
  $view_exists = 0;

  $available_views = get_available_views();

  foreach ( $available_views as $view_id => $view ) {
    if ( $view['view_name'] == $_GET['view_name'] ) {
      $view_exists = 1;
    }
  }

  if ( $view_exists == 1 ) {
  ?>
      <div class="ui-widget">
	<div class="ui-state-error ui-corner-all" style="padding: 0 .7em;"> 
	  <p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span> 
	  <strong>Alert:</strong> View with the name <?php print $_GET['view_name']; ?> already exists.</p>
	</div>
      </div>
    <?php
  } else {
    $empty_view = array ( "view_name" => $_GET['view_name'],
      "items" => array() );
    $view_suffix = str_replace(" ", "_", $_GET['view_name']);
    $view_filename = $GLOBALS['views_dir'] . "/view_" . $view_suffix . ".json";
    $json = json_encode($empty_view);
    if ( file_put_contents($view_filename, $json) === FALSE ) {
    ?>
      <div class="ui-widget">
	<div class="ui-state-error ui-corner-all" style="padding: 0 .7em;"> 
	  <p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span> 
	  <strong>Alert:</strong> Can't write to file <?php print $view_filename; ?>. Perhaps permissions are wrong.</p>
	</div>
      </div>
    <?php
    } else {
    ?>
      <div class="ui-widget">
	<div class="ui-state-default ui-corner-all" style="padding: 0 .7em;"> 
	  <p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span> 
	  View has been created successfully.</p>
	</div>
	</div>
    <?php
    } // end of if ( file_put_contents($view_filename, $json) === FALSE ) 
  }  // end of if ( $view_exists == 1 )
  exit(1);
} 

//////////////////////////////////////////////////////////////////////////////////////////////////////
// Create new view
//////////////////////////////////////////////////////////////////////////////////////////////////////
if ( isset($_GET['add_to_view']) ) {

  $view_exists = 0;
  // Check whether the view name already exists
  $available_views = get_available_views();

  foreach ( $available_views as $view_id => $view ) {
    if ( $view['view_name'] == $_GET['view_name'] ) {
      $view_exists = 1;
      break;
    }
  }

  if ( $view_exists == 0 ) {
  ?>
      <div class="ui-widget">
	<div class="ui-state-error ui-corner-all" style="padding: 0 .7em;"> 
	  <p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span> 
	  <strong>Alert:</strong> View <?php print $_GET['view_name']; ?> does not exist. This should not happen.</p>
	</div>
      </div>
    <?php
  } else {

    // Read in contents of an existing view
    $view_filename = $view['file_name'];
    // Delete the file_name index
    unset($view['file_name']);

    if ( $_GET['type'] == "metric" ) 
      $view['items'][] = array( "hostname" => $_GET['host_name'], "metric" => $_GET['metric_name']);
    else
      $view['items'][] = array( "hostname" => $_GET['host_name'], "graph" => $_GET['metric_name']);

    $json = json_encode($view);

    if ( file_put_contents($view_filename, $json) === FALSE ) {
    ?>
      <div class="ui-widget">
	<div class="ui-state-error ui-corner-all" style="padding: 0 .7em;"> 
	  <p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span> 
	  <strong>Alert:</strong> Can't write to file <?php print $view_filename; ?>. Perhaps permissions are wrong.</p>
	</div>
      </div>
    <?php
    } else {
    ?>
      <div class="ui-widget">
	<div class="ui-state-default ui-corner-all" style="padding: 0 .7em;"> 
	  <p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span> 
	  View has been updated successfully.</p>
	</div>
	</div>
    <?php
    } // end of if ( file_put_contents($view_filename, $json) === FALSE ) 
  }  // end of if ( $view_exists == 1 )
  exit(1);
} 



// Load the metric caching code we use if we need to display graphs
require_once('./cache.php');

$available_views = get_available_views();

// Pop up a warning message if there are no available views
if ( sizeof($available_views) == 0 ) {
    ?>
	<div class="ui-widget">
			  <div class="ui-state-error ui-corner-all" style="padding: 0 .7em;"> 
				  <p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span> 
				  <strong>Alert:</strong> There are no views defined.</p>
			  </div>
	</div>
  <?php
} else {

  if ( !isset($_GET['view_name']) ) {
    if ( sizeof($available_views) == 1 )
      $view_name = $available_views[0]['view_name'];
    else
      $view_name = "default";
  } else {
    $view_name = $_GET['view_name'];
  }

  ?>

  <form id=view_chooser_form>
  <?php
  
  if ( ! isset($_GET['just_graphs']) ) {

  ?>
    <table id=views_table>
    <tr><td valign=top>
  
    <button onclick="return false" id=create_view_button>Create View</button>
    <p>  <div id="views_menu">
      Existing views:
      <ul id="navlist">
    <?php

    # List all the available views
    foreach ( $available_views as $view_id => $view ) {
      $v = $view['view_name'];
      print '<li><a href="#" onClick="getViewsContentJustGraphs(\'' . $v . '\'); return false;">' . $v . '</a></li>';  
    }

    ?>
    </ul></div></td><td valign=top>
    <div id=view_range_chooser>
    <form id=view_timerange_form>
    <input type="hidden" name=view_name id=view_name value="">
<?php
   $context_ranges = array_keys( $time_ranges );
   if ($jobrange)
      $context_ranges[]="job";
   if ($cs or $ce)
      $context_ranges[]="custom";

   $range_menu = "<B>Last</B>&nbsp;&nbsp;";
   foreach ($context_ranges as $v) {
      $url=rawurlencode($v);
      if ($v == $range)
	$checked = "checked=\"checked\"";
      else
	$checked = "";
      $range_menu .= "<input OnChange=\"getViewsContentJustGraphs($('#view_name').val());\" type=\"radio\" id=\"view-range-$v\" name=\"r\" value=\"$v\" $checked/><label for=\"view-range-$v\">$v</label>";

   }
  print $range_menu;
?>
      &nbsp;&nbsp;or from <INPUT TYPE="TEXT" TITLE="Feb 27 2007 00:00, 2/27/2007, 27.2.2007, now -1 week, -2 days, start + 1 hour, etc." NAME="cs" ID="datepicker-cs" SIZE="17"> to <INPUT TYPE="TEXT" TITLE="Feb 27 2007 00:00, 2/27/2007, 27.2.2007, now -1 week, -2 days, start + 1 hour, etc." NAME="ce" ID="datepicker-ce" SIZE="17"> <input type="submit" value="Go">
      <input type="button" value="Clear" onclick="ganglia_submit(1)">
      </form><p>
      </div>
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

      switch ( $view['view_type'] ) {

	case "standard":
	// Does view have any items/graphs defined
	if ( sizeof($view['items']) == 0 ) {
	  print "No graphs defined for this view. Please add some";
	} else {
	  foreach ( $view['items'] as $item_id => $item ) {

	    // Is it a metric or a graph(report)
	    if ( isset($item['metric']) ) {
	      $graph_args_array[] = "m=" . $item['metric'];
	    } else {
	      $graph_args_array[] = "g=" . $item['graph'];
	    }

	    $hostname = $item['hostname'];
	    $cluster = $index_array['cluster'][$hostname];
	    $graph_args_array[] = "h=$hostname";
	    $graph_args_array[] = "c=$cluster";

	    $graph_args = join("&", $graph_args_array);

	    print "
	      <A HREF=\"./graph_all_periods.php?$graph_args&z=large\">
	      <IMG BORDER=0 SRC=\"./graph.php?$graph_args&z=medium\"></A>";

	    unset($graph_args_array);

	  } // end of foreach ( $view['items']
	} // end of if ( sizeof($view['items'])
	break;
	;;

	////////////////////////////////////////////////////////////////////////////////////
	// Currently only supports matching hosts.
	////////////////////////////////////////////////////////////////////////////////////
	case "regex":
	  foreach ( $view['items'] as $item_id => $item ) {
	    // Is it a metric or a graph(report)
	    if ( isset($item['metric']) ) {
	      $metric_suffix = "m=" . $item['metric'];
	    } else {
	      $metric_suffix = "g=" . $item['graph'];
	    }

	    // Find hosts matching a criteria
	    $query = $item['hostname'];
	    foreach ( $index_array['hosts'] as $key => $host_name ) {
	      if ( preg_match("/$query/", $host_name ) ) {
		$cluster = $index_array['cluster'][$host_name];
		$graph_args_array[] = "h=$host_name";
		$graph_args_array[] = "c=$cluster";
		$graph_args = $metric_suffix . "&" . join("&", $graph_args_array);

		print "
		  <A HREF=\"./graph_all_periods.php?$graph_args&z=large\">
		  <IMG BORDER=0 SRC=\"./graph.php?$graph_args&z=medium\"></A>";

		unset($graph_args_array);

	      }
	    }

	    
	  } // end of foreach ( $view['items'] as $item_id => $item )
	break;;
      
      } // end of switch ( $view['view_type'] ) {
    }  // end of if ( $view['view_name'] == $view_name
  } // end of foreach ( $views as $view_id 

  print "</div>"; 

  if ( ! isset($_GET['just_graphs']) )
    print "</td></tr></table></form>";

} // end of ie else ( ! isset($available_views )

?>
