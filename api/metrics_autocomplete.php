<?php

$conf['gweb_root'] = dirname(dirname(__FILE__));

include_once $conf['gweb_root'] . "/eval_conf.php";
include_once $conf['gweb_root'] . "/lib/common_api.php";
include_once $conf['gweb_root'] . "/functions.php";

global $metrics;

if($conf['nagios_cache_enabled'] && file_exists($conf['nagios_cache_file'])){
    	// check for the cached file
    	// snag it and return it if it is still fresh
	$time_diff = time() - filemtime($conf['nagios_cache_file']);
	$expires_in = $conf['nagios_cache_time'] - $time_diff;
  if( $time_diff < $conf['nagios_cache_time']){
    if ( $debug == 1 ) {
      error_log("DEBUG: Fetching data from cache. Expires in " . $expires_in . " seconds.\n");
    }
    $metrics = unserialize(file_get_contents($conf['nagios_cache_file']));
  } else {
    ganglia_cache_metrics();
  }
} else {
  ganglia_cache_metrics();
}

if ( ! is_array( $metrics ) ) {
  if ( $debug == 1 ) {
    error_log("DEBUG: Querying GMond for new data\n");
  }
  $context = "cluster";
  include_once $conf['gweb_root'] . "/ganglia.php";
  include_once $conf['gweb_root'] . "/get_ganglia.php";
  # Massage the metrics to minimize the cache file by caching only attributes
  # we care about
  foreach ( $metrics as $mhost => $host_metrics ) {
    foreach ( $host_metrics as $name => $attributes ) {
    	$new_metrics[$mhost][$name]['VAL'] = $metrics[$mhost][$name]['VAL'];
	if ( isset($metrics[$mhost][$name]['UNITS']) ) 
    	$new_metrics[$mhost][$name]['UNITS'] = $metrics[$mhost][$name]['UNITS'];
    }
  }
  unset($metrics);
  file_put_contents($conf['nagios_cache_file'], serialize($new_metrics));
  $metrics = $new_metrics;  
  unset($new_metrics);
}

if ( isset($_GET['term']) ) {
    $term = $_GET['term'];
    if (count($metrics)) {
      foreach ($metrics as $firsthost => $bar) {
        foreach ($metrics[$firsthost] as $m => $foo)
          $context_metrics[$m] = $m;
      }
      foreach ($reports as $r => $foo)
        $context_metrics[] = $r;
    }
    if (is_array($context_metrics)) {
      $picker_metrics = array();
      # Find all the optional reports
      if ($handle = opendir($conf['gweb_root'] . '/graph.d')) {
        // If we are using RRDtool reports can be json or PHP suffixes
        if ( $conf['graph_engine'] == "rrdtool" )
          $report_suffix = "php|json";
        else
          $report_suffix = "json";

        while (false !== ($file = readdir($handle))) {
          if ( preg_match("/(.*)(_report)\.(" . $report_suffix .")/", $file, $out) ) {
            if ( ! in_array($out[1] . "_report", $context_metrics) )
              $context_metrics[] = $out[1] . "_report";
          }
        }
        closedir($handle);
      }
      sort($context_metrics);
      $c = 0;
      foreach ($context_metrics as $key) {
        $url = rawurlencode($key);
        if (stripos($key, $term) !== false) {
          if ($c > 30) { break; }
          $picker_metrics[] = array(
            'value' => $url,
            'label' => $key,
            'id' => $key
          );
          $c++;
        }
      }
    }
    api_return_ok($picker_metrics);
} else {
    api_return_error("No valid search provided");
}

?>
