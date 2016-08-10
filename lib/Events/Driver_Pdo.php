<?php

$conf['gweb_root'] = dirname(dirname(dirname(__FILE__)));

include_once $conf['gweb_root'] . "/eval_conf.php";
include_once $conf['gweb_root'] . "/lib/common_api.php";

//////////////////////////////////////////////////////////////////////////////
// Add an event to the JSON event array
//////////////////////////////////////////////////////////////////////////////
function ganglia_events_add( $event ) {
  global $conf;
  try {
  $db = new PDO( $conf['overlay_events_dsn'] );
  $sql = "INSERT INTO overlay_events ( description, summary, grid, cluster, host_regex, start_time, end_time ) VALUES ( " .
    ( isset($event['description']) ? $db->quote( $event['description'], PDO::PARAM_STR ) : "NULL" ) . "," .
    ( isset($event['summary']) ? $db->quote( $event['summary'], PDO::PARAM_STR ) : "NULL" ) . "," .
    ( isset($event['grid']) ? $db->quote( $event['grid'], PDO::PARAM_STR ) : "NULL" ) . "," .
    ( isset($event['cluster']) ? $db->quote( $event['cluster'], PDO::PARAM_STR ) : "NULL" ) . "," .
    ( isset($event['host_regex']) ? $db->quote( $event['host_regex'], PDO::PARAM_STR ) : "NULL" ) . "," .
    ( isset($event['start_time']) ? $db->quote( $event['start_time'], PDO::PARAM_INT ) : "NULL" ) . "," .
    ( isset($event['end_time']) ? $db->quote( $event['end_time'], PDO::PARAM_INT ) : "NULL" ) .
    " ) ;";
  $result = $db->exec( $sql );
  $event_id = $db->lastInsertID( 'event_id' );
  $event_id = strval($event_id);
  $message = array( "status" => "ok", "event_id" => $event_id );
  return $message;
  } catch (PDOException $e) {
  api_return_error($e->getMessage());
  }
} // end method ganglia_events_add

//////////////////////////////////////////////////////////////////////////////
// Gets a list of all events in an optional time range
//////////////////////////////////////////////////////////////////////////////
function ganglia_events_get( $start = NULL, $end = NULL ) {
  global $conf;
  try {
  $db = new PDO( $conf['overlay_events_dsn'] );

  $sql = "SELECT * FROM overlay_events ";
  if ( $start != NULL || $end != NULL ) {
    $sql .= " WHERE ";
    $clauses = array();
    if ( $start != NULL ) {
      $clauses[] = "start_time >= " . $db->quote( $start, PDO::PARAM_INT );
    }
    if ( $end != NULL ) {
      $clauses[] = "start_time <= " . $db->quote( $end, PDO::PARAM_INT );
    }
    $sql .= implode(' AND ', $clauses);
  }
  $sql .= " ORDER BY start_time, event_id";

  $result = $db->query( $sql );

  $events_array = array();
  while ( ( $row = $result->fetch(PDO::FETCH_ASSOC) ) ) {
    $events_array[] = $row;
  }

  return $events_array;
  } catch (PDOException $e) {
  api_return_error($e->getMessage());
  }
} // end method ganglia_events_get

function ganglia_event_delete( $event_id ) {
  global $conf;

  try {
  $db = new PDO( $conf['overlay_events_dsn'] );

  $sql = "DELETE FROM overlay_events WHERE event_id = " . $db->quote( $event_id, PDO::PARAM_INT );
  $result = $db->query( $sql );

  $message = array( "status" => "ok", "event_id" => $event_id );

  return $message;
  } catch (PDOException $e) {
  api_return_error($e->getMessage());
  }
} // end method ganglia_event_delete

function ganglia_event_modify( $event ) {
  global $conf;

  if ( !isset( $event['event_id'] ) ) {
    api_return_error( "event_id not set" );
  }

  try {
  $db = new PDO( $conf['overlay_events_dsn'] );

  $clauses = array();

  if (isset( $event['start_time'] )) {
    if ( $event['start_time'] == "now" ) {
      $start_time = time();
    } else if ( is_numeric($event['start_time']) ) {
      $start_time = $event['start_time'];
    } else {
      $start_time = strtotime($event['start_time']);
    }
    $clauses[] = "start_time = " . $db->quote( $start_time, PDO::PARAM_INT );
  } // end isset start_time

  foreach(array('cluster', 'description', 'summary', 'grid', 'host_regex') as $k) {
    if (isset( $event[$k] )) {
      $clauses[] = "${k} = " . $db->quote( $event[$k], 'text' );
    }
  } // end foreach

  if ( isset($event['end_time']) ) {
    $end_time = $event['end_time'] == "now" ? time() : strtotime($event['end_time']);
    $clauses[] = "end_time = " . $db->quote( $end_time, PDO::PARAM_INT );
  } // end isset end_time

  $sql = "UPDATE overlay_events SET " . implode( ",", $clauses ) . " WHERE event_id = " . $db->quote( $event['event_id'], PDO::PARAM_INT );
  $result = $db->exec( $sql );
  $message = array( "status" => "ok", "event_id" => $event['event_id'] );
  return $message;
  } catch (PDOException $e) {
  api_return_error($e->getMessage());
  }
} // end method ganglia_event_modify

?>
