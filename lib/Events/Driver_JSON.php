<?php

$conf['ganglia_dir'] = dirname(dirname(dirname(__FILE__)));

include_once $conf['ganglia_dir'] . "/eval_conf.php";
include_once $conf['ganglia_dir'] . "/lib/common_api.php";

function ganglia_events_add( $event ) {
	$events_array = ganglia_events_get();
	$events_array[] = $event;
	$json = json_encode($events_array);
	if ( file_put_contents($conf['overlay_events_file'], $json) === FALSE ) {
		api_return_error( "Can't write to file " . $conf['overlay_events_file'] . ". Perhaps permissions are wrong." );
	} else {
		$message = array( "status" => "ok", "event_id" => $event_id);
	}
	return $message;
} // end method ganglia_events_add

function ganglia_events_get() {
	global $conf;
	$events_json = file_get_contents($conf['overlay_events_file']);
	$events_array = json_decode($events_json, TRUE);
	return $events_array;
} // end method ganglia_events_get

?>
