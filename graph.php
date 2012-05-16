<?php
// vim: tabstop=2:softtabstop=2:shiftwidth=2:expandtab

include_once "./eval_conf.php";
include_once "./get_context.php";
include_once "./functions.php";

///////////////////////////////////////////////////////////////////////////////
// Populate $rrdtool_graph from $config (from JSON file).
///////////////////////////////////////////////////////////////////////////////
function build_rrdtool_args_from_json( &$rrdtool_graph, $graph_config ) {
  
  global $context, $hostname, $range, $rrd_dir, $size, $conf;
  
  if ($conf['strip_domainname'])     {
    $hostname = strip_domainname($hostname);
  }
   
  $title = sanitize( $graph_config[ 'title' ] );
  $rrdtool_graph[ 'title' ] = $title; 
  // If vertical label is empty or non-existent set it to space otherwise 
  // rrdtool will fail
  if ( ! isset($graph_config[ 'vertical_label' ]) || 
       $graph_config[ 'vertical_label' ] == "" ) {
     $rrdtool_graph[ 'vertical-label' ] = " ";   
  } else {
     $rrdtool_graph[ 'vertical-label' ] = 
       sanitize( $graph_config[ 'vertical_label' ] );
  }

  $rrdtool_graph['lower-limit'] = '0';
  
  if( isset($graph_config['height_adjustment']) ) {
    $rrdtool_graph['height'] += 
      ($size == 'medium') ? $graph_config['height_adjustment'] : 0;
  } else {
    $rrdtool_graph['height'] += ($size == 'medium') ? 28 : 0;
  } 
  
  // find longest label length, so we pad the others accordingly to get 
  // consistent column alignment
  $max_label_length = 0;
  foreach( $graph_config[ 'series' ] as $item ) {
    $max_label_length = max( strlen( $item[ 'label' ] ), $max_label_length );
  }
  
  $series = '';
  
  $stack_counter = 0;

  // Available line types
  $line_widths = array("1","2","3");

  // Loop through all the graph items
  foreach( $graph_config[ 'series' ] as $index => $item ) {
     // ignore item if context is not defined in json template
     if ( isSet($item[ 'contexts' ]) and 
          in_array($context, $item['contexts']) == false )
         continue;

     $rrd_dir = $conf['rrds'] . "/" . $item['clustername'] . "/" . $item['hostname'];

     $metric = sanitize( $item[ 'metric' ] );
     
     $metric_file = $rrd_dir . "/" . $metric . ".rrd";
    
     // Make sure metric file exists. Otherwise we'll get a broken graph
     if ( is_file($metric_file) ) {

       # Need this when defining graphs that may use same metric names
      $unique_id = "a" . $index;
     
       $label = str_pad( sanitize( $item[ 'label' ] ), $max_label_length );

       // use custom DS defined in json template if it's 
       // defined (default = 'sum')
       $DS = "sum";
       if ( isset($item[ 'ds' ]) )
         $DS = sanitize( $item[ 'ds' ] );
       $series .= " DEF:'$unique_id'='$metric_file':'$DS':AVERAGE ";

       // By default graph is a line graph
       isset( $item['type']) ? 
         $item_type = $item['type'] : $item_type = "line";

       // TODO sanitize color
       switch ( $item_type ) {
       
         case "line":
           // Make sure it's a recognized line type
           isset($item['line_width']) && 
           in_array( $item['line_width'], $line_widths) ? 
             $line_width = $item['line_width'] : $line_width = "1";
           $series .= "LINE" . 
                      $line_width . 
                      ":'$unique_id'#{$item['color']}:'{$label}' ";
           break;
       
         case "stack":
           // First element in a stack has to be AREA
           if ( $stack_counter == 0 ) {
             $series .= "AREA";
             $stack_counter++;
           } else {
             $series .= "STACK";
           }
           $series .= ":'$unique_id'#${item['color']}:'${label}' ";
           break;
        } // end of switch ( $item_type )
     
        if ( $conf['graphreport_stats'] )
          $series .= legendEntry($unique_id, $conf['graphreport_stat_items']);

     } // end of if ( is_file($metric_file) ) {
     
  } // end of foreach( $graph_config[ 'series' ] as $index => $item )

  // If we end up with the empty series it means that no RRD files matched. 
  // This can happen if we are trying to create a report and metrics for 
  // this host were not collected. If that happens we should create an 
  // empty graph
  if ( $series == "" ) {
    $rrdtool_graph[ 'series' ] = 
      'HRULE:1#FFCC33:"No matching metrics detected"';   
  } else {
    $rrdtool_graph[ 'series' ] = $series;
  }

  return $rrdtool_graph;
}

///////////////////////////////////////////////////////////////////////////////
// Graphite graphs
///////////////////////////////////////////////////////////////////////////////
function build_graphite_series( $config, $host_cluster = "" ) {
  global $context;
  $targets = array();
  $colors = array();
  // Keep track of stacked items
  $stacked = 0;

  foreach( $config[ 'series' ] as $item ) {
    if ( isSet($item[ 'contexts' ]) and in_array($context, $item['contexts'])==false )
      continue;
    if ( $item['type'] == "stack" )
      $stacked++;

    if ( isset($item['hostname']) && isset($item['clustername']) ) {
      $host_cluster = $item['clustername'] . "." . str_replace(".","_", $item['hostname']);
    }

    $targets[] = "target=". urlencode( "alias($host_cluster.${item['metric']}.sum,'${item['label']}')" );
    $colors[] = $item['color'];

  }
  $output = implode( $targets, '&' );
  $output .= "&colorList=" . implode( $colors, ',' );
  $output .= "&vtitle=" . urlencode( isset($config[ 'vertical_label' ]) ? $config[ 'vertical_label' ] : "" );

  // Do we have any stacked elements. We assume if there is only one element
  // that is stacked that rest of it is line graphs
  if ( $stacked > 0 ) {
    if ( $stacked > 1 )
      $output .= "&areaMode=stacked";
    else
      $output .= "&areaMode=first";
  }
  
  return $output;
}

$gweb_root = dirname(__FILE__);

# RFM - Added all the isset() tests to eliminate "undefined index"
# messages in ssl_error_log.

# Graph specific variables
# ATD - No need for escapeshellcmd or rawurldecode on $size or $graph.  Not used directly in rrdtool calls.
$size = isset($_GET["z"]) && 
        in_array($_GET[ 'z' ], $conf['graph_sizes_keys']) ? $_GET["z"] : NULL;

$metric_name = isset($_GET["m"]) ? sanitize ( $_GET["m"] ) : NULL;
# In clusterview we may supply report as metric name so let's make sure it
# doesn't treat it as a metric
if ( preg_match("/_report$/", $metric_name) && !isset($_GET["g"]) ) {
  $graph = $metric_name;
} else {
  # If graph arg is not specified default to metric
  $graph = isset($_GET["g"])  ?  sanitize ( $_GET["g"] )   : "metric";
}

$graph_arguments = NULL;
$pos = strpos($graph, ",");
if ($pos !== FALSE) {
  $graph_report = substr($graph, 0, $pos);
  $graph_arguments = substr($graph, $pos + 1);
  $graph = $graph_report;
}

$grid = isset($_GET["G"]) ? sanitize( $_GET["G"]) : NULL;
$self = isset($_GET["me"]) ? sanitize( $_GET["me"]) : NULL;
$vlabel = isset($_GET["vl"]) ? sanitize($_GET["vl"])  : NULL;
$value = isset($_GET["v"]) ? sanitize ($_GET["v"]) : NULL;
$max = isset($_GET["x"]) && is_numeric($_GET["x"]) ? $_GET["x"] : NULL;
$min = isset($_GET["n"]) && is_numeric($_GET["n"]) ? $_GET["n"] : NULL;
$critical = isset($_GET["crit"]) && is_numeric($_GET["crit"]) ? $_GET["crit"] : NULL;
$warning = isset($_GET["warn"]) && is_numeric($_GET["warn"]) ? $_GET["warn"] : NULL;

$sourcetime = isset($_GET["st"]) ? clean_number(sanitize($_GET["st"])) : NULL;
$load_color = isset($_GET["l"]) && 
              is_valid_hex_color(rawurldecode($_GET['l'])) ?
                sanitize($_GET["l"]) : NULL;
$summary = isset($_GET["su"]) ? 1 : 0;
$debug = isset($_GET['debug']) ? clean_number(sanitize($_GET["debug"])) : 0;
$showEvents = isset($_GET["event"]) ? sanitize ($_GET["event"]) : "show";
$user['time_shift'] = isset($_GET['ts']) ? 1 : NULL;

$command    = '';
$graphite_url = '';

$user['json_output'] = isset($_GET["json"]) ? 1 : NULL;
# Request for live dashboard
if ( isset($_REQUEST['live']) ) {
  $user['live_dashboard'] = 1;
  $user['json_output'] = 1;
} else {
  $user['live_output'] = NULL;
}
$user['csv_output'] = isset($_GET["csv"]) ? 1 : NULL; 
$user['graphlot_output'] = isset($_GET["graphlot"]) ? 1 : NULL; 
$user['flot_output'] = isset($_GET["flot"]) ? 1 : NULL; 

$user['trend_line'] = isset($_GET["trend"]) ? 1 : NULL; 
# How many months ahead to extend the trend e.g. 6 months
$user['trend_range'] = isset($_GET["trendrange"]) && is_numeric($_GET["trendrange"]) ? $_GET["trendrange"] : 6;
# 
$user['trend_history'] = isset($_GET["trendhistory"]) && is_numeric($_GET["trendhistory"]) ? $_GET["trendhistory"] : 6;

// Get hostname
$raw_host = isset($_GET["h"]) ? sanitize($_GET["h"]) : "__SummaryInfo__";  

// For graphite purposes we need to replace all dots with underscore. dot  is
// separates subtrees in graphite
$host = str_replace(".","_", $raw_host);

# Assumes we have a $start variable (set in get_context.php).
# $conf['graph_sizes'] and $conf['graph_sizes_keys'] defined in conf.php.  
# Add custom sizes there.
$size = in_array($size, $conf['graph_sizes_keys']) ? $size : 'default';

if (isset($_GET['height'])) 
  $height = $_GET['height'];
else 
  $height  = $conf['graph_sizes'][$size]['height'];

if (isset($_GET['width'])) 
  $width =  $_GET['width'];
else
  $width = $conf['graph_sizes'][$size]['width'];

#$height = $conf['graph_sizes'][$size]['height'];
#$width = $conf['graph_sizes'][$size]['width'];
$fudge_0 = $conf['graph_sizes'][$size]['fudge_0'];
$fudge_1 = $conf['graph_sizes'][$size]['fudge_1'];
$fudge_2 = $conf['graph_sizes'][$size]['fudge_2'];

///////////////////////////////////////////////////////////////////////////
// Set some variables depending on the context. Context is set in
// get_context.php
///////////////////////////////////////////////////////////////////////////
switch ($context)
{
  case "meta":
    $rrd_dir = $conf['rrds'] . "/__SummaryInfo__";
    $rrd_graphite_link = $conf['graphite_rrd_dir'] . "/__SummaryInfo__";
    $title = "$self Grid";
    break;
  case "grid":
    $rrd_dir = $conf['rrds'] . "/$grid/__SummaryInfo__";
    $rrd_graphite_link = $conf['graphite_rrd_dir'] . "/$grid/__SummaryInfo__";
    if (preg_match('/grid/i', $gridname))
        $title  = $gridname;
    else
        $title  = "$gridname Grid";
    break;
  case "cluster":
    $rrd_dir = $conf['rrds'] . "/$clustername/__SummaryInfo__";
    $rrd_graphite_link = $conf['graphite_rrd_dir'] . "/$clustername/__SummaryInfo__";
    if (preg_match('/cluster/i', $clustername))
        $title  = $clustername;
    else
        $title  = "$clustername Cluster";
    break;
  case "host":
    $rrd_dir = $conf['rrds'] . "/$clustername/$raw_host";
    $rrd_graphite_link = $conf['graphite_rrd_dir'] . "/" . $clustername . "/" . $host;
    // Add hostname to report graphs' title in host view
    if ($graph != 'metric')
       if ($conf['strip_domainname'])
          $title = strip_domainname($raw_host);
       else
          $title = $raw_host;
    break;
  default:
    break;
}


$resource = GangliaAcl::ALL_CLUSTERS;
if( $context == "grid" ) {
  $resource = $grid;
} else if ( $context == "cluster" || $context == "host" ) {
  $resource = $clustername; 
}
if( ! checkAccess( $resource, GangliaAcl::VIEW, $conf ) ) {
  header( "HTTP/1.1 403 Access Denied" );
  header ("Content-type: image/jpg");
  echo file_get_contents( $gweb_root.'/img/access-denied.jpg');
  die();
}

if ($cs)
    $start = $cs;
if ($ce)
    $end = $ce;

# Set some standard defaults that don't need to change much
$rrdtool_graph = array(
    'start'  => $start,
    'end'    => $end,
    'width'  => $width,
    'height' => $height,
);

# automatically strip domainname from small graphs where it won't fit
if ($size == "small") {
    $conf['strip_domainname'] = true;
    # Let load coloring work for little reports in the host list.
}

if (! isset($subtitle) and $load_color)
    $rrdtool_graph['color'] = "BACK#'$load_color'";

if ($debug) {
  error_log("Graph [$graph] in context [$context]");
}

/* If we have $graph, then a specific report was requested, such as "network_report" or
 * "cpu_report.  These graphs usually have some special logic and custom handling required,
 * instead of simply plotting a single metric.  If $graph is not set, then we are (hopefully),
 * plotting a single metric, and will use the commands in the metric.php file.
 *
 * With modular graphs, we look for a "${graph}.php" file, and if it exists, we
 * source it, and call a pre-defined function name.  The current scheme for the function
 * names is:   'graph_' + <name_of_report>.  So a 'cpu_report' would call graph_cpu_report(),
 * which would be found in the cpu_report.php file.
 *
 * These functions take the $rrdtool_graph array as an argument.  This variable is
 * PASSED BY REFERENCE, and will be modified by the various functions.  Each key/value
 * pair represents an option/argument, as passed to the rrdtool program.  Thus,
 * $rrdtool_graph['title'] will refer to the --title option for rrdtool, and pass the array
 * value accordingly.
 *
 * There are two exceptions to:  the 'extras' and 'series' keys in $rrdtool_graph.  These are
 * assigned to $extras and $series respectively, and are treated specially.  $series will contain
 * the various DEF, CDEF, RULE, LINE, AREA, etc statements that actually plot the charts.  The
 * rrdtool program requires that this come *last* in the argument string; we make sure that it
 * is put in it's proper place.  The $extras variable is used for other arguemnts that may not
 * fit nicely for other reasons.  Complicated requests for --color, or adding --ridgid, for example.
 * It is simply a way for the graph writer to add an arbitrary options when calling rrdtool, and to
 * forcibly override other settings, since rrdtool will use the last version of an option passed.
 * (For example, if you call 'rrdtool' with two --title statements, the second one will be used.)
 *
 * See ${conf['graphdir']}/sample.php for more documentation, and details on the
 * common variables passed and used.
 */

// Calculate time range.
if ($sourcetime)
   {
      $end = $sourcetime;
      # Get_context makes start negative.
      $start = $sourcetime + $start;
   }
// Fix from Phil Radden, but step is not always 15 anymore.
if ($range == "month")
   $rrdtool_graph['end'] = floor($rrdtool_graph['end'] / 672) * 672;

///////////////////////////////////////////////////////////////////////////////
// Are we generating aggregate graphs
///////////////////////////////////////////////////////////////////////////////
if ( isset( $_GET["aggregate"] ) && $_GET['aggregate'] == 1 ) {
    
  // Set start time
  $start = time() + $start;

  // If graph type is not specified default to line graph
  if ( isset($_GET["gtype"]) && in_array($_GET["gtype"], array("stack","line") )  ) 
      $graph_type = $_GET["gtype"];
  else
      $graph_type = "line";

  // If line width not specified default to 2
  if ( isset($_GET["lw"]) && in_array($_GET["lw"], array("1","2", "3") )  ) 
      $line_width = $_GET["lw"];
  else
      $line_width = "2";

  if ( isset($_GET["glegend"]) && in_array($_GET["glegend"], array("show", "hide") ) )
      $graph_legend = $_GET["glegend"];
  else
      $graph_legend = "show";


  /////////////////////////////////////////////////////////////////////////////
  // In order to reduce the load on the machine when someone is doing host
  // compare we look whether host list has been supplied via hl arg.
  // That way we do not have get metric cache
  /////////////////////////////////////////////////////////////////////////////
  if ( isset($_GET['hl']) ) {
    
    $counter = 0;
    $color_count = sizeof($conf['graph_colors']);
    
    $metric_name = str_replace("$", "", str_replace("^", "", $_GET['mreg'][0]));
    
    $host_list = explode(",", $_GET['hl']);
    foreach ( $host_list as $index => $host_cluster ) {
      $color_index = $counter % $color_count;
      $parts = explode("|", $host_cluster);
      $hostname = $parts[0];
      $clustername = $parts[1];

      $series = array("hostname" => $hostname,
                      "clustername" => $clustername,
                      "fill" => "true", 
                      "metric" => $metric_name,  
                      "color" => $conf['graph_colors'][$color_index], 
                      "label" => $hostname, 
                      "type" => $graph_type);

      if ($graph_type == "line" || $graph_type == "area") {
        $series['line_width'] = $line_width;
      } else {
        $series['stack'] = "1";
      }
      $graph_config['series'][] = $series;
      
      $counter++;
    } // end of foreach ( $host_list as 
  } else {
    $exclude_host_from_legend_label = 
      (array_key_exists('lgnd_xh', $_GET) && 
       $_GET['lgnd_xh'] == "true") ? TRUE : FALSE;
    $graph_config = build_aggregate_graph_config ($graph_type, 
                                                  $line_width, 
                                                  $_GET['hreg'], 
                                                  $_GET['mreg'],
                                                  $graph_legend,
                                                  $exclude_host_from_legend_label);
  }

  // Set up 
  $graph_config["report_type"] = "standard";
  $graph_config["vertical_label"] = $vlabel;

  // Reset graph title 
  if ( isset($_GET['title']) && $_GET['title'] != "") {
    $title = "";
    $graph_config["title"] = sanitize($_GET['title']);
  } else {
    $title = "Aggregate";
  }
}

//////////////////////////////////////////////////////////////////////////////
// Check what graph engine we are using
//////////////////////////////////////////////////////////////////////////////
switch ( $conf['graph_engine'] ) {
  case "flot":
  case "rrdtool":

    if ( ! isset($graph_config) ) {
	if ( ($graph == "metric") &&
             isset($_GET['title']) && 
             $_GET['title'] !== '')
	  $metrictitle = sanitize($_GET['title']);
      $php_report_file = $conf['graphdir'] . "/" . $graph . ".php";
      $json_report_file = $conf['graphdir'] . "/" . $graph . ".json";
      if( is_file( $php_report_file ) ) {
        include_once $php_report_file;
        $graph_function = "graph_${graph}";
        if (isset($graph_arguments))
          eval('$graph_function($rrdtool_graph,' . $graph_arguments . ');');
        else
          $graph_function( $rrdtool_graph );  // Pass by reference call, $rrdtool_graph modified inplace
      } else if ( is_file( $json_report_file ) ) {
        $graph_config = json_decode( file_get_contents( $json_report_file ), TRUE );

        # We need to add hostname and clustername if it's not specified
        foreach ( $graph_config['series'] as $index => $item ) {
          if ( ! isset($graph_config['series'][$index]['hostname'])) {
            $graph_config['series'][$index]['hostname'] = $raw_host;
            if (isset($grid))
               $graph_config['series'][$index]['clustername'] = $grid;
            else
               $graph_config['series'][$index]['clustername'] = $clustername;
          }
        }
        build_rrdtool_args_from_json ( $rrdtool_graph, $graph_config );
      }
    } else { 
        build_rrdtool_args_from_json ( $rrdtool_graph, $graph_config );
    }
  
    // We must have a 'series' value, or this is all for naught
    if (!array_key_exists('series', $rrdtool_graph) || 
        !strlen($rrdtool_graph['series']) ) {
	$rrdtool_graph[ 'series' ] = 
	  'HRULE:1#FFCC33:"No matching metrics detected"';
    }
  
    # Make small graphs (host list) cleaner by removing the too-big
    # legend: it is displayed above on larger cluster summary graphs.
    if (($size == "small" and ! isset($subtitle)) || ($graph_config["glegend"] == "hide"))
        $rrdtool_graph['extras'] = isset($rrdtool_graph['extras']) ? $rrdtool_graph['extras'] . " -g" : " -g" ;

    # add slope-mode if rrdtool_slope_mode is set
    if (isset($conf['rrdtool_slope_mode']) && 
        $conf['rrdtool_slope_mode'] == True)
        $rrdtool_graph['slope-mode'] = '';
  
    if (isset($rrdtool_graph['title']) && isset($title)) {
      if ($conf['decorated_graph_title'])
        $rrdtool_graph['title'] = $title . " " . 
                                  $rrdtool_graph['title'] . 
                                  " last $range";
      else
        $rrdtool_graph['title'] = $rrdtool_graph['title'];
    }

    $command = $conf['rrdtool'] . " graph - $rrd_options ";

    // Look ahead six months
    if ( $user['trend_line'] ) {
      // We may only want to use last x months of data since for example
      // if we are trending disk we may have added a disk recently which will
      // skew a trend line. By default we'll use 6 months however we'll let
      // user define this if they want to.
      $rrdtool_graph['start'] = "-" . $user['trend_history'] * 2592000 . "s";
      // Project the trend line this many months ahead
      $rrdtool_graph['end'] = "+" . $user["trend_range"] * 2592000 . "s";
    }

    if ( $max ) {
      $rrdtool_graph['upper-limit'] = $max;
    }
    if ( $min )
      $rrdtool_graph['lower-limit'] = $min;
    if ( $max || $min )
        $rrdtool_graph['extras'] = isset($rrdtool_graph['extras']) ? $rrdtool_graph['extras'] . " --rigid" : " --rigid" ;
  
    // The order of the other arguments isn't important, except for the
    // 'extras' and 'series' values.  These two require special handling.
    // Otherwise, we just loop over them later, and tack $extras and
    // $series onto the end of the command.
    foreach (array_keys ($rrdtool_graph) as $key) {
      if (preg_match('/extras|series/', $key))
          continue;

      $value = $rrdtool_graph[$key];

      if (preg_match('/\W/', $value)) {
          //more than alphanumerics in value, so quote it
          $value = "'$value'";
      }
      $command .= " --$key $value";
    }
  
    // And finish up with the two variables that need special handling.
    // See above for how these are created
    $command .= array_key_exists('extras', $rrdtool_graph) ? ' '.$rrdtool_graph['extras'].' ' : '';
    $command .= " $rrdtool_graph[series]";
    break;

  /////////////////////////////////////////////////////////////////////////////
  // USING Graphite
  /////////////////////////////////////////////////////////////////////////////
  case "graphite":  
    // Check whether the link exists from Ganglia RRD tree to the graphite 
    // storage/rrd_dir area
    if ( ! is_link($rrd_graphite_link) ) {
      // Does the directory exist for the cluster. If not create it
      if ( ! is_dir ($conf['graphite_rrd_dir'] . "/" . str_replace(" ", "_", $clustername)) )
        mkdir ( $conf['graphite_rrd_dir'] . "/" . str_replace(" ", "_", $clustername ));
      symlink($rrd_dir, str_replace(" ", "_", $rrd_graphite_link));
    }
  
    // Generate host cluster string
    if ( isset($clustername) ) {
      $host_cluster = str_replace(" ", "_", $clustername) . "." . $host;
    } else {
      $host_cluster = $host;
    }
  
    $height += 70;
  
    if ($size == "small") {
      $width += 20;
    }
  
  //  $title = urlencode($rrdtool_graph["title"]);
  
    // If graph_config is already set we can use it immediately
    if ( isset($graph_config) ) {

      $target = build_graphite_series( $graph_config, "" );

    } else {

      if ( isset($_GET['g'])) {
    // if it's a report increase the height for additional 30 pixels
    $height += 40;
    
    $report_name = sanitize($_GET['g']);
    
    $report_definition_file = $conf['gweb_root'] . "/graph.d/" . $report_name . ".json";
    // Check whether report is defined in graph.d directory
    if ( is_file($report_definition_file) ) {
      $graph_config = json_decode(file_get_contents($report_definition_file), TRUE);
    } else {
      error_log("There is JSON config file specifying $report_name.");
      exit(1);
    }
    
    if ( isset($graph_config) ) {
      switch ( $graph_config["report_type"] ) {
        case "template":
          $target = str_replace("HOST_CLUSTER", $conf['graphite_prefix'] . $host_cluster, $graph_config["graphite"]);
          break;
    
        case "standard":
          $target = build_graphite_series( $graph_config, $conf['graphite_prefix'] . $host_cluster );
          break;
    
        default:
          error_log("No valid report_type specified in the " .
                    $report_name .
                    " definition.");
          break;
      }
    
      $title = $graph_config['title'];
    } else {
      error_log("Configuration file to $report_name exists however it doesn't appear it's a valid JSON file");
      exit(1);
    }
      } else {
    // It's a simple metric graph
    $target = "target=" . $conf['graphite_prefix'] . "$host_cluster.$metric_name.sum&hideLegend=true&vtitle=" . urlencode($vlabel) . "&areaMode=all&colorList=". $conf['default_metric_color'];
    $title = " ";
      }

    } // end of if ( ! isset($graph_config) ) {
    
    if ($cs) $start = date("H:i_Ymd",strtotime($cs));
    if ($ce) $end = date("H:i_Ymd",strtotime($ce));
    if ($max == 0) $max = "";
    $graphite_url = $conf['graphite_url_base'] . "?width=$width&height=$height&" . $target . "&from=" . $start . "&until=" . $end . "&yMin=" . $min . "&yMax=" . $max . "&bgcolor=FFFFFF&fgcolor=000000&title=" . urlencode($title . " last " . $range);
    break;

} // end of switch ( $conf['graph_engine'])

// Output to JSON
if ( $user['json_output'] || 
     $user['csv_output'] || 
     $user['flot_output'] || 
     $user['graphlot_output'] ) {

  if ($conf['graph_engine'] == "graphite") {
    if ( $user['json_output'] == 1 ) { $output_format = "json"; }
    elseif ( $user['csv_output'] == 1 ) { $output_format = "csv"; }
    echo file_get_contents($graphite_url . "&format=" . $output_format);
  } else {

  $rrdtool_graph_args = "";

  // First find RRDtool DEFs by parsing $rrdtool_graph['series']
  preg_match_all("/([^V]DEF|CDEF):(.*)(:AVERAGE|\s)/", 
                 " " . $rrdtool_graph['series'], 
                 $matches);
  foreach ( $matches[0] as $key => $value ) {
    $rrdtool_graph_args .= $value . " ";
  }

  preg_match_all("/(LINE[0-9]*|AREA|STACK):\'[^']*\'[^']*\'[^']*\'[^ ]* /",
                 " " . $rrdtool_graph['series'], 
                 $matches);
  foreach ( $matches[0] as $key => $value ) {
    if ( preg_match("/(LINE[0-9]*:\'|AREA:\'|STACK:\')([^']*)(\')([^']*)(\')([^']*)(')/", $value, $out ) ) {
      $ds_name = $out[2];
      $cluster_name = "";
      $host_name = "";
      $metric_type = "line";
      if (preg_match("/(STACK:|AREA:)/", $value, $ignore)) {
        $metric_type = "stack";
      }
      $metric_name = $out[6];
      $ds_attr = array( "ds_name" => $ds_name,
			"cluster_name" => $cluster_name,
			"graph_type" => $metric_type,
			"host_name" => $host_name, 
			"metric_name" => $metric_name );

      // Add color if it exists
      $pos_hash = strpos($out[4], '#');
      if ($pos_hash !== FALSE) {
	$pos_colon = strpos($out[4], ':');
        if ($pos_colon !== FALSE)
	  $ds_attr['color'] = substr($out[4], 
				     $pos_hash, 
				     $pos_colon - $pos_hash); 
	else
	  $ds_attr['color'] = substr($out[4], $pos_hash); 
      }

      $output_array[] = $ds_attr;

      $rrdtool_graph_args .=  " " . "XPORT:'" . $ds_name . "':'" . $metric_name . "' ";
    }
  }


  // This command will export values for the specified format in XML
  $command = $conf['rrdtool'] . " xport --start '" . $rrdtool_graph['start'] . "' --end '" .  $rrdtool_graph['end'] . "' " . $rrd_options . " " . $rrdtool_graph_args;

  // Read in the XML
  $fp = popen($command,"r"); 
  $string = "";
  while (!feof($fp)) { 
    $buffer = fgets($fp, 4096);
    $string .= $buffer;
  }
  // Parse it
  $xml = simplexml_load_string($string);

  # If there are multiple metrics columns will be > 1
  $num_of_metrics = $xml->meta->columns;

  // 
  $metric_values = array();
  // Build the metric_values array

  foreach ( $xml->data->row as $key => $objects ) {
    $values = get_object_vars($objects);

    // If $values["v"] is an array we have multiple data sources/metrics and 
    // we need to iterate over those
    if ( is_array($values["v"]) ) {
      foreach ( $values["v"] as $key => $value ) {
        $output_array[$key]["datapoints"][] = 
	  array(floatval($value), intval($values['t']));
      }
    } else {
      $output_array[0]["datapoints"][] = 
	array(floatval($values["v"]), intval($values['t']));
    }

  }

  // If JSON output request simple encode the array as JSON
  if ( $user['json_output'] ) {

    // First let's check if JSON output is requested for Live Dashboard and
    // we are outputting aggregate graph. If so we need to add up all the values
    if ( $user['live_dashboard'] && sizeof($output_array) > 1 ) {
      $summed_output = array();
      foreach ( $output_array[0]['datapoints'] as $index => $datapoint ) {
        // Data point is an array with value and UNIX time stamp. Initialize
        // summed output as 0
        $summed_output[$index] = array( 0, $datapoint[1] );
        for ( $i = 0 ; $i < sizeof($output_array) ; $i++ ) {
          $summed_output[$index][0] += $output_array[$i]['datapoints'][$index][0];
        }
      }
      
      unset($output_array);
      $output_array[0]['datapoints'] = $summed_output;
    }
    header("Content-type: application/json");
    header("Content-Disposition: inline; filename=\"ganglia-metrics.json\"");
    print json_encode($output_array);

  }

  // If Flot output massage the data JSON
  if ( $user['flot_output'] ) {
    foreach ( $output_array as $key => $metric_array ) {
      foreach ( $metric_array['datapoints'] as $key => $values ) {
        $data_array[] = array ( $values[1]*1000, $values[0]);  
      }

      $gdata = array('label' => strip_domainname($metric_array['host_name']) . 
                                                 " " . 
                                                 $metric_array['metric_name'], 
                     'data' => $data_array);

      if (array_key_exists('color', $metric_array))
	$gdata['color'] = $metric_array['color'];

      if ($metric_array['graph_type'] == "stack")
        $gdata['stack'] = '1';
      $flot_array[] = $gdata;

      unset($data_array);
    }
    header("Content-type: application/json");
    print json_encode($flot_array);
  }

  if ( $user['csv_output'] ) {
    header("Content-Type: application/csv");
    header("Content-Disposition: inline; filename=\"ganglia-metrics.csv\"");

    print "Timestamp";

    // Print out headers
    for ( $i = 0 ; $i < sizeof($output_array) ; $i++ ) {
      print "," . $output_array[$i]["metric_name"];
    }

    print "\n";

    foreach ( $output_array[0]['datapoints'] as $key => $row ) {
      print date("c", $row[1]);
      for ( $j = 0 ; $j < $num_of_metrics ; $j++ ) {
        print "," . $output_array[$j]["datapoints"][$key][0];
      }
      print "\n";
    }
  }

  // Implement Graphite style Raw Data
  if ( $user['graphlot_output'] ) {
    header("Content-Type: application/json");

    $last_index = sizeof($output_array[0]["datapoints"]) - 1;
  
    $output_vals['step'] = $output_array[0]["datapoints"][1][1] - $output_array[0]["datapoints"][0][1];
    $output_vals['name'] = "stats." . $output_array[0]["metric_name"];
    $output_vals['start'] = $output_array[0]["datapoints"][0][1];
    $output_vals['end'] = $output_array[0]["datapoints"][$last_index][1];

    foreach ( $output_array[0]["datapoints"] as $index => $array ) {
      $output_vals['data'][] = $array[0];
    } 

    print json_encode(array($output_vals, $output_vals));
  }
  }
  exit(0);
}

//////////////////////////////////////////////////////////////////////////////
// Nagios event integration support
//////////////////////////////////////////////////////////////////////////////
$nagios_events = array();
if ( $showEvents == "show" &&
     $conf['overlay_nagios_events'] && 
     ! in_array($range, $conf['overlay_events_exclude_ranges']) ) {
  $nagios_pull_url = 
    $conf['overlay_nagios_base_url'] . 
    '/cgi-bin/api.cgi?action=host.gangliaevents&host=' . urlencode($raw_host) .
    '&start=' . urlencode($start) . 
    '&end=' . urlencode($end);
  $raw_nagios_events = 
    @file_get_contents(
      $nagios_pull_url,
      0,
      stream_context_create(
        array('http' => array('timeout' => 5), 
                              'https' => array('timeout' => 5))));
  if (strlen($raw_nagios_events) > 3) {
    $nagios_events = json_decode( $raw_nagios_events, TRUE );
    // Handle any "ERROR" formatted messages and wipe resulting array.
    if (isset($nagios_events['response_type']) && 
        $nagios_events['response_type'] == 'ERROR') {
      $nagios_events = array();
    }
  }
}

//////////////////////////////////////////////////////////////////////////////
// Check whether user wants to overlay events on graphs
//////////////////////////////////////////////////////////////////////////////
if ( $showEvents == "show" &&
     $conf['overlay_events'] && 
     $conf['graph_engine'] == "rrdtool" && 
     ! in_array($range, $conf['overlay_events_exclude_ranges']) && ! $user['trend_line'] ) {

  $color_count = sizeof($conf['graph_colors']);
  $counter = 0;
  $color_counter = 0;

  // In order not to pollute the command line with all the possible VRULEs
  // we need to find the time range for the graph
  if ( $rrdtool_graph['end'] == "-now" or $rrdtool_graph['end'] == "now") {
    $end = time();
  } else if ( is_numeric($rrdtool_graph['end']) ) {
    $end = $rrdtool_graph['end'];
  }

  if ( preg_match("/\-([0-9]*)(s)/", $rrdtool_graph['start'] , $out ) ) {
    $start = time() - $out[1];
  } else if ( is_numeric($rrdtool_graph['start']) ) {
    $start = $rrdtool_graph['start'];
  } else {
    // If it's not 
    $start = time() - 157680000;
  }

  // Get array of events for time range
  $events_array = ganglia_events_get($start, $end);

  if (!empty($events_array)) {
    $event_color_json = 
      file_get_contents($conf['overlay_events_color_map_file']);
    if ($debug) 
      error_log("$event_color_json");
    $event_color_array = json_decode($event_color_json, TRUE);
    $initial_event_color_count = count($event_color_array);
    $event_color_map = array();
    foreach ($event_color_array as $event_color_entry) {
      $event_color_map[$event_color_entry['summary']] = 
        $event_color_entry['color'];
      if ($debug) 
	error_log("Adding event color to map: " .
		  $event_color_entry['summary'] .
		  ' ' .
		  $event_color_entry['color']);
    }
    $color_count = sizeof($conf['graph_colors']);
    $counter = 0;

    // In order not to pollute the command line with all the possible VRULEs
    // we need to find the time range for the graph
    if ( $rrdtool_graph['end'] == "-now" or $rrdtool_graph['end'] == "now")
      $end = time();
    else if ( is_numeric($rrdtool_graph['end']) )
      $end = $rrdtool_graph['end'];
    else
      error_log("Graph does not have a specified end time");

    if ( preg_match("/\-([0-9]*)(s)/", $rrdtool_graph['start'] , $out ) ) {
      $start = time() - $out[1];
    } else if ( is_numeric($rrdtool_graph['start']) )
      $start = $rrdtool_graph['start'];
    else
      // If it's not 
      $start = time() - 157680000;

    // Preserve original rrdtool command. That's the one we'll run regex checks
    // against
    $original_command = $command;

    // Combine the nagios_events array, if it exists
    if (count($nagios_events) > 0) {
      // World's dumbest array merge:
      foreach ($nagios_events AS $ne) {
        $events_array[] = $ne;
      }
    }

    foreach ($events_array as $key => $row) {
      $start_time[$key]  = $row['start_time'];
    }

    // Sort events in reverse chronological order
    array_multisort($start_time, SORT_DESC, $events_array);

    // Default to dashed line unless events_line_type is set to solid
    if ( $conf['overlay_events_line_type'] == "solid" )
      $overlay_events_line_type = "";
    else
      $overlay_events_line_type = ":dashes";

    // Loop through all the events
    $legend_items = array();
    foreach ( $events_array as $id => $event) {
      $evt_start = $event['start_time'];
      // Make sure it's a number
      if ( ! is_numeric($evt_start) ) {
	continue;
      }

      unset($evt_end);
      if (array_key_exists('end_time', $event) && 
          is_numeric($event['end_time']) ) {
        $evt_end = $event['end_time'];
      }

      // If event start is less than start bail out of the loop since 
      // there is nothing more to do since events are sorted in reverse 
      // chronological order and these events are not gonna show up in 
      // the graph
      $in_graph = (($evt_start >= $start) && ($evt_start <= $end)) ||
		   (isset($evt_end) && 
		    ($evt_end >= $start) && 
		    ($evt_start <= $end));
      if (!$in_graph) {
	if ($debug)
	  error_log("Event [$evt_start] does not overlap with graph [$start, $end]");
        continue;
      }

      // Compute the part of the event to be displayed
      $evt_start_in_graph_range = TRUE;
      if ($evt_start < $start) {
        $evt_start = $start;
        $evt_start_in_graph_range = FALSE;
      }
 
      $evt_end_in_graph_range = TRUE;
      if (isset($evt_end)) {
        if ($evt_end > $end) {
	  $evt_end = $end;
	  $evt_end_in_graph_range = FALSE;
	}
      } else
	$evt_end_in_graph_range = FALSE;
	
      if ( preg_match("/" . $event["host_regex"] . "/", $original_command)) {
        if ( $evt_start >= $start ) {
	  // Do we have the end timestamp. 
          if ( !isset($end) || ( $evt_start < $end ) || 'N' == $end ) {
	    // This is a potential vector since this gets added to the 
	    // command line_width TODO: Look over sanitize
            $summary = 
	      isset($event['summary']) ? sanitize($event['summary']) : "";

	    // We need to keep track of summaries so that if we have identical
	    // summaries e.g. Deploy we can use the same color
            if ( array_key_exists($summary, $event_color_map) ) {
	      $color = $event_color_map[$summary];
	      if ($debug)
		error_log("Found existing color: $summary $color");
	      // Reset summary to empty string if it is already present in
	      // the legend
              if (array_key_exists($summary, $legend_items))
	        $summary = "";
              else
                $legend_items[$summary] = TRUE;
            } else {
	      // Haven't seen this summary before. Assign it a color
	      $color_index = count($event_color_map) % $color_count;
	      $color = $conf['graph_colors'][$color_index];
	      $event_color_map[$summary] = $color;
              $event_color_array[] = array('summary' => $summary,
                                           'color' => $color);
	      if ($debug)
		error_log("Adding new event color: $summary $color");
	    }
  
            if (isset($evt_end)) {
              # Attempt to draw a shaded area between start and end points.
              # Force solid line for ranges
              $overlay_events_line_type = "";

	      $start_vrule = '';
              if ($evt_start_in_graph_range)
                $start_vrule = " VRULE:" . $evt_start .
		  "#$color" . $conf['overlay_events_tick_alpha'] .
		  ":\"" . $summary . "\"" . $overlay_events_line_type;
              
                        
	      $end_vrule = '';
              if ($evt_end_in_graph_range)
                $end_vrule = " VRULE:" . $evt_end .
		  "#$color" . $conf['overlay_events_tick_alpha'] .
		  ':""' . $overlay_events_line_type;

              # We need a dummpy DEF statement, because RRDtool is too stupid
              # to plot graphs without a DEF statement.
              # We can't count on a static name, so we have to "find" one.
              if (preg_match("/DEF:['\"]?(\w+)['\"]?=/", $command, $matches)) {
                # stupid rrdtool limitation.
                $area_cdef = 
                  " CDEF:area_$counter=$matches[1],POP," .
                  "TIME,$evt_start,GT,1,UNKN,IF,TIME,$evt_end,LT,1,UNKN,IF,+";
                $area_shade = $color . $conf['overlay_events_shade_alpha'];
                $area = " TICK:area_$counter#$area_shade:1";
                if (!$evt_start_in_graph_range)
                  $area .= ':"' . $summary . '"';
                $command .= "$area_cdef $area $start_vrule $end_vrule";
              } else {
                error_log("No DEF statements found in \$command?!");
              }
              
            } else {
              $command .= " VRULE:" . $evt_start . "#" . $color .
                          ":\"" . $summary . "\"" . $overlay_events_line_type;
            }

	    $counter++;

          } else {
            if ($debug)  
              error_log("Event start [$evt_start] >= graph end [$end]");
          }
        } else {
          if ($debug)
            error_log("Event start [$evt_start] < graph start [$start]");
        }
      } // end of if ( preg_match ...
      else {
        //error_log("Doesn't match host_regex");
      }
    } // end of foreach ( $events_array ...

    unset($events_array);
    if (count($event_color_array) > $initial_event_color_count) {
      $event_color_json = json_encode($event_color_array);
      file_put_contents($conf['overlay_events_color_map_file'],
                        $event_color_json);
    }
  } //End check for array
}

////////////////////////////////////////////////////////////////////////////////
// Add a trend line
////////////////////////////////////////////////////////////////////////////////
if ( $user['trend_line'] ) {
  
    $command .= " VDEF:D2=sum,LSLSLOPE VDEF:H2=sum,LSLINT CDEF:avg2=sum,POP,D2,COUNT,*,H2,+";
    $command .= " 'LINE3:avg2#53E2FF:Trend:dashes'";

}

////////////////////////////////////////////////////////////////////////////////
// Add a trend line
////////////////////////////////////////////////////////////////////////////////
if ( $user['time_shift'] && $graph == "metric" ) {

    preg_match_all("/(DEF|CDEF):(.*)(:AVERAGE )/", 
                 " " . $rrdtool_graph['series'], 
                 $matches);

    // Only do this for metric graphs
    $start = intval(abs(str_replace("s", "", $rrdtool_graph['start'])));
    $offset = 2 * $start;

    $def = str_replace("DEF:'sum'", "DEF:'sum2'", trim($matches[0][0])) . ":start=end-" . $offset;
    
    $command .= " " . $def . " SHIFT:sum2:" . $start;
    $command .= " 'LINE2:sum2#FFE466:Previous " . $range . ":dashes'";
}

////////////////////////////////////////////////////////////////////////////////
// Add warning and critical lines
////////////////////////////////////////////////////////////////////////////////
if ( $warning ) {
  $command .= " 'HRULE:" . $warning . "#FFF600:Warning:dashes'";  
}

if ( $critical ) {
  $command .= " 'HRULE:" . $critical . "#FF0000:Critical:dashes'";
}

if ($debug) {
  error_log("Final rrdtool command:  $command");
}

# Did we generate a command?   Run it.
if($command || $graphite_url) {
    /*Make sure the image is not cached*/
    header ("Expires: Mon, 26 Jul 1997 05:00:00 GMT");   // Date in the past
    header ("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
    header ("Cache-Control: no-cache, must-revalidate");   // HTTP/1.1
    header ("Pragma: no-cache");                     // HTTP/1.0
    if ($debug > 2) {
        header ("Content-type: text/html");
        print "<html><body>";
        
        switch ( $conf['graph_engine'] ) {
      case "flot":
          case "rrdtool":
            print htmlentities( $command );
            break;
          case "graphite":
            print $graphite_url;
            break;
        }        
        print "</body></html>";
    } else {
        header ("Content-type: image/png");
        switch ( $conf['graph_engine'] ) {  
      case "flot":
          case "rrdtool":
            if (strlen($command) < 100000) {
              passthru($command);
            } else {
              $tf = tempnam("/tmp", "ganglia-graph");
              file_put_contents($tf, $command);
              passthru("/bin/bash $tf");
              unlink($tf);
            }
            break;
          case "graphite":
            echo file_get_contents($graphite_url);
            break;
        }        
    }
}

?>
