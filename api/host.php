<?php
// vim: tabstop=2:softtabstop=2:shiftwidth=2:expandtab

if ( !isset($_GET['debug']) ) {
  header("Content-Type: text/json");
}

$conf['gweb_root'] = dirname(dirname(__FILE__));

include_once $conf['gweb_root'] . "/eval_conf.php";
include_once $conf['gweb_root'] . "/functions.php";
include_once $conf['gweb_root'] . "/lib/common_api.php";

// Cache metrics
include_once $conf['gweb_root'] . "/ganglia.php";
include_once $conf['gweb_root'] . "/get_ganglia.php";

if ( !isset($_GET['action']) ) {
  api_return_error( "Error: You need to specify an action at a minimum" );
}

// Variables
$hostname = sanitize($_GET['h']);
$cluster_url = sanitize($_GET['c']);
$range = sanitize($_GET['r']);
$debug = sanitize($_GET['debug']);

function form_image_url ( $page, $args ) {
  global $conf;
  $a = array();
  foreach ($args as $k => $v) {
    if ($v != null) {
      $a[] = $k . "=" . urlencode($v);
    }
  }
  return ( !empty($conf['external_location']) ? $conf['external_location'] . '/' : "" ) . $page . "?" . join("&", $a);
}

switch ( $_GET['action'] ) {
  case 'list':
    $rrd_dir = $conf['rrds'];
    $rrd_escaped = str_replace('/', '\/', $rrd_dir);
    $cmd = "find " . escapeshellarg($rrd_dir) . " -type d | grep -v __SummaryInfo__ | sed -e 's/^${rrd_escaped}\///'";
    $l = explode( "\n", `$cmd` );
    $clusters = array();
    $hosts = array();
    foreach ($l as $v) {
      if ($v == $rrd_dir) {
        continue; // skip base directory
      }
      if (strpos($v, "/") === false) {
        continue; // skip clusters
      }
      if (strpos($v, ";") !== false) {
        continue; // skip weird invalid directories
      }
      list( $_cluster, $_host ) = str_split( '/', $v );
      $hosts[$_host]['clusters'][] = $_cluster;
      $clusters[$_cluster][] = $_host;
    }
    api_return_ok(array(
        'clusters' => $clusters
      , 'hosts' => $hosts
    ));
    break; // end list
  case 'get':
    retrieve_metrics_cache();
    if ($debug == 1) {
      //print "<pre>"; print_r($metrics); print "</pre>";
      ; //PHPCS
    }
    $r = array('graph' => array());
    $default_reports = array("included_reports" => array(), "excluded_reports" => array());
    if ( is_file($conf['conf_dir'] . "/default.json") ) {
      $default_reports = array_merge($default_reports, json_decode(file_get_contents($conf['conf_dir'] . "/default.json"), TRUE));
    }
    $host_file = $conf['conf_dir'] . "/host_" . $hostname . ".json";
    $override_reports = array("included_reports" => array(), "excluded_reports" => array());
    if ( is_file($host_file) ) {
      $override_reports = array_merge($override_reports, json_decode(file_get_contents($host_file), TRUE));
    }
    // Merge arrays
    $reports["included_reports"] = array_merge( $default_reports["included_reports"], $override_reports["included_reports"]);
    $reports["excluded_reports"] = array_merge($default_reports["excluded_reports"], $override_reports["excluded_reports"]);
    // Remove duplicates
    $reports["included_reports"] = array_unique($reports["included_reports"]);
    $reports["excluded_reports"] = array_unique($reports["excluded_reports"]);
    $additional_cluster_img_html_args = array();
    $additional_cluster_img_html_args['h'] = $hostname;
    $additional_cluster_img_html_args['st'] = $cluster[LOCALTIME];
    $additional_cluster_img_html_args['m'] = $metricname;
    $additional_cluster_img_html_args['r'] = $range;
    $additional_cluster_img_html_args['s'] = $sort;
    if ($jobrange and $jobstart) {
      $additional_cluster_img_html_args['jr'] = $jobrange;
      $additional_cluster_img_html_args['js'] = $jobstart;
    }
    if ($cs) {
      $additional_cluster_img_html_args['cs'] = $cs;
    }
    if ($ce) {
      $additional_cluster_img_html_args['ce'] = $ce;
    }
    if ( isset($conf['zoom_support']) && $conf['zoom_support'] === true ) {
      $additional_cluster_img_html_args['class'] = "cluster_zoomable";
    }
    foreach ( $reports["included_reports"] as $index => $report_name ) {
      if ( ! in_array( $report_name, $reports["excluded_reports"] ) ) {
        $graph = array();
        // Form image URL
        $graph_arguments = $additional_cluster_img_html_args;
        $graph_arguments['z'] = 'medium';
        $graph_arguments['c'] = $cluster_url;
        $graph_arguments['g'] = $report_name;
        $graph['graph_image'] = array (
            'script' => 'graph.php'
          , 'params' => $graph_arguments
        );
        // Form page URL 
        $graph_arguments = $additional_cluster_img_html_args;
        $graph_arguments['z'] = 'large';
        $graph_arguments['c'] = $cluster_url;
        $graph_arguments['g'] = $report_name;
        $graph['graph_page'] = array (
            'script' => 'graph_all_periods.php'
          , 'params' => $graph_arguments
        );
        $graph['graph_url'] = form_image_url ( 'graph.php', $graph_arguments );
        // Add graph 
        $r['graph'][] = $graph;
      } // if ! excluded
    } // end foreach included reports

    // Pull all rrds
    $rrd_cmd = 'ls -1 ' .
      escapeshellarg(
        $conf['rrds'] . DIRECTORY_SEPARATOR . $cluster_url .
        DIRECTORY_SEPARATOR . $hostname . DIRECTORY_SEPARATOR
      ) . "*.rrd";
    $rrds_raw = explode( "\n", `$rrd_cmd` ); 
    foreach ($rrds_raw as $v) {
      $rrd = str_replace(".rrd", "", basename( $v ));
      $size = isset($clustergraphsize) ? $clustergraphsize : 'default';
      $size = $size == 'medium' ? 'default' : $size; // set to 'default' to preserve old behavior

      $graph_arguments = array();
      $graph_arguments['h'] = $hostname;
      $graph_arguments['c'] = $cluster_url;
      $graph_arguments['v'] = $metrics[$cluster_url][VAL];
      $graph_arguments['m'] = $rrd;
      $graph_arguments['r'] = $range;
      $graph_arguments['z'] = $size;
      $graph_arguments['jr'] = $jobrange;
      $graph_arguments['js'] = $jobstart;
      $graph_arguments['st'] = $cluster[LOCALTIME];
      # Adding units to graph 2003 by Jason Smith <smithj4@bnl.gov>.
      if ($v['UNITS']) {
        $graph_arguments['vl'] = $metrics[$cluster_url]['UNITS'];
      }
      if (isset($v['TITLE'])) {
        $graph_arguments['ti'] = $metrics[$cluster_url]['TITLE'];
      }
      $graph['description'] = isset($metrics[$cluster_url]['DESC']) ? $metrics[$cluster_url]['DESC'] : '';
      $graph['title'] = isset($metrics[$cluster_url]['TITLE']) ? $metrics[$cluster_url]['TITLE'] : $rrd;

      # Setup an array of groups that can be used for sorting in group view
      if ( isset($metrics[$name]['GROUP']) ) {
        $groups = $metrics[$name]['GROUP'];
      } else {
        $groups = array("");
      }
      $graph['graph_image'] = array (
          'script' => 'graph.php'
        , 'params' => $graph_arguments
      );
      $graph['graph_url'] = form_image_url ( 'graph.php', $graph_arguments );
      $r['graph'][] = $graph;
    } // end foreach metrics
    if ($debug) { print "<pre>";
print_r($r);
die("</pre>"); }
    api_return_ok($r);
    break; // end get

  default:
    api_return_error("Invalid action.");
    break; // bad action

} // end case action

?>
