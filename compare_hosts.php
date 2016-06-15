<?php
include_once("./global.php");

$tpl = new Dwoo_Template_File( template("compare_hosts.tpl") );
$data = new Dwoo_Data();
$data->assign("range", $range);

$size = isset($clustergraphsize) ? $clustergraphsize : 'default';
//set to 'default' to preserve old behavior
$size = $size == 'medium' ? 'default' : $size; 

retrieve_metrics_cache();

$matches = array();
if (array_key_exists('hreg', $_GET)) {
  foreach ( $_GET['hreg'] as $key => $query ) {
    if ($query != '') {
      foreach ( $index_array['hosts'] as $key => $host_name ) {
        if ( preg_match("/$query/i", $host_name ) ) {
          // We can have same hostname in multiple clusters
          foreach ($index_array['cluster'][$host_name] as $clustername) {
            $matches[] = array ("hostname" => $host_name, "clustername" => $clustername);
          }
        }
      }
    }
  }
}

#print "<PRE>";print_r($index_array['metrics']);

$host_metrics = array();
$host_cluster = array();
foreach ( $matches as $index => $match ) {
  $hostname = $match['hostname'];
  $host_cluster[] = $match['hostname'] . "|" . $match['clustername'];
  foreach ( $index_array['metrics'] as $metric_name => $hosts ) {
    if ( array_search( $hostname, $hosts ) !== FALSE && 
         ! isset($host_metrics[$metric_name]) ) {
      $host_metrics[$metric_name] = 1; 
    }
  }
}

# Join the hosts in a list into a string which we pass to graphs
$host_list = join(",", $host_cluster);

ksort($host_metrics);
#print "<PRE>";print_r($host_metrics);

$hmetrics = array();
foreach ( $host_metrics as $name => $value )
  $hmetrics[] = $name;


$hreg = "";
if (array_key_exists('hreg', $_GET)) {
  foreach ( $_GET['hreg'] as $index => $arg ) {
    $hreg .= "&hreg[]=" . rawurlencode($arg);
  }
}

if ( isset($_GET['hreg']) ) {
  $data->assign("hreg_arg", htmlspecialchars($_GET['hreg'][0]) );
} else {
  $data->assign("hreg_arg", "");
}

$size = isset($clustergraphsize) ? $clustergraphsize : 'default';
//set to 'default' to preserve old behavior
$size = $size == 'medium' ? 'default' : $size; 

$additional_host_img_css_classes = "";
if ( isset($conf['zoom_support']) && $conf['zoom_support'] === true )
    $additional_host_img_css_classes = "host_${size}_zoomable";

$data->assign("additional_host_img_css_classes", $additional_host_img_css_classes);

$graphargs = "&r=" . $range;
if ($cs)
   $graphargs .= "&amp;cs=" . rawurlencode($cs);
if ($ce)
   $graphargs .= "&amp;ce=" . rawurlencode($ce);

$data->assign("hreg", $hreg);
$data->assign("graphargs", $graphargs);
$data->assign("host_list", $host_list);
$data->assign("host_metrics", $hmetrics);
$data->assign("number_of_metrics", count($hmetrics));

$data->assign('GRAPH_BASE_ID', $GRAPH_BASE_ID);
$data->assign('SHOW_EVENTS_BASE_ID', $SHOW_EVENTS_BASE_ID);

$dwoo->output($tpl, $data);

?>
