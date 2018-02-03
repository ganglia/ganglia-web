<?php

$tpl = new Dwoo_Template_File( template("decompose_graph.tpl") );
$data = new Dwoo_Data();
$data->assign("range", $range);

$graph_type = "line";
$line_width = "2";


$user['view_name'] = isset($_GET["vn"]) ? sanitize ($_GET["vn"]) : NULL;
$user['item_id'] = isset($_GET["item_id"]) ? sanitize ($_GET["item_id"]) : NULL;
$user['hreg'] = isset($_GET["hreg"]) ? $_GET["hreg"] : NULL;
$user['mreg'] = isset($_GET["mreg"]) ? $_GET["mreg"] : NULL;

#################################################################################
# Let's check if we are decomposing a composite graph from a view
#################################################################################
if ( $user['view_name'] and $user['item_id'] ) {
  
    $available_views = get_available_views();
    foreach ( $available_views as $id => $view ) {
      # Find view settings
      if ( $user['view_name'] == $view['view_name'] )
	break;
    }

    unset($available_views);

    foreach ( $view['items'] as $index => $graph_config ) {
      if (  $user['item_id'] == $graph_config['item_id'] )
	break;
    }

    unset($view);

    $title = "";
    
} else if ( isset($_GET['aggregate']) ) {


  $graph_config = build_aggregate_graph_config ($graph_type, $line_width, $user['hreg'], $user['mreg']);

  foreach ( $user['hreg'] as $index => $arg ) {
    print "<input type=hidden name=hreg[] value='" . sanitize($arg) . "'>";
  }
  foreach ( $user['mreg'] as $index => $arg ) {
    print "<input type=hidden name=mreg[] value='" . sanitize($arg) . "'>";
  }

} else {

  print '
      <div class="ui-widget">
			<div class="ui-state-error ui-corner-all" style="padding: 0 .7em;"> 
				<p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span> 
				<strong>Alert:</strong> This graph can not be decomposed</p>
			</div>
      </div>
  ';

  exit(1);

}

$size = isset($clustergraphsize) ? $clustergraphsize : 'default';
$size = $size == 'medium' ? 'default' : $size; //set to 'default' to preserve old behavior

$additional_host_img_css_classes = "";
if ( isset($conf['zoom_support']) && $conf['zoom_support'] === true )
    $additional_host_img_css_classes = "host_${size}_zoomable";

$data->assign("additional_host_img_css_classes", $additional_host_img_css_classes);

$items = array();

$graphargs = "";
if ($cs)
   $graphargs .= "&amp;cs=" . rawurlencode($cs);
if ($ce)
   $graphargs .= "&amp;ce=" . rawurlencode($ce);

foreach ( $graph_config['series'] as $index => $item ) {
   $args = "h=" . $item['hostname'] . "&c=" . $item['clustername'] . "&m=" . $item['metric'];
   $items[] = array ( "title" => $item['hostname'] . " " . $item['metric'],
          "url_args" => $args . $graphargs . "&r=" . $range
   );

}

$data->assign("items", $items);
$data->assign("number_of_items", count($items));
$dwoo->output($tpl, $data);
