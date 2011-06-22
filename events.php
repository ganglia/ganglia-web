Following is a list of known overlay events<p>


<table width=90%>
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

include_once("./eval_conf.php");

$events_json = file_get_contents($conf['overlay_events_file']);

$events_array = json_decode($events_json, TRUE);

foreach ( $events_array as $id => $event ) {
  $description = isset($event['description']) ? $event['description'] : "";
  $end_time = isset($event['end_time']) ? date("Y-m-d H:i:s", $event['end_time']) : "";
  print "<tr><td>" . date("Y-m-d H:i:s", $event['start_time']) . "</td>" .
    "<td>" . $end_time . "</td>" .
    "<td>" . $event['summary'] . "</td>" .
    "<td>" . $description . "</td>" .
    "<td>" . $event['grid'] . "</td>" .
    "<td>" . $event['cluster'] . "</td>" .
    "<td>" . $event['host_regex'] . "</td>";
}
?>
</table>