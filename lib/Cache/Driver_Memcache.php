<?php

function g_cache_exists() {
	$mc = g_get_memcache();
	return $mc->get( 'ganglia_cache_' . gethostname() ) !== FALSE;
} // end function g_cache_exists

function g_cache_serialize($data) {
	global $conf;
	$mc = g_get_memcache();
	$mc->set( 'ganglia_cache_' . gethostname() , $data );
	$mc->set( 'ganglia_cache_timestamp_' . gethostname(), time() );
} // end function g_cache_serialize

function g_cache_deserialize() {
	global $conf;
	$index_array = $mc->get( 'ganglia_cache_' . gethostname() );
	return $index_array;
} // end function g_cache_deserialize

function g_cache_expire () {
	global $conf;
	
	return time() - $mc->set( 'ganglia_cache_timestamp_' . gethostname() ) <= time() );
} // end function g_cache_expire

function g_get_memcache() {
	global $conf;
        if (!$GLOBALS['__memcached_pool']) {
                $GLOBALS['__memcached_pool'] = new Memcached();
                foreach ($conf['memcached_servers'] AS $server) {
                        list($host, $port) = explode(':', $server);
                        $GLOBALS['__memcached_pool']->addServer( $host, (int)$port );
                }
        }
	return $GLOBALS['__memcached_pool'];
} // end function g_get_memcache

?>
