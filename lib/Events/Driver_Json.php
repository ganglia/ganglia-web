<?php

$conf['gweb_root'] = dirname(dirname(dirname(__FILE__)));

include_once $conf['gweb_root'] . "/eval_conf.php";
include_once $conf['gweb_root'] . "/lib/common_api.php";

//////////////////////////////////////////////////////////////////////////////
// Add an event to the JSON event array
//////////////////////////////////////////////////////////////////////////////
function ganglia_events_add( $event ) {
  global $conf;
  $events_array = ganglia_events_get();
  $events_array[] = $event;
  $json = json_encode($events_array);
  if ( file_put_contents($conf['overlay_events_file'], $json) === FALSE ) {
    api_return_error( "Can't write to " . 
		      $conf['overlay_events_file'] . 
		      ". Please check permissions." );
  } else {
    $message = array( "status" => "ok", "event_id" => $event['event_id']);
  }
  return $message;
} // end method ganglia_events_add

//////////////////////////////////////////////////////////////////////////////
// Gets a list of all events that overlap with a specified time range
//////////////////////////////////////////////////////////////////////////////
function ganglia_events_get( $start = NULL, $end = NULL ) {
  global $conf;
  $events_json = file_get_contents($conf['overlay_events_file']);
  $orig_events_array = json_decode($events_json, TRUE);
  
  // Save some time, pass back if no values given
  if ( $start == NULL && $end == NULL ) {
    return $orig_events_array;
  }

  $events_array = array();
  foreach ($orig_events_array as $k => $evt) {
    if ($evt['end_time'] != NULL) { // Duration event
      if ($start == NULL) {
        if ($evt['start_time'] <= $end && $evt['end_time'] >= $end)
	  $events_array[] = $evt;
      } else if ($end == NULL) {
        if ($evt['start_time'] <= $start && $evt['end_time'] >= $start)
	  $events_array[] = $evt;
      } else {
        if ($evt['end_time'] >= $start && $evt['start_time'] <= $end)
	  $events_array[] = $evt;
      }
    } else { // Instantaneous event
      if ($start == NULL && $evt['start_time'] == $end)
	$events_array[] = $evt;
      else if ($end == NULL && $evt['start_time'] == $start)
	$events_array[] = $evt;
      else if ($evt['start_time'] >= $start && $evt['start_time'] <= $end)
	$events_array[] = $evt;
    }
  }
  return $events_array;
} // end method ganglia_events_get

function ganglia_event_delete( $event_id ) {
  global $conf;
  $orig_events_array = ganglia_events_get();
  $events_array = array();
  $event_found = 0;
  foreach ( $orig_events_array as $k => $v ) {
    if ( $v['event_id'] != $event_id ) {
      $events_array[] = $v;
    } else {
      $event_found = 1;
    }
  }
  if ( $event_found == 1 ) {
    $json = json_encode($events_array);
    if ( file_put_contents($conf['overlay_events_file'], $json) === FALSE ) {
      api_return_error( "Can't write to " . 
			$conf['overlay_events_file'] . 
			". Please check permissions." );
    } else {
        $message = array( "status" => "ok", "message" => "Event ID " . $event_id . " deleted successfully" );
        return $message;
    }
  }
  api_return_error( "Event ID ". $event_id . " not found" );
  return NULL; // never reached
} // end method ganglia_event_delete

function ganglia_event_modify( $event ) {
  global $conf;
  $event_found = 0;
  $events_array = ganglia_events_get();
  $new_events_array = array();
  
  if (!isset($event['event_id'])) {
    api_return_error( "Event ID not found" );
  } // isset event_id
  
  foreach ( $events_array as $k => $e ) {
    if ( $e['event_id'] == $event['event_id'] ) {
      $event_found = 1;
      
      if (isset( $event['start_time'] )) {
        if ( $event['start_time'] == "now" ) {
          $e['start_time'] = time();
        } else if ( is_numeric($event['start_time']) ) {
          $e['start_time'] = $event['start_time'];
        } else {
          $e['start_time'] = strtotime($event['start_time']);
        }
      } // end isset start_time
      
      foreach(array('cluster', 
		    'description', 
		    'summary', 
		    'grid', 
		    'host_regex') as $k) {
        if (isset( $event[$k] )) {
          $e[$k] = $event[$k];
        }
      } // end foreach
      
      if ( isset($event['end_time']) ) {
        $e['end_time'] = $event['end_time'] == "now" ? time() : strtotime($event['end_time']);
      } // end isset end_time
    } // if event_id
    
    // Add either original or modified event back in
    $new_events_array[] = $e;
  } // foreach events array
  if ( $event_found == 1 ) {
    $json = json_encode($new_events_array);
    if ( file_put_contents($conf['overlay_events_file'], $json) === FALSE ) {
      api_return_error( "Can't write to file " . 
			$conf['overlay_events_file'] . 
			". Perhaps permissions are wrong." );
    } else {
      $message = array( "status" => "ok",
			"message" => "Event ID " . $event_id . " modified successfully" );
    }
  } // end if event_found

  return $message;
} // end method ganglia_event_modify

?>
