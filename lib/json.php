<?php

if (!function_exists('json_encode')) {

	// Shim this if json_encode doesn't exist

	include_once( 'Services/JSON.php' );

	function json_encode( $s ) {
		$j = new Services_JSON( SERVICES_JSON_LOOSE_TYPE );
		return $j->encode( $s );
	}

	function json_decode( $s ) {
		$j = new Services_JSON( SERVICES_JSON_LOOSE_TYPE );
		return $j->decode( $s );
	}

}

?>
