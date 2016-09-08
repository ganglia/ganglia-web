<?php
include_once("./eval_conf.php");
include_once("./functions.php");
include_once("./global.php");
include_once("./dwoo/dwooAutoload.php");

if (! checkAccess(GangliaAcl::ALL_VIEWS, GangliaAcl::VIEW, $conf))
  die("You do not have access to view views.");

$view_name = NULL;

if (isset($_GET['vn']) && !is_proper_view_name($_GET['vn'])) {
?>
<div class="ui-widget">
  <div class="ui-state-default ui-corner-all" styledefault="padding: 0 .7em;">
    <p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>
    View names valid characters are 0-9, a-z, A-Z, -, _ and space. View has not been created.</p>
  </div>
</div>
<?php
  exit(0);
} else {
  $view_name = $_GET['vn'];
}

$viewList = new ViewList();

$dwoo = new Dwoo($conf['dwoo_compiled_dir'], $conf['dwoo_cache_dir']);
$tpl = new Dwoo_Template_File( template("view_content.tpl") );
$data = new Dwoo_Data();

$size = isset($clustergraphsize) ? $clustergraphsize : 'default';
// set to 'default' to preserve old behavior
if ($size == 'medium')
  $size = 'default';

$additional_host_img_css_classes = "";
if (isset($conf['zoom_support']) && $conf['zoom_support'] === true)
  $additional_host_img_css_classes = "host_${size}_zoomable";

$data->assign("additional_host_img_css_classes",
              $additional_host_img_css_classes);

$view_items = NULL;
$view = $viewList->getView($view_name);
if ($view != NULL) {
  $range = isset($_GET["r"]) ? escapeshellcmd(rawurldecode($_GET["r"])) : NULL;
  $cs = isset($_GET["cs"]) ? escapeshellcmd($_GET["cs"]) : NULL;
  $ce = isset($_GET["ce"]) ? escapeshellcmd($_GET["ce"]) : NULL;
  if ($cs or $ce)
    $range = "custom";
  $view_items = getViewItems($view, $range, $cs, $ce);
}

if (isset($view_items)) {
  $data->assign("view_items", $view_items);
  $data->assign("number_of_view_items", count($view_items));
  if ($view['common_y_axis'] != 0)
    $data->assign("common_y_axis", 1);
}

$data->assign('GRAPH_BASE_ID', $GRAPH_BASE_ID);
$data->assign('SHOW_EVENTS_BASE_ID', $SHOW_EVENTS_BASE_ID);
$data->assign('graph_engine', $conf['graph_engine']);
$data->assign('flot_graph', isset($conf['flot_graph']) ? true : null);
$dwoo->output($tpl, $data);

?>
