<?php
include_once "./eval_conf.php";
include_once "./functions.php";
include_once "./get_context.php";
include_once "./ganglia.php";
include_once "./get_ganglia.php";
include_once "./dwoo/dwooAutoload.php";

try {
  $dwoo = new Dwoo($conf['dwoo_compiled_dir'], $conf['dwoo_cache_dir']);
} catch (Exception $e) {
  print "<H4>There was an error initializing the Dwoo PHP Templating Engine: " .
    $e->getMessage() . 
    "<br><br>The compile directory should be owned and writable by the apache user.</H4>";
  exit;
}

$tpl = new Dwoo_Template_File( template("host_overview.tpl") );
$data = new Dwoo_Data();
getHostOverViewData($hostname, 
                    $metrics, 
                    $cluster,
                    $hosts_up, 
                    $hosts_down, 
                    $always_timestamp, 
                    $always_constant, 
                    $data);
$dwoo->output($tpl, $data);
?>
