<?php
include_once("./eval_conf.php");
include_once("./functions.php");
include_once("./global.php");

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
  $view_name = sanitize($_GET['vn']);
}

function viewFileName($view_name) {
  global $conf;

  $view_suffix = str_replace(" ", "_", $view_name);
  return $conf['views_dir'] .
    "/view_" .
    preg_replace('/[^a-zA-Z0-9_\-]/', '', $view_suffix) . ".json";
}

$viewList = new ViewList();

///////////////////////////////////////////////////////////////////////////////
// Create new view
///////////////////////////////////////////////////////////////////////////////
if (isset($_GET['create_view'])) {
  if(! checkAccess(GangliaAcl::ALL_VIEWS, GangliaAcl::EDIT, $conf)) {
    $output = "You do not have access to edit views.";
  } else {
    if ($viewList->viewExists($view_name)) {
      $output = "<strong>Alert:</strong> View with the name " .
                $view_name .
                " already exists.";
    } else {
      $empty_view = array ("view_name" => $view_name,
                           "items" => array());
      $view_filename = viewFileName($view_name);
      if (pathinfo($view_filename, PATHINFO_DIRNAME) != $conf['views_dir']) {
        die('Invalid path detected');
      }

      $json = json_encode($empty_view);
      if (file_put_contents($view_filename,
                            json_prettyprint($json)) === FALSE) {
        $output = "<strong>Alert:</strong>" .
                  " Can't write to file " . htmlspecialchars($view_filename) .
                  " Perhaps permissions are wrong.";
      } else {
        $output = "View has been created successfully.";
      }
    }
  }
  $json = '{"output": "<div class=\"ui-widget\"><div class=\"ui-state-default ui-corner-all\" style=\"padding: 0 .7em;\"><p><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span>' . $output . '</div></div>"';
  if ($conf['display_views_using_tree']) {
    $json .= ',"tree_node": {"text":"' . $view_name . '","id":"' . viewId($view_name) . '"';
    $json .= ',"view_name":"' . $view_name . '"';
    $json .= '}';
  }
  $json .= '}';
  echo $json;
  exit(0);
}

///////////////////////////////////////////////////////////////////////////////
// Delete view
///////////////////////////////////////////////////////////////////////////////
if (isset($_GET['delete_view'])) {
  if (! checkAccess(GangliaAcl::ALL_VIEWS, GangliaAcl::EDIT, $conf)) {
    $output = "You do not have access to edit views.";
  } else {
    if (!$viewList->viewExists($view_name)) {
      $output = "<strong>Alert:</strong> View with the name " .
      $view_name .
      " does not exist.";
    } else {
      $view_filename = viewFileName($view_name);
      if (pathinfo($view_filename, PATHINFO_DIRNAME) != $conf['views_dir']) {
        die('Invalid path detected');
      }

      if (unlink($view_filename) === FALSE) {
        $output = "<strong>Alert:</strong>" .
                  " Can't remove file $view_filename." .
                  " Perhaps permissions are wrong.";
      } else {
        $output = "View has been successfully removed.";
	$viewList->removeView($view_name);
      }
    }
  }
} // delete_view

///////////////////////////////////////////////////////////////////////////////
// Add to view
///////////////////////////////////////////////////////////////////////////////
if (isset($_GET['add_to_view'])) {
  if (! checkAccess(GangliaAcl::ALL_VIEWS, GangliaAcl::EDIT, $conf)) {
    $output = "You do not have access to edit views.";
  } else {
    if (!$viewList->viewExists($view_name)) {
      $output = "<strong>Alert:</strong> View " .
      $view_name .
      " does not exist. This should not happen.";
    } else {
      $view = $viewList->getView($view_name);

      # Check if we are adding an aggregate graph
      if (isset($_GET['aggregate'])) {
	foreach ($_GET['mreg'] as $key => $value)
	  $metric_regex_array[] = array("regex" => $value);

	foreach ($_GET['hreg'] as $key => $value)
	  $host_regex_array[] = array("regex" => $value);

	$item_array = array("aggregate_graph" => "true",
			    "metric_regex" => $metric_regex_array,
			    "host_regex" => $host_regex_array,
			    "graph_type" => stripslashes($_GET['gtype']),
			    "vertical_label" => stripslashes($_GET['vl']),
			    "title" => $_GET['title'],
			    "glegend" => $_GET['glegend']);

	if (isset($_GET['x']) && is_numeric($_GET['x'])) {
	  $item_array["upper_limit"] = $_GET['x'];
	}

	if (isset($_GET['n']) && is_numeric($_GET['n'])) {
	  $item_array["lower_limit"] = $_GET['n'];
	}
	if (isset($_GET['c'])) {
	  $item_array["cluster"] = $_GET['c'];
	}

	if (isset($_GET['h'])) {
	  $item_array['host'] = $_GET['h'];
	  unset($item_array['host_regex']);
	}
	if (isset($_GET['m'])) {
	  $item_array['metric'] = $_GET['m'];
	  unset($item_array['metric_regex']);
	}
	if (isset($_GET['g'])) {
	  $item_array['graph'] = $_GET['g'];
	}
	if ($item_array['host_regex'] == null)
	  $item_array['host_regex'] = '.*';

	$view['items'][] = $item_array;
	unset($item_array);
      } else {
	if ($_GET['type'] == "metric") {
          $items = array("hostname" => $_GET['host_name'],
                         "metric" => $_GET['metric_name']);
	  if (isset($_GET['vertical_label']))
            $items["vertical_label"] = stripslashes($_GET['vertical_label']);
	  if (isset($_GET['title']))
            $items["title"] = stripslashes($_GET['title']);
	  if (isset($_GET['c']))
            $items["cluster"] = $_GET['c'];
          if (isset($_GET['warning']) && is_numeric($_GET['warning']))
            $items["warning"] = $_GET['warning'];
          if (isset($_GET['critical']) && is_numeric($_GET['critical']))
            $items["critical"] = $_GET['critical'];

	  $view['items'][] = $items;
	} else
	  $view['items'][] = array("hostname" => $_GET['host_name'],
                                   "graph" => $_GET['metric_name']);
      }

      $view_filename = $view['file_name'];
      // Remove the file_name attribute, it is not stored in the view defn.
      unset($view['file_name']);
      $json = json_encode($view);
      if (file_put_contents($view_filename,
                            json_prettyprint($json)) === FALSE ) {
        $output = "<strong>Alert:</strong>" .
	  " Can't write to file: \"$view_filename\"." .
	  " Perhaps permissions are wrong.";
      } else {
        $output = "View has been updated successfully.";
      }
    }
  }
?>
<div class="ui-widget">
  <div class="ui-state-default ui-corner-all" style="padding: 0 .7em;">
    <p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>
    <?php echo $output ?></p>
  </div>
</div>
<?php
  exit(0);
}

class ViewTreeNode {
  private $name = NULL;
  private $data = NULL;
  private $children = NULL;
  private $parent = NULL;

  public function __construct($name) {
    $this->name = $name;
  }

  public function hasChild($name) {
    if ($this->children == NULL)
      return false;
    return array_key_exists($name, $this->children);
  }

  public function getChild($name) {
    return $this->children[$name];
  }

  public function getChildren() {
    return $this->children;
  }

  public function addChild($node) {
    if ($this->children == NULL)
      $this->children = array();
    $this->children[$node->getName()] = $node;
    $node->setParent($this);
    return $node;
  }

  public function getNode($path) {
    $parent = $this;
    foreach (explode("/", $path) as $node_name) {
      if ($parent->hasChild($node_name)) {
	$parent = $parent->getChild($node_name);
      } else {
	$parent = $parent->addChild(new ViewTreeNode($node_name));
      }
    }
    return $parent;
  }

  public function getData() {
    return $this->data;
  }

  public function setData($data) {
    $this->data = $data;
  }

  public function getName() {
    return $this->name;
  }

  public function getParent() {
    return $this->parent;
  }

  public function setParent($parent) {
    $this->parent = $parent;
  }

  public function toJson($initially_open) {
    $pathName = $this->getPathName();
    $id = viewId($pathName);
    $json = '{"text":"' . $this->name . '","id":"' . $id . '"';
    if ($this->data != NULL)
      $json .= ',"view_name":"' . $pathName . '"';

    if ($initially_open && in_array($pathName, $initially_open))
      $json .= ',"state":{"opened": true}';

    if ($this->children != NULL) {
      $json .= ',"children":[';
      $i = 0;
      foreach ($this->children as $child_node) {
	if ($i++ > 0) $json .= ',';
	$json .= $child_node->toJson($initially_open);
      }
      $json .= ']';
    }
    $json .= '}';
    return $json;
  }

  function getPathName() {
    global $VIEW_NAME_SEP;

    if ($this->parent == NULL)
      return $this->name;

    $pathName = $this->name;
    $parent = $this->parent;
    while ($parent->getParent() != NULL) {
      $pathName = $parent->getName() . $VIEW_NAME_SEP . $pathName;
      $parent = $parent->getParent();
    }
    return $pathName;
  }
}

function build_view_tree($views) {
  $view_tree = new ViewTreeNode('root');
  foreach ($views as $view) {
    $node = new ViewTreeNode($view['view_name']);
    $node->setData($view);
    if ($view['parent'] != NULL) {
      $parent = $view_tree->getNode($view['parent']);
      $parent->addChild($node);
    } else {
      $view_tree->addChild($node);
    }
  }
  return $view_tree;
}

function getViewSelectors($viewList,
                          $display_views_using_tree,
                          $view_tree_nodes_initially_open,
                          $selected_view) {
  $existing_views = '';
  if ($display_views_using_tree) {
    $initially_open = NULL;
    if (!isset($_SESSION['view_tree_built']) &&
        isset($view_tree_nodes_initially_open))
      $initially_open = $view_tree_nodes_initially_open;

    $view_tree = build_view_tree($viewList->getViews());
    $existing_views = '[';
    $i = 0;
    foreach ($view_tree->getChildren() as $view_node) {
      if ($i++ > 0) $existing_views .= ',';
      $existing_views .= $view_node->toJson($initially_open);
      $i++;
    }
    $existing_views .= ']';
    $_SESSION['view_tree_built'] = TRUE;
  } else {
    foreach ($viewList->getViews() as $view) {
      if ($view['parent'] == NULL) {
        $v = $view['view_name'];
        $vid = viewId($v);
        $checked = ($selected_view == $v);
        $existing_views .= '<input type="radio" id="' . $vid . '" name="views_menu_button_group" onchange="selectView(\'' . $v . '\'); return false;"' . ($checked ? " checked" : "") . '><label style="text-align:left;" class="nobr" for="' . $vid . '">' . $v . '</label>';
      }
    }
  }
  return $existing_views;
}

$existing_views = getViewSelectors($viewList,
                                   $conf['display_views_using_tree'],
                                   $conf['view_tree_nodes_initially_open'],
                                   $_GET['vn']);
if (isset($_GET['views_menu'])) {
  if (!$conf['display_views_using_tree']) {
    echo $existing_views;
  }
  exit(0);
}

$tpl = new Dwoo_Template_File( template("views_view.tpl") );
$data = new Dwoo_Data();
$data->assign("range", $range);

if (isset($conf['ad-hoc-views']) &&
    $conf['ad-hoc-views'] === true &&
    isset($_GET['ad-hoc-view'])) {
  $is_ad_hoc = true;
}

// Pop up a warning message if there are no available views
// (Disable temporarily, otherwise we can't create views)
if (count($viewList->getViews()) == -1 && !$is_ad_hoc) {
  $error_msg = '
    <div class="ui-widget">
      <div class="ui-state-error ui-corner-all" style="padding: 0 .7em;">
        <p><span class="ui-icon ui-icon-alert"
                 style="float: left; margin-right: .3em;"></span>
	   <strong>Alert:</strong> There are no views defined.</p>
      </div>
    </div>';
}

$size = isset($clustergraphsize) ? $clustergraphsize : 'default';

// set to 'default' to preserve old behavior
if ($size == 'medium')
  $size = 'default';

$additional_host_img_css_classes = "";
if (isset($conf['zoom_support']) && $conf['zoom_support'] === true)
  $additional_host_img_css_classes = "host_${size}_zoomable";

$data->assign("additional_host_img_css_classes",
              $additional_host_img_css_classes);

$data->assign("existing_views", $existing_views);
$data->assign("view_name", $view_name);
$data->assign("display_views_using_tree", $conf["display_views_using_tree"]);

$view_items = NULL;
if ($is_ad_hoc) {
  $data->assign("ad_hoc_view", true);
  $data->assign("ad_hoc_view_json", rawurlencode($_GET['ad-hoc-view']));
  $ad_hoc_view_json = json_decode(heuristic_urldecode($_GET['ad-hoc-view']),
				  true);
  $view_items = getViewItems($ad_hoc_view_json, $range, $cs, $ce);
} else {
  $view = $viewList->getView($view_name);
  if ($view != NULL) {
    $view_items = getViewItems($view, $range, $cs, $ce);
    if ($view['common_y_axis'] != 0)
      $data->assign("common_y_axis", 1);
  }
}

if (isset($view_items)) {
  $data->assign("view_items", $view_items);
  $data->assign("number_of_view_items", count($view_items));
}

$data->assign('GRAPH_BASE_ID', $GRAPH_BASE_ID);
$data->assign('SHOW_EVENTS_BASE_ID', $SHOW_EVENTS_BASE_ID);
$data->assign('graph_engine', $conf['graph_engine']);
$data->assign('flot_graph', isset($conf['flot_graph']) ? true : null);
$dwoo->output($tpl, $data);

?>
