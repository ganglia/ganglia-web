<?php

function g_cache_exists() {
	global $conf;
	return file_exists( $conf['cachefile'] );
} // end function g_cache_exists

function g_cache_serialize($data) {
	global $conf;
	file_put_contents($conf['cachefile'], serialize($data));
	file_put_contents($conf['cachefile'] . "_cluster_data", serialize($data["cluster"]));	
	file_put_contents($conf['cachefile'] . "_host_list", serialize($data["hosts"]));	
	file_put_contents($conf['cachefile'] . "_metric_list", serialize(array_keys($data["metrics"])));	
} // end function g_cache_serialize

function g_cache_deserialize($index) {
	global $conf;
        $index_array = array();
	
        switch ( $index ) {

           case "hosts_and_metrics":
	       $index_array["cluster"] = unserialize(file_get_contents($conf['cachefile'] . "_cluster_data"));
	       $index_array["hosts"] = unserialize(file_get_contents($conf['cachefile'] . "_host_list"));
	       $index_array["metrics"] = unserialize(file_get_contents($conf['cachefile'] . "_metric_list"));
               break;
        
           case "metric_list":
	       $index_array["metrics"] = unserialize(file_get_contents($conf['cachefile'] . "_" . $index));
               break;

           default:
	       $index_array = unserialize(file_get_contents($conf['cachefile']));
             
        }
	return $index_array;
} // end function g_cache_deserialize

function g_cache_expire () {
	global $conf;
	$time_diff = time() - filemtime($conf['cachefile']);
	return $time_diff;
} // end function g_cache_expire

?>
