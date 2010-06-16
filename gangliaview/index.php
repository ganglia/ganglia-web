<?php

header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

# Get the config
include("config.php");

# Get the requested graphid and store it in a somewhat more beautiful variable name
if ( isset($_GET['id']) ) 
  $id = $_GET['id'];
else
  $id = 0;

# Set up some variables 
$host = $graphs[$id]['hostname'];
$metric_name = $graphs[$id]['metric_name'];
$cluster = $graphs[$id]['cluster'];
# The title of the graph
$title = $graphs[$id]["title"];

# If it is a report graph you have to use g=
if ( preg_match ("/_report/", $metric_name) ) {
    $graph_name = "g=${metric_name}";
} else {
    $graph_name = "m=${metric_name}";
}

# The title of the next graph, with some logic to set the next to the first if we run out of graphs
if ($id < (count($graphs) -1)) {
	$nextid = $id+1;
} else {
        $nextid = 0;
}

$nexttitle = $graphs[$nextid]["title"];


?>
<html>
<head>
<title>Ganglia - Graph View</title>
<meta http-equiv="refresh" content="<?php print "$timeout;url=" . $_SERVER["SCRIPT_NAME"] . "?id=" . $nextid; ?>">
<style>
body { 
	margin: 0px;
	font-family: Tahoma, Helvetica, Verdana, Arial, sans-serif;
}
</style>
</head>


<body>
<div style="position: fixed; left: 20; width: 800; top: 2; font-size: 48px;"><?php echo $title;  ?></div>
<div style="position: fixed; left: 20; width: 600; top: 55; font-size: 24px;">Next: <?php echo $nexttitle  ?></div><br />

<table>
<tr>
	<td><img src="<?php echo $gangliapath . "&c=${cluster}&h=${host}&r=hour&z=${large_size}&${graph_name}" ?>"><br />
	    <img src="<?php echo $gangliapath . "&c=${cluster}&h=${host}&r=day&z=${large_size}&${graph_name}" ?>"></td>
	<td valign="top">
          <img src="<?php echo $gangliapath . "&c=${cluster}&h=${host}&r=week&z=${small_size}&${graph_name}" ?>">
	  <img src="<?php echo $gangliapath . "&c=${cluster}&h=${host}&r=month&z=${small_size}&${graph_name}" ?>">
         <div style="margin-top: 10px; font-size: 48px; text-align: center;"><?php echo date('n/j/y G:i T'); ?></div>
	</td>

	</td>
</tr>
</table>


</body>
</html>