Following is a list of known overlay events<p>


<table width=90%>
<tr>
<th>Time</th>
<th>Event description</th>
<th>Grid</th>
<th>Cluster</th>
<th>Host Regex</th>
</tr>
<?php

include_once("./eval_conf.php");

$events_json = file_get_contents($conf['events_file']);

$events_array = json_decode($events_json, TRUE);

foreach ( $events_array as $id => $event ) {
  print "<tr><td>" . date("Y-m-d H:i:s", $event['timestamp']) . "</td>" .
    "<td>" . $event['description'] . "</td>" .
    "<td>" . $event['grid'] . "</td>" .
    "<td>" . $event['cluster'] . "</td>" .
    "<td>" . $event['host_regex'] . "</td>";
}
?>
</table>