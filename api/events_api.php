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

if ( ! isset($_GET['start_time']) || ! isset($_GET['summary']) || ! isset($_GET['host_regex']) || !isset($_GET['action']) ) {
  print "Error: You need to supply start_time, summary, host_regex and action at a minimum";
  exit(1);
}

$conf['ganglia_dir'] = dirname(dirname(__FILE__));

include_once $conf['ganglia_dir'] . "/eval_conf.php";

if ( ! $conf['overlay_events'] ) {
  print "Events API is DISABLED. Please set \$conf['overlay_events'] = true to enable.";
  exit(1);
}


$events_json = file_get_contents($conf['overlay_events_file']);

$events_array = json_decode($events_json, TRUE);


switch ( $_GET['action'] ) {
 
  case "add":

    // If the time is now just insert the current time stamp. Otherwise use strtotime
    // to convert
    if ( $_GET['start_time'] == "now" )
      $start_time = time();
    else if ( is_numeric($_GET['start_time']) ) 
      $start_time = $_GET['start_time'];
    else 
      $start_time = strtotime($_GET['start_time']);

//    $start_time = $_GET['start_time'] == "now" ? time() : strtotime($_GET['start_time']);

    $grid = isset($_GET['grid']) ? $_GET['grid'] : "*";
    $cluster = isset($_GET['cluster']) ? $_GET['cluster'] : "*";
    $description = isset($_GET['description']) ? $_GET['description'] : "";

    $event = array( "start_time" => $start_time, "summary" => $_GET['summary'],
      "grid" => $grid, "cluster" => $cluster, "host_regex" => $_GET['host_regex']);

    if ( isset($_GET['end_time']) )
      $event['end_time'] = $_GET['end_time'] == "now" ? time() : strtotime($_GET['end_time']);
  
    $events_array[] = $event;

    $json = json_encode($events_array);

    if ( file_put_contents($conf['overlay_events_file'], $json) === FALSE ) {
      $message = array( "status" => "error", "message" => "Can't write to file " . $conf['overlay_events_file'] . ". Perhaps permissions are wrong.");
    } else {
      $message = array( "status" => "ok");
    }

    print json_encode($message);

    break;

  default:

    print "No valid action specified";
    break;

} // end of switch ( $_GET['action'] ) {
?>
