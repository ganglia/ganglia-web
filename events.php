<?php
if (!isset($_GET['events_only'])) {
?>
<script>
function refreshOverlayEvent() {
  var d = new Date();
  $.get('events.php?events_only=1&_=' + d.getTime(), function(data) {
    $("#overlay_event_table").html(data);
  });
}

$(function(){
  $( "#add-event-dialog" ).dialog({autoOpen: false,
	                           height: 300,
                                   width: 550,
                                   modal: true});
  $( "#event-start-date" ).datetimepicker({showOn: "button",
      	                                   constrainInput: false,
                                           buttonImage: "img/calendar.gif", 
                                           buttonImageOnly: true});
  $( "#event-end-date" ).datetimepicker({showOn: "button",
                                       	 constrainInput: false,
                                         buttonImage: "img/calendar.gif",
                                         buttonImageOnly: true});
  $('#add-event-button').button();
});

function eventActions(action) {
  $("#event-message").html('<img src="img/spinner.gif">');
  var queryString = "";
  if ($("#event-start-date").val() != "")
    queryString += "&start_time=" + $("#event-start-date").val();
  if ($("#event-end-date").val() != "")
    queryString += "&end_time=" + $("#event-end-date").val();
  if ( $("#host_reg").val() != "" ) {
    queryString += "&host_regex=" + $("#host_regex").val();    
  } else {
    alert("You need to specify Host Regex");
    return false;
  }
  $.get('api/events.php', 
        "action=" + action + 
        "&summary=" + $("#event_summary").val() + queryString, function(data) {
      $("#event-message").html(data);
  });
}
</script>
<div align=center><button onClick='$("#add-event-dialog").dialog("open");' class="minimal-indent">Add an Event</button></div>
<div id=add-event-dialog title="Add Event">
You can specify either start date or end date or both.<p>
<table width=90%>
<form id="event-actions-form">
<tr><td>Event Summary:</td><td><input type="text" name="summary" id="event_summary" size=20></td></tr>
<tr><td>Host Regex:</td><td><input type="text" name="host_regex" id="host_regex" size=20></td></tr>
<tr><td>Start Date:</td><td><input type="text" title="Start Date" name="start_date" id="event-start-date" size=20></td></tr>
<tr><td>End Date:</td><td><input type="text" title="End Date" name="end_date" id="event-end-date" size=20></td></tr>
<tr><td>
<button onclick="eventActions('add');" id="add-event-button">Add</button>
</td><td colspan=2><div id="event-message"></div></td></tr>
</form>
</table>
</div>
<p>
Following is a list of known overlay events<p>


<?php 
} 
?>
<table id="overlay_event_table" width="90%">
<tr>
<th>Start Time</th>
<th>End Time</th>
<th>Summary</th>
<th>Description</th>
<th>Grid</th>
<th>Cluster</th>
<th>Host Regex</th>
</tr>
<?php

function start_time_cmp($ev1, $ev2) {
  $start1 = $ev1['start_time'];
  $start2 = $ev2['start_time'];

  if ($start1 == $start2)
    return 0;

  return ($start1 < $start2) ? 1 : -1;
}


$conf['gweb_root'] = dirname(__FILE__);

include_once $conf['gweb_root'] . "/eval_conf.php";
include_once $conf['gweb_root'] . "/functions.php";
include_once $conf['gweb_root'] . "/lib/common_api.php";

// $events_json = file_get_contents($conf['overlay_events_file']);

// $events_array = json_decode($events_json, TRUE);

$events_array = ganglia_events_get();

if (sizeof($events_array) > 0) {
  usort($events_array, 'start_time_cmp');

  foreach ( $events_array as $id => $event ) {
    $description = isset($event['description']) ? $event['description'] : "";
    $end_time = isset($event['end_time']) ? date("Y/m/d H:i", $event['end_time']) : "";
    print "<tr><td>" . date("Y/m/d H:i", $event['start_time']) . "</td>" .
      "<td>" . $end_time . "</td>" .
      "<td>" . $event['summary'] . "</td>" .
      "<td>" . $description . "</td>" .
      "<td>" . $event['grid'] . "</td>" .
      "<td>" . $event['cluster'] . "</td>" .
      "<td>" . $event['host_regex'] . "</td>" .
      "</tr>";
  }
}
?>
</table>
