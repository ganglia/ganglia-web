<?php

function g_cache_exists() {
	global $conf;
	return file_exists( $conf['cachefile'] );
} // end function g_cache_exists

function g_cache_serialize($data) {
	global $conf;
	file_put_contents($conf['cachefile'], json_encode($data));
	file_put_contents($conf['cachefile'] . "_cluster_data", json_encode($data["cluster"]));
	file_put_contents($conf['cachefile'] . "_host_list", json_encode($data["hosts"]));
	file_put_contents($conf['cachefile'] . "_metric_list", json_encode(array_keys($data["metrics"])));
} // end function g_cache_serialize

function g_cache_deserialize($index) {
	global $conf;
        $index_array = array();
	
        switch ( $index ) {

           case "hosts_and_metrics":
	       $index_array["cluster"] = json_decode(file_get_contents($conf['cachefile'] . "_cluster_data"), TRUE);
	       $index_array["hosts"] = json_decode(file_get_contents($conf['cachefile'] . "_host_list"), TRUE);
	       $index_array["metrics"] = json_decode(file_get_contents($conf['cachefile'] . "_metric_list"), TRUE);
               break;
        
           case "metric_list":
	       $index_array["metric_list"] = json_decode(file_get_contents($conf['cachefile'] . "_" . $index), TRUE);
               break;

           default:
	       $index_array = json_decode(file_get_contents($conf['cachefile']), TRUE);
             
        }
	return $index_array;
} // end function g_cache_deserialize

function g_cache_expire () {
	global $conf;
	$time_diff = time() - filemtime($conf['cachefile']);
	return $time_diff;
} // end function g_cache_expire

?>
