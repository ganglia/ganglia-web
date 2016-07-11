<?php

require_once('./eval_conf.php');
require_once('./functions.php');

// Load the metric caching code
//$debug = 1;
retrieve_metrics_cache();

if ( isset($_GET['term']) && $_GET['term'] != "" ) {

  $query = $_GET['term'];
  // Now let's look through metrics.
  foreach ( $index_array['metrics'] as $metric_name => $hosts ) {
    if ( preg_match("/$query/i", $metric_name ) ) {
      foreach ( $hosts as $key => $host_name ) {
        $clusters = $index_array['cluster'][$host_name];
        foreach ($clusters as $cluster_name) {
          $results[] = array( "value" => $cluster_name . "_|_" . $host_name . "_|_" . $metric_name);
        }
      }
    }
  }

}

echo json_encode($results);

?>
