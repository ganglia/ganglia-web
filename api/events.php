<?php

////////////////////////////////////////////////////////////////////////////////////
// This is the Events API. At this time it simply appends to the Events JSON
// You need to supply at a minimum following GET variables 
//    start_time => this is freeform as we'll see the PHP strtotime function to 
//       convert to unix time stamp. You can also specify now 
//    description => Event description
//    host_regex => Host regular expression ie. web or web-0[2,4,5]
////////////////////////////////////////////////////////////////////////////////////
// Make timestamp, description and host_regex have been supplied before proceeding
header("Content-Type: text/plain");

$conf['ganglia_dir'] = dirname(dirname(__FILE__));

include_once $conf['ganglia_dir'] . "/eval_conf.php";
include_once $conf['ganglia_dir'] . "/functions.php";
include_once $conf['ganglia_dir'] . "/lib/common_api.php";

if ( ! $conf['overlay_events'] ) {
  api_return_error( "Events API is DISABLED. Please set \$conf['overlay_events'] = true to enable." );
}

# If events_auth_token is specified in conf.php use that.
if ( isset($conf['events_auth_token']) ) {
   if ( ! ( isset($_GET['token']) && $conf['events_auth_token'] == $_GET['token'] ) ) {
      api_return_error( "Error: Events Auth Token is invalid. Please check token=" );
   }
}

if ( !isset($_GET['action']) ) {
  api_return_error( "Error: You need to specify an action at a minimum" );
}

switch ( $_GET['action'] ) {
 
  case "add":
    if ( ! isset($_GET['start_time']) || ! isset($_GET['summary']) || ! isset($_GET['host_regex']) ) {
      api_return_error( "Error: You need to supply start_time, summary, host_regex at a minimum" );
    }

    // If the time is now just insert the current time stamp. Otherwise use strtotime
    // to convert
    if ( $_GET['start_time'] == "now" )
      $start_time = time();
    else if ( is_numeric($_GET['start_time']) ) 
      $start_time = $_GET['start_time'];
    else 
      $start_time = strtotime($_GET['start_time']);

    $grid = isset($_GET['grid']) ? $_GET['grid'] : "*";
    $cluster = isset($_GET['cluster']) ? $_GET['cluster'] : "*";
    $description = isset($_GET['description']) ? $_GET['description'] : "";
    // Generate a unique event ID. This is so we can reference it later
    $event_id = uniqid();

    $event = array( "event_id" => $event_id, "start_time" => $start_time, "summary" => $_GET['summary'],
      "grid" => $grid, "cluster" => $cluster, "host_regex" => $_GET['host_regex'],
      );

    if ( isset($_GET['end_time']) )
      $event['end_time'] = $_GET['end_time'] == "now" ? time() : strtotime($_GET['end_time']);

    $message = ganglia_events_add( $event );
    break;

  case "edit":
    $message = ganglia_event_modify( $_GET );
    break;

  case "remove":
  case "delete":

    $events_array = ganglia_events_get();

    $event_found = 0;
    if ( isset($_GET['event_id']) ) {
      foreach ( $events_array as $key => $event ) {
	if ( $event['event_id'] == $_GET['event_id'] ) {
	  $event_found = 1;
	} else {
	  $new_events_array[] = $event;
	}

      } // end of foreach ( $events_array as $key => $event

      if ( $event_found == 1 ) {

	$json = json_encode($new_events_array);

	if ( file_put_contents($conf['overlay_events_file'], $json) === FALSE ) {
          api_return_error( "Can't write to file " . $conf['overlay_events_file'] . ". Perhaps permissions are wrong." );
	} else {
	  $message = array( "status" => "ok", "message" => "Event ID " . $event_id . " removed successfully" );
	}

      } else {
	  api_return_error( "Event ID ". $event_id . " not found" );
      }
      
      unset($new_events_array);

    } else {
      api_return_error( "No event_id has been supplied." );
    }

    break;

  default:

    api_return_error( "No valid action specified" );
    break;

} // end of switch ( $_GET['action'] ) {

print json_encode($message);

?>
