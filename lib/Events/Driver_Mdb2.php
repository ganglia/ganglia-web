<?php

$conf['gweb_root'] = dirname(dirname(dirname(__FILE__)));

include_once $conf['gweb_root'] . "/eval_conf.php";
include_once $conf['gweb_root'] . "/lib/common_api.php";

// Load MDB2 driver and dependencies
require 'DB.php';
require 'MDB2.php';

//////////////////////////////////////////////////////////////////////////////
// Add an event to the JSON event array
//////////////////////////////////////////////////////////////////////////////
function ganglia_events_add( $event ) {
  global $conf;
  $db =& MDB2::factory( $conf['overlay_events_dsn'] );
  if (DB::isError($db)) { api_return_error($db->getMessage()); }
  $sql = "INSERT INTO overlay_events ( description, summary, grid, cluster, host_regex, start_time, end_time ) VALUES ( " .
    ( isset($event['description']) ? $db->quote( $event['description'], 'text' ) : "NULL" ) . "," .
    ( isset($event['summary']) ? $db->quote( $event['summary'], 'text' ) : "NULL" ) . "," .
    ( isset($event['grid']) ? $db->quote( $event['grid'], 'text' ) : "NULL" ) . "," .
    ( isset($event['cluster']) ? $db->quote( $event['cluster'], 'text' ) : "NULL" ) . "," .
    ( isset($event['host_regex']) ? $db->quote( $event['host_regex'], 'text' ) : "NULL" ) . "," .
    ( isset($event['start_time']) ? $db->quote( $event['start_time'], 'integer' ) : "NULL" ) . "," .
    ( isset($event['end_time']) ? $db->quote( $event['end_time'], 'integer' ) : "NULL" ) .
    " ) ;";
  $result =& $db->exec( $sql );
  if (PEAR::isError($result)) {
    api_return_error( $result->getMessage());
  }
  $event_id = $db->lastInsertID( 'overlay_events', 'event_id' );
  if (PEAR::isError($event_id)) {
    api_return_error( $event_id->getMessage());
  }
  $event_id = strval($event_id);
  $message = array( "status" => "ok", "event_id" => $event_id );
  return $message;
} // end method ganglia_events_add

//////////////////////////////////////////////////////////////////////////////
// Gets a list of all events in an optional time range
//////////////////////////////////////////////////////////////////////////////
function ganglia_events_get( $start = NULL, $end = NULL ) {
  global $conf;
  $db =& MDB2::factory( $conf['overlay_events_dsn'] );
  if (DB::isError($db)) { api_return_error($db->getMessage()); }

  $sql = "SELECT * FROM overlay_events ";
  if ( $start != NULL || $end != NULL ) {
    $sql .= " WHERE ";
    $clauses = array();
    if ( $start != NULL ) {
      $clauses[] = "start_time >= " . $db->quote( $start, 'integer' );
    }
    if ( $end != NULL ) {
      $clauses[] = "start_time <= " . $db->quote( $end, 'integer' );
    }
    $sql .= implode(' AND ', $clauses);
  }
  $sql .= " ORDER BY start_time, event_id";

  $result =& $db->query( $sql );
  if (PEAR::isError($result)) {
    api_return_error( $result->getMessage());
  }

  $events_array = array();
  while ( ( $row = $result->fetchRow( MDB2_FETCHMODE_ASSOC ) ) ) {
    $events_array[] = $row;
  }

  return $events_array;
} // end method ganglia_events_get

function ganglia_event_delete( $event_id ) {
  global $conf;

  $db =& MDB2::factory( $conf['overlay_events_dsn'] );
  if (DB::isError($db)) { api_return_error($db->getMessage()); }

  $sql = "DELETE FROM overlay_events WHERE event_id = " . $db->quote( $event_id, 'integer' );
  $result =& $db->query( $sql );
  if (PEAR::isError($result)) {
    api_return_error( $result->getMessage());
  }

  $message = array( "status" => "ok", "event_id" => $event_id );

  return $message;
} // end method ganglia_event_delete

function ganglia_event_modify( $event ) {
  global $conf;

  if ( !isset( $event['event_id'] ) ) {
    api_return_error( "event_id not set" );
  }

  $db =& MDB2::factory( $conf['overlay_events_dsn'] );
  if (DB::isError($db)) { api_return_error($db->getMessage()); }

  $clauses = array();

  if (isset( $event['start_time'] )) {
    if ( $event['start_time'] == "now" ) {
      $start_time = time();
    } else if ( is_numeric($event['start_time']) ) {
      $start_time = $event['start_time'];
    } else {
      $start_time = strtotime($event['start_time']);
    }
    $clauses[] = "start_time = " . $db->quote( $start_time, 'integer' );
  } // end isset start_time

  foreach(array('cluster', 'description', 'summary', 'grid', 'host_regex') as $k) {
    if (isset( $event[$k] )) {
      $clauses[] = "${k} = " . $db->quote( $event[$k], 'text' );
    }
  } // end foreach

  if ( isset($event['end_time']) ) {
    $end_time = $event['end_time'] == "now" ? time() : strtotime($event['end_time']);
    $clauses[] = "end_time = " . $db->quote( $end_time, 'integer' );
  } // end isset end_time

  $sql = "UPDATE overlay_events SET " . implode( ",", $clauses ) . " WHERE event_id = " . $db->quote( $event['event_id'], 'integer' );
  $result =& $db->exec( $sql );
  if (PEAR::isError($result)) {
    api_return_error( $result->getMessage());
  }
  $message = array( "status" => "ok", "event_id" => $event['event_id'] );
  return $message;
} // end method ganglia_event_modify

?>
