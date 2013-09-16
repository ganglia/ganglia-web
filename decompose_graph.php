<?php

$tpl = new Dwoo_Template_File( template("decompose_graph.tpl") );
$data = new Dwoo_Data();
$data->assign("range",$range);


if ( !isset($_GET['hreg']) or !isset($_GET['mreg']) ) {
    print '
	<div class="ui-widget">
			  <div class="ui-state-error ui-corner-all" style="padding: 0 .7em;"> 
				  <p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span> 
				  <strong>Alert:</strong> Host Regex and Metric Regex arguments are missing.</p>
			  </div>
	</div>
    ';

    exit(1);
}

$graph_type = "line";
$line_width = "2";

if ( isset($_GET['vn']) && isset($_GET['item_id']) ) {

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

  $graph_config = build_rrdtool_args_from_json ( $rrdtool_graph, $graph_config );
} else {


$graph_config = build_aggregate_graph_config ($graph_type, $line_width, $_GET['hreg'], $_GET['mreg']);

foreach ( $_GET['hreg'] as $index => $arg ) {
  print "<input type=hidden name=hreg[] value='" . htmlspecialchars($arg) . "'>";
}
foreach ( $_GET['mreg'] as $index => $arg ) {
  print "<input type=hidden name=mreg[] value='" . htmlspecialchars($arg) . "'>";
}

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
   $items[] = array ( "title" => "",
          "url_args" => $args . $graphargs . "&r=" . $range
   );

}

#print "<PRE>"; print_r($items);

$data->assign("items", $items);
$data->assign("number_of_items", sizeof($items));
$dwoo->output($tpl, $data);

?>
