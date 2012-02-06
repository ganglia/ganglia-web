<?php
$tpl = new Dwoo_Template_File( template("cluster_view.tpl") );
$data = new Dwoo_Data();
$data->assign("php_gd", 
              (function_exists('imagegif') or function_exists('imagepng')));
$data->assign("extra", template("cluster_extra.tpl"));

$data->assign("images","./templates/${conf['template_name']}/images");

$data->assign("user_may_edit", 
              checkAccess( $clustername, GangliaAcl::EDIT, $conf ) );
$data->assign("graph_engine", $conf['graph_engine']);

#
# Correct handling of *_report metrics
#
if (!$showhosts) {
  if (array_key_exists($metricname, $metrics))
    $units = $metrics[$metricname]['UNITS'];
} else {
  if (array_key_exists($metricname, $metrics[key($metrics)]))
     if (isset($metrics[key($metrics)][$metricname]['UNITS']))
       $units = $metrics[key($metrics)][$metricname]['UNITS'];
     else
        $units = '';
}

$data->assign("localtime", date("Y-m-d H:i", $cluster['LOCALTIME']));

get_cluster_overview($showhosts, 
		     $metrics,
		     $cluster,
		     $range, 
		     $clustername, 
		     $data);

$cluster_url = rawurlencode($clustername);

$data->assign("cluster", $clustername);

$graph_args = "c=$cluster_url&amp;$get_metric_string&amp;st=$cluster[LOCALTIME]";

get_cluster_optional_reports($conf, 
			     $clustername, 
			     $graph_args,
			     $data);

#
# Correctly handle *_report cases and blank (" ") units
#
if (isset($units)) {
  $vlabel = $units;
  if ($units == " ")
    $units = "";
  else
    $units=$units ? "($units)" : "";
} else {
  $units = "";
}
$data->assign("metric","$metricname $units");
$data->assign("metric_name","$metricname");
$data->assign("sort", $sort);
$data->assign("range", $range);

$showhosts_levels = array(
   1 => array('checked'=>'', 'name'=>'Auto'),
   2 => array('checked'=>'', 'name'=>'Same'),
   0 => array('checked'=>'', 'name'=>'None'),
);
$showhosts_levels[$showhosts]['checked'] = 'checked';
$data->assign("showhosts_levels", $showhosts_levels);

if ($showhosts) {
  $data->assign("columns_size_dropdown", 1);
  $data->assign("cols_menu", $cols_menu);
  $data->assign("size_menu", $size_menu);
}

if (!(isset($conf['heatmaps_enabled']) and $conf['heatmaps_enabled'] == 1))
  get_cluster_load_pie($showhosts, 
		       $hosts_up, 
		       $hosts_down, 
		       $user, 
		       $conf,
		       $metrics, 
		       $cluster,
		       $name,
		       $data);

get_host_metric_graphs($showhosts, 
                       $hosts_up, 
                       $hosts_down, 
                       $user, 
                       $conf,
                       $metrics, 
                       $metricname,
                       $sort,
                       $clustername,
                       $get_metric_string,
                       $cluster,
                       $always_timestamp,
                       $reports[$metricname],
                       $clustergraphsize,
                       $range,
                       $cs,
                       $ce,
                       $vlabel,
		       $data);

// No reason to go on if we have no up hosts.
if (!is_array($hosts_up) or !$showhosts) {
  $dwoo->output($tpl, $data);
  return;
}

///////////////////////////////////////////////////////////////////////////////
// Creates a heatmap
///////////////////////////////////////////////////////////////////////////////
if (isset($conf['heatmaps_enabled']) and $conf['heatmaps_enabled'] == 1) {
  foreach ($hosts_up as $host => $val) {
    // If host_regex is defined
    if (isset($user['host_regex']) && 
        ! preg_match("/" .$user['host_regex'] . "/", $host))
      continue;
    
    $load = get_load($host, $metrics);
    $host_load[$host] = $load;
  }

  $num_hosts = count($host_load);

  $matrix = ceil(sqrt($num_hosts));

  $xindex = 0;
  $yindex = 0;

  foreach ($host_load as $key => $value) {
    if ($xindex >= $matrix) {
      $string_array[] = "[" . join(",", $matrix_array[$yindex]) . "]";
      $yindex++;
      $xindex = 0;
    }

    $matrix_array[$yindex][$xindex] = $value;
    $xindex++;
  }

  $string_array[] = "[" . join(",", $matrix_array[$yindex]) . "]";

  $conf['heatmap_size'] = 200;

  $heatmap = join(",", $string_array);

  $data->assign("heatmap", $heatmap);
  $data->assign("heatmap_size", floor($conf['heatmap_size'] / $matrix));

}

///////////////////////////////////////////////////////////////////////////////
// Show stacked graphs
///////////////////////////////////////////////////////////////////////////////
if (isset($conf['show_stacked_graphs']) and 
    $conf['show_stacked_graphs'] == 1  and 
    ! preg_match("/_report$/", $metricname)) {
      $stacked_args = "m=$metricname&c=$cluster_url&r=$range&st=$cluster[LOCALTIME]";
      if ( isset($user['host_regex']) )
        $stacked_args .= "&host_regex=" .  $user['host_regex'];
      $data->assign( "stacked_graph_args", $stacked_args );

}


$dwoo->output($tpl, $data);
?>
