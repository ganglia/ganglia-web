<?php
$conf['gweb_root'] = dirname(__FILE__);
include_once $conf['gweb_root'] . "/eval_conf.php";

if (isset($_GET['calendar_events_only'])) {
  $conf['gweb_root'] = dirname(__FILE__);

  include_once $conf['gweb_root'] . "/functions.php";
  include_once $conf['gweb_root'] . "/lib/common_api.php";

  $start = new DateTime($_GET['start']);
  $end = new DateTime($_GET['end']);
  $events = ganglia_events_get($start->getTimestamp(), $end->getTimestamp());
  $cal_events = array();
  foreach ( $events as $id => $event ) {
    $cal_event = array('title' => $event['summary'],
                       'start' => $event['start_time'] * 1000,
                       'end' => isset($event['end_time']) ? $event['end_time'] * 1000 : NULL,
                       'gweb_event_id' => $event['event_id'],
                       'grid' => $event['grid'],
                       'cluster' => $event['cluster'],
                       'host_regex' => $event['host_regex'],
                       'start_time' => $event['start_time'],
                       'description' => $event['description']);

    if (isset($event['end_time']))
      $cal_event['end_time'] = $event['end_time'];

    array_push($cal_events, $cal_event);
  }
  $json = json_encode($cal_events);
  print "$json";
  exit(0);
}

if (!isset($_GET['events_only'])) {
?>
<script>
function refreshOverlayEvent() {
<?php if ($conf['display_events_using_calendar']) { ?>
  $('#calendar').fullCalendar('refetchEvents');
<?php } else { ?>
  var d = new Date();
  $.get('events.php?events_only=1&_=' + d.getTime(), function(data) {
    $('#overlay_event_table').html(data);});
<?php } ?>
}

$(function(){
  $( "#add-event-dialog" ).dialog({height: 250,
                                   width: 500,
                                   autoOpen: false,
	                           position: {
                                     my: 'center top',
	                             at: 'center bottom+30',
                                     of: '#add-an-event-button'
                                   },
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

<?php if ($conf['display_events_using_calendar']) { ?>
  $('#calendar').fullCalendar({
    timezone: 'local',
    editable: false,
    disableDragging: true,
    disableResizing: true,
    eventSources: [
      {
        url: 'events.php',
        data: {
          calendar_events_only: '1'
        }
      }
    ],
    defaultView: 'basicWeek',
    header: {left: 'prev,next,today', 
             center: 'title', 
             right: 'basicDay,basicWeek,month'},
    eventRender: function(event, element) {
        var fmt = "HH:mm:ss";
	console.log("event = " + JSON.stringify(event));
	var start = moment(event.start_time * 1000);
	var end = null;
	if ('end_time' in event) {
	  var end = moment(event.end_time * 1000);
	  if (start.year() != end.year()) {
	    fmt = "MM/dd/yyyy " + fmt;
	  } else {
	    if ((start.date() != end.date()) ||
		(start.month() != end.month()))
	      fmt = "MM/dd " + fmt;
	  }
	}

	var tipText = event.title + 
	  '<br>Start: ' + start.format(fmt);
	if (end)
	  tipText += '<br>End: ' + end.format(fmt);

        element.qtip({
          content : {
            text: tipText
          },
          position: {
            target: 'mouse',
	    adjust: {x: 5, y: 5}
          },
          style: {
            classes: 'ganglia-qtip'
          }
       })
    }
  });
<?php } ?>
});

function parseDateTime(str) {
  var parts = str.split(" ");
  var date = parts[0].split("/");
  var time = parts[1].split(":");
  return new Date(date[2], date[0] - 1, date[1], time[0], time[1], 0, 0);
}

function eventActions(action) {
  var summary = $.trim($("#event_summary").val());
  if (summary == "") {
    alert("Please specify a value for the Event Summary");
    return false;
  }

  var startDate = $.trim($("#event-start-date").val());
  var endDate = $.trim($("#event-end-date").val());

  var dateTimeRegexp = /^(0[1-9]|1[012])\/(0[1-9]|[12][0-9]|3[01])\/(19|20)\d\d (0[0-9]|1[0-9]|2[0-3])\:([0-5][0-9])$/;

  if (startDate == "" && endDate == "") {
    alert("Please specify either a start date or an end date or both");
    return false;
  }

  var queryString = "";
  if (startDate != "") {
    if (startDate.match(dateTimeRegexp) == null) {
      alert("Malformed start date: " + startDate + 
	    "\nPlease specify date-time using the format: mm/dd/yyyy hh:mm");
      return false;
    }
    queryString += "&start_time=@" + parseDateTime(startDate).getTime()/1000
  }

  if (endDate != "") {
    if (endDate.match(dateTimeRegexp) == null) {
      alert("Malformed end date: " + endDate + 
	    "\nPlease specify date-time using the format: mm/dd/yyyy hh:mm");
      return false;
    }
    queryString += "&end_time=@" + parseDateTime(endDate).getTime()/1000
  }

  if (startDate != "" && endDate != "") {
    var start = parseDateTime(startDate);
    var end = parseDateTime(endDate);
    if (start.getTime() > end.getTime()) {
      alert("Start time is greater than end time");
      return false;
    }
  }

  var host_regex = $.trim($("#host_regex").val());
  if (host_regex == "") {
    alert("You must specify a regular expression describing the host(s) to which this event should be associated.");
    return false;
  }
  queryString += "&host_regex=" + encodeURIComponent(host_regex);

  /*
  alert('api/events.php' +
        "action=" + action +
        "&summary=" + summary + queryString);
  */

  $("#event-message").html('<img src="img/spinner.gif">');
  $.get('api/events.php',
        "action=" + action +
        "&summary=" + encodeURIComponent(summary) + queryString, function(data) {
      $("#event-message").html(data);
  });
}
</script>
<div align=center><button id='add-an-event-button' onClick='$("#add-event-dialog").dialog("open");' class="minimal-indent">Add an Event</button></div>

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
<br>

<?php
  } // if (!isset($_GET['events_only']))

if ($conf['display_events_using_calendar']) {
  print "<div id='calendar'></div>";
} else {
  print "<table id='overlay_event_table' width='90%'>";
  print "<thead>";
  print "<tr>";
  print "<th>Start Time</th>";
  print "<th>End Time</th>";
  print "<th>Summary</th>";
  print "<th>Description</th>";
  print "<th>Grid</th>";
  print "<th>Cluster</th>";
  print "<th>Host Regex</th>";
  print "</tr>";
  print "</thead>";

  include_once $conf['gweb_root'] . "/functions.php";
  include_once $conf['gweb_root'] . "/lib/common_api.php";

  function start_time_cmp($ev1, $ev2) {
    $start1 = $ev1['start_time'];
    $start2 = $ev2['start_time'];

    if ($start1 == $start2)
      return 0;

    return ($start1 < $start2) ? 1 : -1;
  }

  $events_array = ganglia_events_get();
  if (count($events_array) > 0) {
    print "<tbody>";
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
    print "</tbody>";
  }
  print "</table>";
}
?>

