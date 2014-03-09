<?php

    if (!isset($debug)) { $debug = 0; }

    $cmodule = !empty($conf['cachemodule']) ? $conf['cachemodule'] : 'Json';

    include_once dirname(__FILE__) . "/Cache/Driver_${cmodule}.php";

    if ($conf['cachedata'] == 1 && g_cache_exists()) {
        // check for the cached file
        // snag it and return it if it is still fresh
        $cache_age = g_cache_expire();
        if( $cache_age <= $conf['cachetime']){
                if ( $debug == 1 ) {
                  echo("DEBUG: Fetching data from cache. Expires in " . $conf['cachetime'] . " seconds.\n");
                }
                $index_array = g_cache_deserialize($index);
        }
    }

    if (empty($index_array)) {

        if ( $debug == 1 ) {
            echo("DEBUG: Querying GMond for new data\n");
        }
        // Set up for cluster summary
        include_once $conf['gweb_root'] . "/functions.php";
        include_once $conf['gweb_root'] . "/ganglia.php";
        $GLOBALS['context'] = "index_array";
        include $conf['gweb_root'] . "/get_ganglia.php";

        // only save if the result looks good
        if (count($index_array) > 0) {
          $index_array['hosts'] = array_keys($index_array['cluster']);
          g_cache_serialize($index_array);
        }
    }

?>
