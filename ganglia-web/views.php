<?php

$GLOBALS['ganglia_dir'] = dirname(__FILE__);
$GLOBALS['views_dir'] = $GLOBALS['ganglia_dir'] . '/conf';

//////////////////////////////////////////////////////////////////////////////////////////////////////
// Get all the available views
//////////////////////////////////////////////////////////////////////////////////////////////////////
function get_available_views() {
  /* -----------------------------------------------------------------------
  Find available views by looking in the GANGLIA_DIR/conf directory
  anything that matches view_*.json. Read them all and build a available_views
  array
  ----------------------------------------------------------------------- */
  $available_views = array();

  if ($handle = opendir($GLOBALS['views_dir'])) {

      while (false !== ($file = readdir($handle))) {

	if ( preg_match("/view_(.*)/", $file, $out) ) {

	  $view_config_file = $GLOBALS['views_dir'] . "/" . $file;
	  if ( ! is_file ($view_config_file) ) {
	    echo("Can't read view config file " . $view_config_file . ". Please check permissions");
	  }

	  $view = json_decode(file_get_contents($view_config_file), TRUE);	  
	  $available_views[] = array ( "file_name" => $view_config_file, "name" => $view['view_name'],
	    "items" => $view['items']);
	  unset($view);

	}
      }

      closedir($handle);
  }

  return $available_views;

}

//////////////////////////////////////////////////////////////////////////////////////////////////////
// Create new view
//////////////////////////////////////////////////////////////////////////////////////////////////////
if ( isset($_GET['create_view']) ) {
  // Check whether the view name already exists
  $view_exists = 0;

  $available_views = get_available_views();

  foreach ( $available_views as $view_id => $view ) {
    if ( $view['name'] == $_GET['view_name'] ) {
      $view_exists = 1;
    }
  }

  if ( $view_exists == 1 ) {
    print "View with the name " . $_GET['view_name'] . " already exists.";
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
    <style>
    ul#horiz-list
    {
    margin-left: 0;
    padding-left: 0;
    white-space: nowrap;
    }

    #horiz-list li
    {
    display: inline;
    list-style-type: none;
    }

    #horiz-list a { padding: 0px 5px; }

    #horiz-list a:link, #horiz-list a:visited
    {
    color: green;
    background-color: #eeeeee;
    text-decoration: none;
    }

    #horiz-list a:hover
    {
    color: green;
    background-color: #369;
    text-decoration: none;
    }
    </style>
    <table id=views_table>
    <tr><td valign=top>
    <script>
      $( "#create-new-view-dialog" ).dialog({
	autoOpen: false,
	height: 200,
	width: 350,
	modal: true,
	close: function() {
	  getViewsContent();
	  }
	});
    </script>
    <div id="create-new-view-dialog" title="Create new view">
	<form id="create_view_form">
	<input type=hidden name=create_view value=1>
		<label for="name">View Name</label>
		<input type="text" name="view_name" id="view_name" class="text ui-widget-content ui-corner-all" />
	  <center><button onclick="createView();">Create</button></center>
	</form>
    </div>

    <button onclick="return false" id=create_view_button>Create View</button>
    <p>  <div id="views_menu">
      Existing views:
      <ul id="navlist">
    <?php

    # List all the available views
    foreach ( $available_views as $view_id => $view ) {
      $v = $view['name'];
      print '<li><a href="#" onClick="getViewsContentJustGraphs(\'' . $v . '\'); return false;">' . $v . '</a></li>';  
    }

    ?>
    </ul></div></td><td valign=top>
    <div id=view_range_chooser>
    <ul id="horiz-list">
    <li><a href="#">hour</a></li>
    <li><a href="#">day</a></li>
    <li><a href="#">week</a></li>
    <li><a href="#">month</a></li>
    <li><a href="#">year</a></li>
    </ul>
    </div>

  <?php

  } // end of  if ( ! isset($_GET['just_graphs']) 
	
  print "<div id=view_graphs>";

  foreach ( $available_views as $view_id => $view ) {
   if ( $view['name'] == $view_name ) {

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
    }  // end of if ( $view['view_name'] == $view_name
  } // end of foreach ( $views as $view_id 

  print "</div>"; 

  if ( ! isset($_GET['just_graphs']) )
    print "</td></tr></table></form>";

} // end of ie else ( ! isset($available_views )

?>
