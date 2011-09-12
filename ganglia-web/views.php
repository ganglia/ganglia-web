<?php
/* $Id$ */

$tpl = new Dwoo_Template_File( template("views.tpl") );
$data = new Dwoo_Data();
$data->assign("extra", template("host_extra.tpl"));

$data->assign("cluster", $clustername);
$data->assign("host", $hostname);
$data->assign("may_edit_cluster", checkAccess( $clustername, GangliaAcl::EDIT, $conf ) );
$data->assign("may_edit_views", checkAccess( GangliaAcl::ALL_VIEWS, GangliaAcl::EDIT, $conf) );
$data->assign("node_image", node_image($metrics));
$data->assign("sort",$sort);
$data->assign("range",$range);
$data->assign("hostname", $hostname);
$data->assign("graph_engine", $conf['graph_engine']);

$cluster_url=rawurlencode($clustername);
$data->assign("cluster_url", $cluster_url);
$data->assign("graphargs", $graph_args);

# For the node view link.
$data->assign("node_view","./?p=2&amp;c=$cluster_url&amp;h=$hostname");

# No reason to go on if this node is down.
if ($hosts_down)
   {
      $dwoo->output($tpl, $data);
      return;
   }

$data->assign("ip", $hosts_up['IP']);
$data->assign('columns_dropdown', 1);
$data->assign("metric_cols_menu", $metric_cols_menu);
$data->assign("size_menu", $size_menu);
$g_metrics_group = array();

if ( $conf['graph_engine'] == "flot" ) {
  $data->assign("graph_height", $conf['graph_sizes'][$size]["height"] + 50);
  $data->assign("graph_width", $conf['graph_sizes'][$size]["width"]);
}
$data->assign("g_metrics_group_data", $g_metrics_group_data);
$dwoo->output($tpl, $data);

?>
