<h2>Breakdown</h2>

<?php

if ( !isset($_REQUEST['hreg']) and !isset($_REQUEST['metric']) ) {
  die("You need to supply host regex and metric");
} else {
  $host_reg = trim($_REQUEST['hreg']);
  $metric = trim($_REQUEST['metric']);
} 

require_once('./eval_conf.php');

$context = "cluster";

include_once "./functions.php";
include_once "./ganglia.php";
include_once "./get_ganglia.php";

$results_array = array();

foreach ($metrics as $hostname => $host_metrics ) {

  if ( preg_match("/" . $host_reg  .  "/", $hostname) ) {
    if ( isset($host_metrics[$metric]) ) {
      $metric_value = $host_metrics[$metric]["VAL"];
      $results_array[$metric_value][] = $hostname;
    }
  }

}

?>

<table width="100%" border=1>
<thead>
  <tr>
    <th>Value</th>
    <th>#</th>
    <th>Members</th>
  </tr>
</thead>
<tbody>
<?php

foreach ( $results_array as $metric_value => $members ) {

  print "<tr><td>" . $metric_value . "</td>" . 
    "<td align=\"center\">" . count($members) .
    "</td><td>" . join(",", $members) . "</td></tr>";

}

?>
</tbody>
</table>
