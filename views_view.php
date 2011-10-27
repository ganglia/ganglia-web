<?php

$tpl = new Dwoo_Template_File( template("views_view.tpl") );
$data = new Dwoo_Data();
$data->assign("range",$range);

if( ! checkAccess('views/*', 'view', $conf) ) {
  die("You do not have access to view views.");
}

$available_views = get_available_views();
// Pop up a warning message if there are no available views
// (Disable temporarily, otherwise we can't create views)
if ( sizeof($available_views) == -1 ) {
    $error_msg = '
	<div class="ui-widget">
			  <div class="ui-state-error ui-corner-all" style="padding: 0 .7em;"> 
				  <p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span> 
				  <strong>Alert:</strong> There are no views defined.</p>
			  </div>
	</div>
    ';
}

$size = isset($clustergraphsize) ? $clustergraphsize : 'default';
$size = $size == 'medium' ? 'default' : $size; //set to 'default' to preserve old behavior

$additional_host_img_css_classes = "";
if ( isset($conf['zoom_support']) && $conf['zoom_support'] === true )
    $additional_host_img_css_classes = "host_${size}_zoomable";

$data->assign("additional_host_img_css_classes", $additional_host_img_css_classes);

foreach ( $available_views as $view_id => $view ) {

 if ( $view['view_name'] == $user["viewname"] ) {

    $view_elements = get_view_graph_elements($view);

    $view_items = array();

    if ( count($view_elements) != 0 ) {
        
      $graphargs = "";
      if ($cs)
         $graphargs .= "&amp;cs=" . rawurlencode($cs);
      if ($ce)
         $graphargs .= "&amp;ce=" . rawurlencode($ce);
        
      foreach ( $view_elements as $id => $element ) {
        $view_items[] = array ( "legend" => isset($element['hostname']) ? $element['hostname'] : "Aggregate graph",
          "url_args" => htmlentities($element['graph_args']) . "&amp;z=medium&r=" . $range . $graphargs,
          "aggregate_graph" => isset($element['aggregate_graph']) ? 1 : 0
        );
      }
    }
    
    
    $data->assign("number_of_view_items", sizeof($view_items));
    break;
        
 }  // end of if ( $view['view_name'] == $view_name
} // end of foreach ( $views as $view_id 

$data->assign("view_items", $view_items);
$dwoo->output($tpl, $data);

?>
