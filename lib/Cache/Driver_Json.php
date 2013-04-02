<?php

function g_cache_exists() {
	global $conf;
	return file_exists( $conf['cachefile'] );
} // end function g_cache_exists

function g_cache_serialize($data) {
	global $conf;
	file_put_contents($conf['cachefile'], serialize($data));
} // end function g_cache_serialize

function g_cache_deserialize() {
	global $conf;
	$index_array = unserialize(file_get_contents($conf['cachefile']));
	return $index_array;
} // end function g_cache_deserialize

function g_cache_expire () {
	global $conf;
	$time_diff = time() - filemtime($conf['cachefile']);
	return $time_diff;
} // end function g_cache_expire

?>
