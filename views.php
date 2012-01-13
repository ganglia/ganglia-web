<?php

include_once("./eval_conf.php");
include_once("./functions.php");

if( ! checkAccess(GangliaAcl::ALL_VIEWS, GangliaAcl::VIEW, $conf) ) {
  die("You do not have access to view views.");
}

//////////////////////////////////////////////////////////////////////////////////////////////////////
// Create new view
//////////////////////////////////////////////////////////////////////////////////////////////////////
if ( isset($_GET['create_view']) ) {
  if( ! checkAccess( GangliaAcl::ALL_VIEWS, GangliaAcl::EDIT, $conf ) ) {
    $output = "You do not have access to edit views.";
  } else {
    // Check whether the view name already exists
    $view_exists = 0;

    $available_views = get_available_views();

    foreach ( $available_views as $view_id => $view ) {
      if ( $view['view_name'] == $_GET['view_name'] ) {
        $view_exists = 1;
      }
    }

    if ( $view_exists == 1 ) {
      $output = "<strong>Alert:</strong> View with the name ".$_GET['view_name']." already exists.";
    } else {
      $empty_view = array ( "view_name" => $_GET['view_name'],
        "items" => array() );
      $view_suffix = str_replace(" ", "_", $_GET['view_name']);
      $view_filename = $conf['views_dir'] . "/view_" . $view_suffix . ".json";
      $json = json_encode($empty_view);
      if ( file_put_contents($view_filename, json_prettyprint($json)) === FALSE ) {
        $output = "<strong>Alert:</strong> Can't write to file $view_filename. Perhaps permissions are wrong.";
      } else {
        $output = "View has been created successfully.";
      } // end of if ( file_put_contents($view_filename, $json) === FALSE ) 
    }  // end of if ( $view_exists == 1 )
  }
?>
<div class="ui-widget">
  <div class="ui-state-default ui-corner-all" style="padding: 0 .7em;"> 
    <p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span> 
    <?php echo $output ?></p>
  </div>
</div>
<?php
  exit(1);
} 

//////////////////////////////////////////////////////////////////////////////////////////////////////
// Delete view
//////////////////////////////////////////////////////////////////////////////////////////////////////
if ( isset($_GET['delete_view']) ) {
  if( ! checkAccess( GangliaAcl::ALL_VIEWS, GangliaAcl::EDIT, $conf ) ) {
    $output = "You do not have access to edit views.";
  } else {
    // Check whether the view name already exists
    $view_exists = 0;

    $available_views = get_available_views();

    foreach ( $available_views as $view_id => $view ) {
      if ( $view['view_name'] == $_GET['view_name'] ) {
        $view_exists = 1;
      }
    }

    if ( $view_exists != 1 ) {
      $output = "<strong>Alert:</strong> View with the name ".$_GET['view_name']." does not exist.";
    } else {
      $view_suffix = str_replace(" ", "_", $_GET['view_name']);
      $view_filename = $conf['views_dir'] . "/view_" . $view_suffix . ".json";
      if ( unlink($view_filename) === FALSE ) {
        $output = "<strong>Alert:</strong> Can't remove file $view_filename. Perhaps permissions are wrong.";
      } else {
        $output = "View has been successfully removed.";
      }
    }
  }
} // delete_view

//////////////////////////////////////////////////////////////////////////////////////////////////////
// Add to view
//////////////////////////////////////////////////////////////////////////////////////////////////////
if ( isset($_GET['add_to_view']) ) {
  if( ! checkAccess( GangliaAcl::ALL_VIEWS, GangliaAcl::EDIT, $conf ) ) {
    $output = "You do not have access to edit views.";
  } else {
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
      $output = "<strong>Alert:</strong> View ".$_GET['view_name']." does not exist. This should not happen.";
    } else {

      // Read in contents of an existing view
      $view_filename = $view['file_name'];
      // Delete the file_name index
      unset($view['file_name']);

      # Check if we are adding an aggregate graph
      if ( isset($_GET['aggregate']) ) {

	  foreach ( $_GET['mreg'] as $key => $value ) 
	    $metric_regex_array[] = array("regex" => $value);

	  foreach ( $_GET['hreg'] as $key => $value ) 
	    $host_regex_array[] = array("regex" => $value);

	  $item_array = array( "aggregate_graph" => "true", "metric_regex" => $metric_regex_array, 
	    "host_regex" => $host_regex_array, "graph_type" => $_GET['gtype'],
	    "vertical_label" => $_GET['vl'], "title" => $_GET['title']);

          if ( isset($_GET['x']) && is_numeric($_GET['x'])) {
            $item_array["upper_limit"] = $_GET['x'];
          }
          if ( isset($_GET['n']) && is_numeric($_GET['n'])) {
            $item_array["lower_limit"] = $_GET['n'];
          }

          if ( isset($_GET['c']) ) {
            $item_array["cluster"] = $_GET['c'];
          }

          $view['items'][] = $item_array;
          unset($item_array);

      } else {
	if ( $_GET['type'] == "metric" ) {
          $items = array( "hostname" => $_GET['host_name'], "metric" => $_GET['metric_name'] );
	  if (isset($_GET['vertical_label']))
              $items["vertical_label"] = $_GET['vertical_label'];
	  if (isset($_GET['title']))
              $items["title"] = $_GET['title'];
	  $view['items'][] = $items;
	} else
	  $view['items'][] = array( "hostname" => $_GET['host_name'], "graph" => $_GET['metric_name']);

      }

      $json = json_encode($view);

      if ( file_put_contents($view_filename, json_prettyprint($json)) === FALSE ) {
        $output = "<strong>Alert:</strong> Can't write to file $view_filename. Perhaps permissions are wrong.";
      } else {
        $output = "View has been updated successfully.";
      } // end of if ( file_put_contents($view_filename, $json) === FALSE ) 
    }  // end of if ( $view_exists == 1 )
  }
?>
<div class="ui-widget">
  <div class="ui-state-default ui-corner-all" style="padding: 0 .7em;"> 
    <p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span> 
    <?php echo $output ?></p>
  </div>
</div>
<?php
  exit(1);
} 



// Load the metric caching code we use if we need to display graphs
retrieve_metrics_cache();

$available_views = get_available_views();

// Pop up a warning message if there are no available views
// (Disable temporarily, otherwise we can't create views)
if ( sizeof($available_views) == -1 ) {
    ?>
	<div class="ui-widget">
			  <div class="ui-state-error ui-corner-all" style="padding: 0 .7em;"> 
				  <p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span> 
				  <strong>Alert:</strong> There are no views defined.</p>
			  </div>
	</div>
  <?php
} else {

  if ( !isset($_GET['view_name']) && !isset($_GET['vn']) ) {
    if ( sizeof($available_views) == 1 )
      $view_name = $available_views[0]['view_name'];
    else
      $view_name = "default";
  } else {
    $view_name = isset($_GET['view_name']) ? $_GET['view_name'] : $_GET['vn'];
  }

  if ( isset($_GET['standalone']) ) {
    $base = isset($_GET['base']) ? $_GET['base'] . "/" : "";
    ?>
<html><head>
<script type="text/javascript" src="<?php print $base; ?>js/jquery-1.7.1.min.js"></script>
<script type="text/javascript" src="<?php print $base; ?>js/jquery-ui-1.8.14.custom.min.js"></script>
<script type="text/javascript" src="<?php print $base; ?>js/ganglia.js"></script>
<script type="text/javascript" src="<?php print $base; ?>js/jquery.cookie.js"></script>
<script type="text/javascript" src="<?php print $base; ?>js/jquery-ui-timepicker-addon.js"></script>
<link type="text/css" href="css/smoothness/jquery-ui-1.8.14.custom.min.css" rel="stylesheet" />
<LINK rel="stylesheet" href="./styles.css" type="text/css">
<?php
if ( isset($_GET['view_name']) ) {

  print "<script type=\"text/javascript\">$(document).ready(function() {selectView('" . $_GET['view_name'] . "');});</script>";

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
    if( ! isset($_GET['standalone']) && 
        checkAccess( GangliaAcl::ALL_VIEWS, GangliaAcl::EDIT, $conf ) ) {
       print '<button onclick="return false" id="create_view_button">Create View</button>';
    }
    if ( ! isset($_GET['standalone']) && 
         ! isset($_GET['just_graphs']) ) {
       print '<a href="javascript:void(0)" onclick="detachViews();" id="detach-tab-button">Detach Tab</a>';
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

      $range_menu .= "<input onChange=\"$.cookie('ganglia-view-range-' + window.name, '" . $v . "'); $('#view-cs').val(''); $('#view-ce').val(''); $.cookie('ganglia-view-cs-' + window.name, ''); $.cookie('ganglia-view-ce-' + window.name, ''); getViewsContentJustGraphs($('#view_name').val(), '" . $v . "', '','');\" type=\"radio\" id=\"view-range-$v\" name=\"r\" value=\"$v\" $checked/><label for=\"view-range-$v\">$v</label>";

   }
  print $range_menu;
?>
      &nbsp;&nbsp;or <span class="nobr">from 
<?php
  $custom_range = "<input onChange=\"$.cookie('ganglia-view-cs-' + window.name, $('#view-cs').val())\" type=\"text\" title=\"Feb 27 2007 00:00, 2/27/2007, 27.2.2007, now -1 week, -2 days, start + 1 hour, etc.\" name=\"cs\" id=\"view-cs\" size=\"17\"> to ";
 
  $custom_range .= "<input onChange=\"$.cookie('ganglia-view-ce-' + window.name, $('#view-ce').val())\" type=\"text\" title=\"Feb 27 2007 00:00, 2/27/2007, 27.2.2007, now -1 week, -2 days, start + 1 hour, etc.\" name=\"ce\" id=\"view-ce\" size=\"17\">";
 
  $custom_range .= "<input type=\"button\" onclick=\"getViewsContentJustGraphs($('#view_name').val(), '', $('#view-cs').val(), $('#view-ce').val() ); return false;\" value=\"Go\" id=\"view-custom-go\">";

  $custom_range .= "<input type=\"button\" value=\"Clear\" onclick=\"$('#view-cs').val(''); $('#view-ce').val(''); $.cookie('ganglia-view-cs-' + window.name, ''); $.cookie('ganglia-view-ce-' + window.name, ''); return false;\">";

      print $custom_range;
?>
		    </span></form><p>&nbsp;</p>
      </div>

  <?php

  } // end of  if ( ! isset($_GET['just_graphs']) 

  ///////////////////////////////////////////////////////////////////////////////////////////////////////
  // Displays graphs in the graphs div
  ///////////////////////////////////////////////////////////////////////////////////////////////////////
  print "<div id=\"view_graphs\">";

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
	    <a href=\"" . ( isset($_GET['base']) ? $_GET['base'] : '.' ) . "/graph_all_periods.php?" . htmlentities($element['graph_args']) ."&amp;z=large\">
	    <img title=\"" . $legend . " - " . $element['name'] . "\" border=0 SRC=\"" . ( isset($_GET['base']) ? $_GET['base'] : '.' ) . "/graph.php?" . htmlentities($element['graph_args']) . "&amp;z=small" . $range_args .  "\" style=\"padding:2px;\"></A>";

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


} // end of ie else ( ! isset($available_views )

?>
