<?php

/* Pass in by reference! */
function graph_all_web_load_report ( &$rrdtool_graph ) {

  global $context, 
           $hostname,
           $range,
           $rrd_dir,
           $size;


  # Unique colors
  $color_array = array("#000000","#CCCCCC","#FF9933","#006600","#F0F22C","#00FF00","#FA0000",
    "#FF00AA","#0000FF","#F129E5","#990000","#00FFFF","#33FF99","#996699","#FF6699","#FFCCFF",
    "#996600","#666600","#333300","#AAAAAA");

  $metric_name = "load_one";
  
  $title = 'All web servers load';
  if ($context != 'host') {
       $rrdtool_graph['title'] = $title;
  } else {
       $rrdtool_graph['title'] = "$title last $range";

  }

  $rrdtool_graph['lower-limit']    = '0';
  $rrdtool_graph['vertical-label'] = 'load';
  $rrdtool_graph['extras']         = '--rigid';
 
  if($context != "host" ) {
    /* If we are not in a host context, then we need to calculate the average */
    # Don't know what this is for therefore it's blank

  } else {

    # Initialize some of the RRDtool components
    $rrd_defs = "";
    $rrd_graphs = "";
    $rrd_legend = "";

    # Get a listing of all web servers by looking in /var/lib/ganglia/rrd/cluster
    # To do so back track in the file system
    $webservers_ls = `ls -d ${rrd_dir}/../web??.*`;

    # Split the output into an array so we can easily loop through it
    $webservers = explode("\n", $webservers_ls);

    $counter = 0;

    for ( $i = 0 ; $i < sizeof($webservers); $i++ ) {
       
	# Need index for generating RRD labels
	$index = chr($counter + 97);
	$rrd_file = $webservers[$i] . "/" . $metric_name . ".rrd"; 
  
	if ( file_exists($rrd_file)) { 

	  $rrd_defs .= "DEF:" . $index . "='" . $rrd_file . "':'sum':AVERAGE ";
	  $rrd_graphs .= "CDEF:n" . $index . "=" . $index . ",UN,0," . $index . ",IF ";
	  ##################################################################################
	  # I want short hostname not a FQDN so that legend is nice and clean
          preg_match("/(.*)(web)([0-9]{2})(.*)/", $webservers[$i] , $out);
	  $short_host = $out[2] . $out[3];
	  $rrd_legend .= "LINE1:" . $index . $color_array[$counter] . ":'" . $short_host . "' ";
	  $counter++;

	}
    
    }
    
    $rrdtool_graph['series'] = $rrd_defs . $rrd_graphs . $rrd_legend; 
  }

return $rrdtool_graph;

}

?>
