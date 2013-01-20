<?php
include_once("./eval_conf.php");
include_once("./functions.php");
include_once("./global.php");

if (! checkAccess(GangliaAcl::ALL_VIEWS, GangliaAcl::VIEW, $conf))
  die("You do not have access to view views.");

///////////////////////////////////////////////////////////////////////////////
// Create new view
///////////////////////////////////////////////////////////////////////////////
if (isset($_GET['create_view'])) {
  if(! checkAccess(GangliaAcl::ALL_VIEWS, GangliaAcl::EDIT, $conf)) {
    $output = "You do not have access to edit views.";
  } else {
    // Check whether the view name already exists
    $view_exists = 0;

    $available_views = get_available_views();

    foreach ($available_views as $view_id => $view) {
      if ($view['view_name'] == $_GET['view_name']) {
        $view_exists = 1;
      }
    }

    if ($view_exists == 1) {
      $output = "<strong>Alert:</strong> View with the name " .
                $_GET['view_name'] . 
                " already exists.";
    } else {
      $empty_view = array ("view_name" => $_GET['view_name'],
                           "items" => array());
      $view_suffix = str_replace(" ", "_", $_GET['view_name']);
      $view_filename = $conf['views_dir'] . "/view_" . preg_replace('/[^a-zA-Z0-9_-]/', '', $view_suffix) . ".json";
      if ( pathinfo( $view_filename, PATHINFO_DIRNAME ) != $conf['views_dir'] ) {
        die('Invalid path detected');
      }
      $json = json_encode($empty_view);
      if (file_put_contents($view_filename, 
                            json_prettyprint($json)) === FALSE) {
        $output = "<strong>Alert:</strong>" .
                  " Can't write to file " . htmlspecialchars($view_filename) .
                  " Perhaps permissions are wrong.";
      } else {
        $output = "View has been created successfully.";
      }
    }
  }
?>
<div class="ui-widget">
  <div class="ui-state-default ui-corner-all" style="padding: 0 .7em;"> 
    <p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span> 
    <?php echo $output ?></p>
  </div>
</div>
<?php
  exit(0);
} 

///////////////////////////////////////////////////////////////////////////////
// Delete view
///////////////////////////////////////////////////////////////////////////////
if (isset($_GET['delete_view'])) {
  if (! checkAccess(GangliaAcl::ALL_VIEWS, GangliaAcl::EDIT, $conf)) {
    $output = "You do not have access to edit views.";
  } else {
    // Check whether the view name already exists
    $view_exists = 0;

    $available_views = get_available_views();

    foreach ($available_views as $view_id => $view) {
      if ($view['view_name'] == $_GET['view_name']) {
        $view_exists = 1;
      }
    }

    if ($view_exists != 1) {
      $output = "<strong>Alert:</strong> View with the name " .
      $_GET['view_name'] . 
      " does not exist.";
    } else {
      $view_suffix = str_replace(" ", "_", $_GET['view_name']);
      $view_filename = $conf['views_dir'] . "/view_" . preg_replace('/[^a-zA-Z0-9_-]/', '', $view_suffix) . ".json";
      if ( pathinfo( $view_filename, PATHINFO_DIRNAME ) != $conf['views_dir'] ) {
        die('Invalid path detected');
      }
      if (unlink($view_filename) === FALSE) {
        $output = "<strong>Alert:</strong>" .
                  " Can't remove file $view_filename." .
                  " Perhaps permissions are wrong.";
      } else {
        $output = "View has been successfully removed.";
      }
    }
  }
} // delete_view

///////////////////////////////////////////////////////////////////////////////
// Add to view
///////////////////////////////////////////////////////////////////////////////
if (isset($_GET['add_to_view'])) {
  if (! checkAccess(GangliaAcl::ALL_VIEWS, GangliaAcl::EDIT, $conf)) {
    $output = "You do not have access to edit views.";
  } else {
    $view_exists = 0;
    // Check whether the view name already exists
    $available_views = get_available_views();

    foreach ($available_views as $view_id => $view) {
      if ($view['view_name'] == $_GET['view_name']) {
        $view_exists = 1;
        break;
      }
    }

    if ($view_exists == 0) {
      $output = "<strong>Alert:</strong> View " .
      $_GET['view_name'] . 
      " does not exist. This should not happen.";
    } else {
      // Read in contents of an existing view
      $view_filename = $view['file_name'];
      // Delete the file_name index
      unset($view['file_name']);

      # Check if we are adding an aggregate graph
      if (isset($_GET['aggregate'])) {
	foreach ( $_GET['mreg'] as $key => $value ) 
	  $metric_regex_array[] = array("regex" => $value);

	  foreach ($_GET['hreg'] as $key => $value) 
	    $host_regex_array[] = array("regex" => $value);

	  $item_array = array("aggregate_graph" => "true", 
                              "metric_regex" => $metric_regex_array, 
	                      "host_regex" => $host_regex_array, 
                              "graph_type" => stripslashes($_GET['gtype']),
	                      "vertical_label" => stripslashes($_GET['vl']),
                              "title" => $_GET['title'],
                        "glegend" => $_GET['glegend']);

          if (isset($_GET['x']) && is_numeric($_GET['x'])) {
            $item_array["upper_limit"] = $_GET['x'];
          }
          if ( isset($_GET['n']) && is_numeric($_GET['n'])) {
            $item_array["lower_limit"] = $_GET['n'];
          }
          if ( isset($_GET['c']) ) {
            $item_array["cluster"] = $_GET['c'];
          }

          if ( isset($_GET['h']) ) { $item_array['host'] = $_GET['h']; unset($item_array['host_regex']); }
          if ( isset($_GET['m']) ) { $item_array['metric'] = $_GET['m']; unset($item_array['metric_regex']); }
          if ( isset($_GET['g']) ) { $item_array['graph'] = $_GET['g']; }
          if ($item_array['host_regex'] == null) $item_array['host_regex'] = '.*';

          $view['items'][] = $item_array;
          unset($item_array);

      } else {
	if ($_GET['type'] == "metric") {
          $items = array("hostname" => $_GET['host_name'], 
                         "metric" => $_GET['metric_name']);
	  if (isset($_GET['vertical_label']))
            $items["vertical_label"] = stripslashes($_GET['vertical_label']);
	  if (isset($_GET['title']))
            $items["title"] = stripslashes($_GET['title']);
	  if (isset($_GET['c']))
            $items["cluster"] = $_GET['c'];
          if (isset($_GET['warning']) && is_numeric($_GET['warning']))
            $items["warning"] = $_GET['warning'];
          if (isset($_GET['critical']) && is_numeric($_GET['critical']))
            $items["critical"] = $_GET['critical'];
          
	  $view['items'][] = $items;
	} else
	  $view['items'][] = array("hostname" => $_GET['host_name'], 
                                   "graph" => $_GET['metric_name']);
      }

      $json = json_encode($view);

      if (file_put_contents($view_filename, 
                            json_prettyprint($json)) === FALSE ) {
        $output = "<strong>Alert:</strong>" .
                  " Can't write to file $view_filename." .
                  " Perhaps permissions are wrong.";
      } else {
        $output = "View has been updated successfully.";
      } 
    }  
  }
?>
<div class="ui-widget">
  <div class="ui-state-default ui-corner-all" style="padding: 0 .7em;"> 
    <p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span> 
    <?php echo $output ?></p>
  </div>
</div>
<?php
  exit(0);
} 

$available_views = get_available_views();
$existing_views = '';
foreach ($available_views as $view) {
 $v = $view['view_name'];
 $vid = viewId($v);
 $checked = ($_GET['vn'] == $v);
 $existing_views .= '<input type="radio" id="' . $vid . '" onClick="selectView(\'' . $v . '\'); return false;"' . ($checked ? " checked" : "") . '><label style="text-align:left;" class="nobr" for="' . $vid . '">' . $v . '</label>'; 
}

if (isset($_GET['views_menu'])) {
?>
<div id="views_menu">
  <?php echo $existing_views ?>
</div>
<script type="text/javascript">$(function(){$("#views_menu").buttonsetv();});</script>
<?php
  exit(0);
}

$tpl = new Dwoo_Template_File( template("views_view.tpl") );
$data = new Dwoo_Data();
$data->assign("range",$range);

// Pop up a warning message if there are no available views
// (Disable temporarily, otherwise we can't create views)
if (sizeof($available_views) == -1) {
  $error_msg = '
    <div class="ui-widget">
      <div class="ui-state-error ui-corner-all" style="padding: 0 .7em;"> 
        <p><span class="ui-icon ui-icon-alert" 
                 style="float: left; margin-right: .3em;"></span> 
	   <strong>Alert:</strong> There are no views defined.</p>
      </div>
    </div>';
}

$size = isset($clustergraphsize) ? $clustergraphsize : 'default';
//set to 'default' to preserve old behavior
$size = $size == 'medium' ? 'default' : $size; 

$additional_host_img_css_classes = "";
if ( isset($conf['zoom_support']) && $conf['zoom_support'] === true )
    $additional_host_img_css_classes = "host_${size}_zoomable";

$data->assign("additional_host_img_css_classes", 
              $additional_host_img_css_classes);

$data->assign("existing_views", $existing_views);
$data->assign("view_name", $user["viewname"]);

$view_items = NULL;
foreach ($available_views as $view_id => $view) {
 if ($view['view_name'] == $user["viewname"]) {
   $view_elements = get_view_graph_elements($view);
   $view_items = array();
   if ( count($view_elements) != 0) {
     $graphargs = "";
     if ($cs)
       $graphargs .= "&amp;cs=" . rawurlencode($cs);
     if ($ce)
       $graphargs .= "&amp;ce=" . rawurlencode($ce);
        
     foreach ($view_elements as $id => $element) {
       $view_items[] = array ("legend" => isset($element['hostname']) ? $element['hostname'] : "Aggregate graph",
                               "url_args" => htmlentities($element['graph_args']) . "&amp;r=" . $range . $graphargs,

                               "aggregate_graph" => isset($element['aggregate_graph']) ? 1 : 0
        );
      }
    }
    
    $data->assign("number_of_view_items", sizeof($view_items));
    break;    
 }  // end of if ( $view['view_name'] == $view_name
} // end of foreach ( $views as $view_id 

if (isset($view_items))
  $data->assign("view_items", $view_items);

$data->assign('GRAPH_BASE_ID', $GRAPH_BASE_ID);
$data->assign('SHOW_EVENTS_BASE_ID', $SHOW_EVENTS_BASE_ID);
 
$dwoo->output($tpl, $data);

?>
