<?php

$debug = 0;

# One of the bottlenecks is that to get individual metrics we query gmond which
# returns every single host and all the metrics. If you have lots of hosts and lots of 
# checks this may be quite heavy so you may want to cache data
define("CACHEDATA", 1);
define("CACHEFILE",     $ganglia_dir . "/conf/ganglia_metrics.cache");
define("CACHETIME",     120); // How long to cache the data in seconds

if(CACHEDATA == 1 && file_exists(CACHEFILE)){
        // check for the cached file
        // snag it and return it if it is still fresh
        $time_diff = time() - filemtime(CACHEFILE);
        $expires_in = CACHETIME - $time_diff;
        if( $time_diff < CACHETIME){
                if ( $debug == 1 ) {
                  echo("DEBUG: Fetching data from cache. Expires in " . $expires_in . " seconds.\n");
                }
                $index_array = unserialize(file_get_contents(CACHEFILE));
        }
}

if ( ! isset($index_array) ) {

  if ( $debug == 1 ) {
                  echo("DEBUG: Querying GMond for new data\n");
  }
  include_once "$ganglia_dir/conf.php";
  # Set up for cluster summary
  $context = "cluster";
  include_once "$ganglia_dir/functions.php";
  include_once "$ganglia_dir/ganglia.php";
  include_once "$ganglia_dir/get_ganglia.php";
  # Put the serialized metrics into a file
  $index_array['hosts'] = array_keys($metrics);
  foreach ( $metrics as $host => $host_metrics ) {
    foreach ( $host_metrics as $metric_name => $value ) {
      $index_array['metrics'][$metric_name][] = $host;
    }
  }

  # Get host cluster location
  foreach ( $metrics as $host => $host_metrics ) {
     $index_array['cluster'][$host] = $host_metrics['location']['VAL'];
  }

  file_put_contents(CACHEFILE, serialize($index_array));

}

?>
