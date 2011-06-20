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
if ( ! isset($_GET['start_time']) || ! isset($_GET['summary']) || ! isset($_GET['host_regex']) || !isset($_GET['action']) ) {
  print "Error: You need to supply start_time, summary, host_regex and action at a minimum";
  exit(1);
}

$conf['ganglia_dir'] = dirname(dirname(__FILE__));

include_once $conf['ganglia_dir'] . "/eval_conf.php";

$events_json = file_get_contents($conf['overlay_events_file']);

$events_array = json_decode($events_json, TRUE);


switch ( $_GET['action'] ) {
 
  case "add":

    // If the time is now just insert the current time stamp. Otherwise use strtotime
    // to convert
    $start_time = $_GET['start_time'] == "now" ? time() : strtotime($_GET['start_time']);

    $grid = isset($_GET['grid']) ? $_GET['grid'] : "*";
    $cluster = isset($_GET['cluster']) ? $_GET['cluster'] : "*";
    $description = isset($_GET['description']) ? $_GET['description'] : "";

    $events_array[] = array( "start_time" => $start_time, "summary" => $_GET['summary'],
      "grid" => $grid, "cluster" => $cluster, "host_regex" => $_GET['host_regex']);

    $json = json_encode($events_array);

    if ( file_put_contents($conf['overlay_events_file'], $json) === FALSE ) {
      print "Error: Can't write to file " . $conf['overlay_events_file'] . ". Perhaps permissions are wrong.";
    } else {
      print "Events file has been updated successfully.";
    } 

    break;

  default:

    print "No valid action specified";
    break;

} // end of switch ( $_GET['action'] ) {
?>