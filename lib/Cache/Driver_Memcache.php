<?php
// vim: tabstop=2:softtabstop=2:shiftwidth=2:noexpandtab

function g_cache_exists() {
	$mc = g_get_memcache();
	return $mc->get( 'ganglia_cache_' . gethostname() ) !== FALSE;
} // end function g_cache_exists

function g_cache_serialize($data) {
	global $conf;
	$mc = g_get_memcache();
	$mc->set( 'ganglia_cache_' . gethostname(), $data );
	$mc->set( 'ganglia_cache_timestamp_' . gethostname(), time() );
} // end function g_cache_serialize

function g_cache_deserialize() {
	global $conf;
	$mc = g_get_memcache();
	$index_array = $mc->get( 'ganglia_cache_' . gethostname() );
	return $index_array;
} // end function g_cache_deserialize

function g_cache_expire () {
	global $conf;
	$mc = g_get_memcache();
	return time() - $mc->get( 'ganglia_cache_timestamp_' . gethostname() );
} // end function g_cache_expire

function g_get_memcache() {
	global $conf;
	if (!$GLOBALS['__memcached_pool']) {
		$GLOBALS['__memcached_pool'] = new Memcached();
		$GLOBALS['__memcached_pool']->setOption(Memcached::OPT_DISTRIBUTION, Memcached::DISTRIBUTION_CONSISTENT);
		foreach ($conf['memcached_servers'] as $server) {
			list($host, $port) = explode(':', $server);
			$GLOBALS['__memcached_pool']->addServer( $host, (int)$port );
		}
	}
	return $GLOBALS['__memcached_pool'];
} // end function g_get_memcache

?>
