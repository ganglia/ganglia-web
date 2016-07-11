<?php
$tpl = new Dwoo_Template_File( template("footer.tpl") );
$data = new Dwoo_Data(); 
$data->assign("webfrontend_version", $version["webfrontend"]);

if (isset($_GET["hide-hf"]) && filter_input(INPUT_GET, "hide-hf", FILTER_VALIDATE_BOOLEAN, array("flags" => FILTER_NULL_ON_FAILURE))) {
  $data->assign("hide_footer", true);
}

if ($version["rrdtool"]) {
   $data->assign("rrdtool_version", $version["rrdtool"]);
}

$backend_components = array("gmetad", "gmetad-python", "gmond");

foreach ($backend_components as $backend) {
   if (isset($version[$backend])) {
      $data->assign("webbackend_component", $backend);
      $data->assign("webbackend_version", $version[$backend]);
      break;
   }
}

$data->assign("parsetime", sprintf("%.4f", $parsetime) . "s");

$dwoo->output($tpl, $data);
?>
