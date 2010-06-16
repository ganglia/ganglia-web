<?
#########################################################################
#       
#       GangliaView derived from CactiView http://github.com/lozzd/CactiView
#
#       Author: Vladimir Vuksan http://twitter.com/vvuksan
#
#       Displays a section of Ganglia graphs based on your selection.
#       Graphs rotate automatically every 30 seconds (by default)
#
#       Configuration is available in config.php
#
#########################################################################
# Time (in seconds) before the graphs will rotate automatically.
$timeout = 30;

# For ease of changing things
$cluster = "unspecified";

# Graph sizes to use. Those have to be specified in /ganglia/conf.php
$small_size = "medium";
$large_size = "xlarge";

# Path to ganglia. It can be a real URL
$gangliapath = "/ganglia/graph.php?s=by name&hc=4&st=";

# Graph definitions
#
# Alter the lines below to take the graphs you wish to rotate. 
# You can define as many graphs as you wish. 

$graphs = array (
array("hostname" => "db06.domain.com", "cluster" => $cluster, "metric_name" => "load_five", "title" => "db06 5 minute load average" ),
array("hostname" => "db03.domain.com", "cluster" => $cluster, "metric_name" => "load_five", "title" => "db03 5 minute load average" ),

);
s
# Disable debugging
error_reporting(0);

?>
