<?php

$tpl = new Dwoo_Template_File( template("compare_hosts.tpl") );
$data = new Dwoo_Data();
$data->assign("range",$range);

$size = isset($clustergraphsize) ? $clustergraphsize : 'default';
$size = $size == 'medium' ? 'default' : $size; //set to 'default' to preserve old behavior

retrieve_metrics_cache();

foreach ( $_GET['hreg'] as $key => $query ) {
  foreach ( $index_array['hosts'] as $key => $host_name ) {
    if ( preg_match("/$query/i", $host_name ) ) {
      // We can have same hostname in multiple clusters
      $matches[] = array ( "hostname" => $host_name, "clustername" => $index_array['cluster'][$host_name]); 
    }
  }
} 

#print "<PRE>";print_r($index_array['metrics']);

foreach ( $matches as $index => $match ) {
  $hostname = $match['hostname'];
  foreach ( $index_array['metrics'] as $metric_name => $hosts ) {
    if ( array_search( $hostname , $hosts ) !== NULL && ! isset($host_metrics[$metric_name]) ) {
      $host_metrics[$metric_name] = 1; 
    }
  }
}

ksort($host_metrics);
#print "<PRE>";print_r($host_metrics);

foreach ( $host_metrics as $name => $value )
  $hmetrics[] = $name;


$hreg = "";

foreach ( $_GET['hreg'] as $index => $arg ) {
  $hreg .= "&hreg[]=" . $arg;
}

$size = isset($clustergraphsize) ? $clustergraphsize : 'default';
$size = $size == 'medium' ? 'default' : $size; //set to 'default' to preserve old behavior

$additional_host_img_css_classes = "";
if ( isset($conf['zoom_support']) && $conf['zoom_support'] === true )
    $additional_host_img_css_classes = "host_${size}_zoomable";

$data->assign("additional_host_img_css_classes", $additional_host_img_css_classes);

$data->assign("hreg", $hreg);
$data->assign("host_metrics", $hmetrics);
$data->assign("number_of_metrics", sizeof($hmetrics));
$dwoo->output($tpl, $data);

?>
