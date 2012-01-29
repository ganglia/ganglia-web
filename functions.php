<?php

#
# Some common functions for the Ganglia PHP website.
# Assumes the Gmeta XML tree has already been parsed,
# and the global variables $metrics, $clusters, and $hosts
# have been set.
#

include_once ( dirname(__FILE__) . "/lib/json.php" );

#
# Load event API driver.
#
$driver = ucfirst(strtolower( !isset($conf['overlay_events_provider']) ? "Json" : $conf['overlay_events_provider'] ));
if (file_exists( dirname(__FILE__) . "/lib/Events/Driver_${driver}.php")) {
  include_once( dirname(__FILE__) . "/lib/Events/Driver_${driver}.php" );
}

#------------------------------------------------------------------------------
# Allows a form of inheritance for template files.
# If a file does not exist in the chosen template, the
# default is used. Cuts down on code duplication.
function template ($name)
{
   global $conf;

   $fn = "./templates/${conf['template_name']}/$name";
   $default = "./templates/default/$name";

   if (file_exists($fn)) {
      return $fn;
   }
   else {
      return $default;
   }
}

#------------------------------------------------------------------------------
# Creates a hidden input field in a form. Used to save CGI variables.
function hiddenvar ($name, $var)
{

   $hidden = "";
   if ($var) {
      #$url = rawurlencode($var);
      $hidden = "<input type=\"hidden\" name=\"$name\" value=\"$var\">\n";
   }
   return $hidden;
}

#------------------------------------------------------------------------------
# Gives a readable time string, from a "number of seconds" integer.
# Often used to compute uptime.
function uptime($uptimeS)
{
   $uptimeD=intval($uptimeS/86400);
   $uptimeS=$uptimeD ? $uptimeS % ($uptimeD*86400) : $uptimeS;
   $uptimeH=intval($uptimeS/3600);
   $uptimeS=$uptimeH ? $uptimeS % ($uptimeH*3600) : $uptimeS;
   $uptimeM=intval($uptimeS/60);
   $uptimeS=$uptimeM ? $uptimeS % ($uptimeM*60) : $uptimeS;

   $s = ($uptimeD!=1) ? "s" : "";
   return sprintf("$uptimeD day$s, %d:%02d:%02d",$uptimeH,$uptimeM,$uptimeS);
}

#------------------------------------------------------------------------------
# Try to determine a nodes location in the cluster. Attempts to find the
# LOCATION attribute first. Requires the host attribute array from 
# $hosts[$cluster][$name], where $name is the hostname.
# Returns [-1,-1,-1] if we could not determine location.
#
function findlocation($attrs)
{
   $rack=$rank=$plane=-1;

   $loc=$attrs['LOCATION'];
   if ($loc) {
      sscanf($loc, "%d,%d,%d", $rack, $rank, $plane);
      #echo "Found LOCATION: $rack, $rank, $plane.<br>";
   }
   if ($rack<0 or $rank<0) {
      # Try to parse the host name. Assumes a compute-<rack>-<rank>
      # naming scheme.
      $n=sscanf($attrs['NAME'], "compute-%d-%d", $rack, $rank);
      $plane=0;
   }
   return array($rack,$rank,$plane);
}


#------------------------------------------------------------------------------
function cluster_sum($name, $metrics)
{
   $sum = 0;

   foreach ($metrics as $host => $val)
      {
         if(isset($val[$name]['VAL'])) $sum += $val[$name]['VAL'];
      }

   return $sum;
}

#------------------------------------------------------------------------------
function cluster_min($name, $metrics)
{
   $min = "";

   foreach ($metrics as $host => $val)
      {
         $v = $val[$name]['VAL'];
         if (!is_numeric($min) or $min < $v)
            {
               $min = $v;
               $minhost = $host;
            }
      }
   return array($min, $minhost);
}

#------------------------------------------------------------------------------
#
# A useful function for giving the correct picture for a given
# load. Scope is "node | cluster | grid". Value is 0 <= v <= 1.
function load_image ($scope, $value)
{
   global $conf;

   $scaled_load = $value / $conf['load_scale'];
   if ($scaled_load>1.00) {
      $image = template("images/${scope}_overloaded.jpg");
   }
   else if ($scaled_load>=0.75) {
      $image = template("images/${scope}_75-100.jpg");
   }
   else if ($scaled_load >= 0.50) {
      $image = template("images/${scope}_50-74.jpg");
   }
   else if ($scaled_load>=0.25) {
      $image = template("images/${scope}_25-49.jpg");
   }
   else {
      $image = template("images/${scope}_0-24.jpg");
   }

   return $image;
}

#------------------------------------------------------------------------------
# A similar function that specifies the background color for a graph
# based on load. Quantizes the load figure into 6 sets.
function load_color ($value)
{
   global $conf;

   $scaled_load = $value / $conf['load_scale'];
   if ($scaled_load>1.00) {
      $color = $conf['load_colors']["100+"];
   }
   else if ($scaled_load>=0.75) {
      $color = $conf['load_colors']["75-100"];
   }
   else if ($scaled_load >= 0.50) {
      $color = $conf['load_colors']["50-75"];
   }
   else if ($scaled_load>=0.25) {
      $color = $conf['load_colors']["25-50"];
   }
   else if ($scaled_load < 0.0)
      $color = $conf['load_colors']["down"];
   else {
      $color = $conf['load_colors']["0-25"];
   }

   return $color;
}

#------------------------------------------------------------------------------
#
# Just a useful function to print the HTML for
# the load/death of a cluster node
function node_image ($metrics)
{
   global $hosts_down;

   # More rigorous checking if variables are set before trying to use them.
   if ( isset($metrics['cpu_num']['VAL']) and $metrics['cpu_num']['VAL'] != 0 ) {
		$cpu_num = $metrics['cpu_num']['VAL'];
   } else {
		$cpu_num = 1;
   }

   if ( isset($metrics['load_one']['VAL']) ) {
		$load_one = $metrics['load_one']['VAL'];
   } else {
		$load_one = 0;
   }

   $value = $load_one / $cpu_num;

   # Check if the host is down
   # RFM - Added isset() check to eliminate error messages in ssl_error_log
   if (isset($hosts_down) and $hosts_down)
         $image = template("images/node_dead.jpg");
   else
         $image = load_image("node", $value);

   return $image;
}

#------------------------------------------------------------------------------
#
# Finds the min/max over a set of metric graphs. Nodes is
# an array keyed by host names.
#
function find_limits($nodes, $metricname)
{
   global $conf, $metrics, $clustername, $rrd_dir, $start, $end, $rrd_options;

   if (!count($metrics))
      return array(0, 0);

   $firsthost = key($metrics);
   
   if (array_key_exists($metricname,$metrics[$firsthost])) {
     if ($metrics[$firsthost][$metricname]['TYPE'] == "string"
        or $metrics[$firsthost][$metricname]['SLOPE'] == "zero")
           return array(0,0);
   }
   else {
     return array(0,0);
   }

   $max=0;
   $min=0;
   foreach ( $nodes as $host => $value )
      {
         $out = array();

         $rrd_dir = "${conf['rrds']}/$clustername/$host";
         $rrd_file = "$rrd_dir/$metricname.rrd";
         if (file_exists($rrd_file)) {
            if ( extension_loaded( 'rrd' ) ) {
              $values = rrd_fetch($rrd_file,
                array(
                  "--start", $start,
                  "--end", $end,
                  "AVERAGE"
                )
              );

              $values = (array_filter(array_values($values['data']['sum']), 'is_finite'));
              $thismax = max($values);
              $thismin = min($values);
            } else {
              $command = $conf['rrdtool'] . " graph /dev/null $rrd_options ".
                 "--start $start --end $end ".
                 "DEF:limits='$rrd_dir/$metricname.rrd':'sum':AVERAGE ".
                 "PRINT:limits:MAX:%.2lf ".
                 "PRINT:limits:MIN:%.2lf";
              exec($command, $out);
              if(isset($out[1])) {
                 $thismax = $out[1];
              } else {
                 $thismax = NULL;
              }
              if (!is_numeric($thismax)) continue;
              $thismin=$out[2];
              if (!is_numeric($thismin)) continue;
            }

            if ($max < $thismax) $max = $thismax;

            if ($min > $thismin) $min = $thismin;
            #echo "$host: $thismin - $thismax (now $value)<br>\n";
         }
      }
      
      return array($min, $max);
}

#------------------------------------------------------------------------------
#
# Finds the avg of the given cluster & metric from the summary rrds.
#
function find_avg($clustername, $hostname, $metricname)
{
    global $conf, $start, $end, $rrd_options;
    $avg = 0;

    if ($hostname)
        $sum_dir = "${conf['rrds']}/$clustername/$hostname";
    else
        $sum_dir = "${conf['rrds']}/$clustername/__SummaryInfo__";

    $command = $conf['rrdtool'] . " graph /dev/null $rrd_options ".
        "--start $start --end $end ".
        "DEF:avg='$sum_dir/$metricname.rrd':'sum':AVERAGE ".
        "PRINT:avg:AVERAGE:%.2lf ";
    exec($command, $out);
    if ( isset($out[1]) ) 
      $avg = $out[1];
    else
      $avg = 0;
    #echo "$sum_dir: avg($metricname)=$avg<br>\n";
    return $avg;
}

#------------------------------------------------------------------------------
#
# Generates the colored Node cell HTML. Used in Physical
# view and others. Intended to be used to build a table, output
# begins with "<tr><td>" and ends the same.
function nodebox($hostname, $verbose, $title="", $extrarow="")
{
   global $cluster, $clustername, $metrics, $hosts_up, $GHOME;

   if (!$title) $title = $hostname;

   # Scalar helps choose a load color. The lower it is, the easier to get red.
   # The highest level occurs at a load of (loadscalar*10).
   $loadscalar=0.2;

   # An array of [NAME|VAL|TYPE|UNITS|SOURCE].
   $m=$metrics[$hostname];
   $up = $hosts_up[$hostname] ? 1 : 0;

   # The metrics we need for this node.

   # Give memory in Gigabytes. 1GB = 2^20 bytes.
   $mem_total_gb = $m['mem_total']['VAL']/1048576;
   $load_one=$m['load_one']['VAL'];
   $cpu_speed=round($m['cpu_speed']['VAL']/1000, 2);
   $cpu_num= $m['cpu_num']['VAL'];
   #
   # The nested tables are to get the formatting. Insane.
   # We have three levels of verbosity. At L3 we show
   # everything; at L1 we only show name and load.
   #
   $rowclass = $up ? rowStyle() : "down";
   $host_url=rawurlencode($hostname);
   $cluster_url=rawurlencode($clustername);
   
   $row1 = "<tr><td class=$rowclass>\n".
      "<table width=\"100%\" cellpadding=1 cellspacing=0 border=0><tr>".
      "<td><a href=\"$GHOME/?p=$verbose&amp;c=$cluster_url&amp;h=$host_url\">".
      "$title</a>&nbsp;<br>\n";

   $cpus = $cpu_num > 1 ? "($cpu_num)" : "";
   if ($up)
      $hardware = 
         sprintf("<em>cpu: </em>%.2f<small>G</small> %s ", $cpu_speed, $cpus) .
         sprintf("<em>mem: </em>%.2f<small>G</small>",$mem_total_gb);
   else $hardware = "&nbsp;";

   $row2 = "<tr><td colspan=2>";
   if ($verbose==2)
      $row2 .= $hardware;
   else if ($verbose > 2) {
      $hostattrs = $up ? $hosts_up : $hosts_down;
      $last_heartbeat = $hostattrs[$hostname]['TN'];
      $age = $last_heartbeat > 3600 ? uptime($last_heartbeat) : 
         "${last_heartbeat}s";
      $row2 .= "<font size=\"-2\">Last heartbeat $age</font>";
      $row3 = $hardware;
   }

   #
   # Load box.
   #
   if (!$cpu_num) $cpu_num=1;
   $loadindex = intval($load_one / ($loadscalar*$cpu_num)) + 1;
   # 10 is currently the highest allowed load index.
   $load_class = $loadindex > 10 ? "L10" : "L$loadindex";
   $row1 .= "</td><td align=right valign=top>".
      "<table cellspacing=1 cellpadding=3 border=0><tr>".
      "<td class=$load_class align=right><small>$load_one</small>".
      "</td></tr></table>".
      "</td></tr>\n";

   # Construct cell.
   $cell = $row1;

   if ($extrarow)
      $cell .= $extrarow;

   if ($verbose>1)
      $cell .= $row2;

   $cell .= "</td></tr></table>\n";
   # Tricky.
   if ($verbose>2)
      $cell .= $row3;

   $cell .= "</td></tr>\n";

   return $cell;
}

#------------------------------------------------------------------------------
# Alternate between even and odd row styles.
function rowstyle()
{
   static $style;

   if ($style == "even") { $style = "odd"; }
   else { $style = "even"; }

   return $style;
}

#------------------------------------------------------------------------------
# Organize hosts by rack locations.
# Works with or without "location" host attributes.
function physical_racks()
{
   global $hosts_up, $hosts_down;

   # 2Key = "Rack ID / Rank (order in rack)" = [hostname, UP|DOWN]
   $rack = NULL;

   # If we don't know a node's location, it goes in a negative ID rack.
   $i=1;
   $unknownID= -1;
   if (is_array($hosts_up)) {
      foreach ($hosts_up as $host=>$v) {
         # Try to find the node's location in the cluster.
         list($rack, $rank, $plane) = findlocation($v);

         if ($rack>=0 and $rank>=0 and $plane>=0) {
            $racks[$rack][]=$v['NAME'];
            continue;
         }
         else {
            $i++;
            if (! ($i % 25)) {
               $unknownID--;
            }
            $racks[$unknownID][] = $v['NAME'];
         }
      }
   }
   if (is_array($hosts_down)) {
      foreach ($hosts_down as $host=>$v) {
         list($rack, $rank, $plane) = findlocation($v);
         if ($rack>=0 and $rank>=0 and $plane>=0) {
            $racks[$rack][]=$v['NAME'];
            continue;
         }
         else {
            $i++;
            if (! ($i % 25)) {
               $unknownID--;
            }
            $racks[$unknownID][] = $v['NAME'];
         }
      }
   }

   # Sort the racks array.
   if ($unknownID<-1) { krsort($racks); }
   else {
      ksort($racks);
      reset($racks);
      while (list($rack,) = each($racks)) {
         # In our convention, y=0 is close to the floor. (Easier to wire up)
         krsort($racks[$rack]);
      }
   }
   
   return $racks;
}

#------------------------------------------------------------------------------
# Return a version of the string which is safe for display on a web page.
# Potentially dangerous characters are converted to HTML entities.  
# Resulting string is not URL-encoded.
function clean_string( $string )
{
  return htmlentities( $string );
}
#------------------------------------------------------------------------------
function sanitize ( $string ) {
  return  escapeshellcmd( clean_string( rawurldecode( $string ) ) ) ;
}

#------------------------------------------------------------------------------
# If arg is a valid number, return it.  Otherwise, return null.
function clean_number( $value )
{
  return is_numeric( $value ) ? $value : null;
}

#------------------------------------------------------------------------------
# Return true if string is a 3 or 6 character hex color.Return false otherwise.
function is_valid_hex_color( $string )
{
  $return_value = false;
  if( strlen( $string ) == 6 || strlen( $string ) == 3 ) {
    if( preg_match( '/^[0-9a-fA-F]+$/', $string ) ) {
      $return_value = true;
    }
  }
  return $return_value;
    
}

#------------------------------------------------------------------------------
# Return a shortened version of a FQDN
# if "hostname" is numeric only, assume it is an IP instead
# 
function strip_domainname( $hostname ) {
    $postition = strpos($hostname, '.');
    $name = substr( $hostname , 0, $postition );
    if ( FALSE === $postition || is_numeric($name) ) {
        return $hostname;
    } else {
        return $name;
    }
}

#------------------------------------------------------------------------------
# Read a file containing key value pairs
function file_to_hash($filename, $sep)
{
  
  $lines = file($filename, FILE_IGNORE_NEW_LINES);
  
  foreach ($lines as $line) 
  {
    list($k, $v) = explode($sep, rtrim($line));
    $params[$k] = $v;
  }

  return $params;
}

#------------------------------------------------------------------------------
# Read a file containing key value pairs
# Multiple values permitted for each key
function file_to_hash_multi($filename, $sep)
{
 
  $lines = file($filename);
 
  foreach ($lines as $line)
  {
    list($k, $v) = explode($sep, rtrim($line));
    $params[$k][] = $v;
  }

  return $params;
}

#------------------------------------------------------------------------------
# Obtain a list of distinct values from an array of arrays
function hash_get_distinct_values($h)
{
  $values = array();
  $values_done = array();
  foreach($h as $k => $v)
  {
    if($values_done[$v] != "x")
    {
      $values_done[$v] = "x";
      $values[] = $v;
    } 
  }
  return $values;
}

$filter_defs = array();

#------------------------------------------------------------------------------
# Scan $conf['filter_dir'] and populate $filter_defs
function discover_filters()
{
  global $conf, $filter_defs;

  # Check whether filtering is configured or not
  if(!isset($conf['filter_dir']))
    return;

  if(!is_dir($conf['filter_dir']))
  {
    error_log("discover_filters(): not a directory: ${conf['filter_dir']}");
    return;
  }

  if($dh = opendir($conf['filter_dir']))
  {
    while(($filter_conf_filename = readdir($dh)) !== false) {
      if(!is_dir($filter_conf_filename))
      {
        # Parse the file contents
        $full_filename = "${conf['filter_dir']}/$filter_conf_filename";
        $filter_params = file_to_hash($full_filename, '=');
        $filter_shortname = $filter_params["shortname"];
        $filter_type = $filter_params["type"];
        if($filter_type = "url")
        {
          $filter_data_url = $filter_params['url'];
          $filter_defs[$filter_shortname] = $filter_params;
          $filter_defs[$filter_shortname]["data"] = file_to_hash($filter_data_url, ',');
          $filter_defs[$filter_shortname]["choices"] = hash_get_distinct_values($filter_defs[$filter_shortname]["data"]);
        }
      }
    }
    closedir($dh);
  }
}

$filter_permit_list = NULL;

#------------------------------------------------------------------------------
# Initialise the filter permit list, if necessary
function filter_init()
{
   global $conf, $filter_permit_list, $filter_defs, $choose_filter;

   if(!is_null($filter_permit_list))
   {
      return;
   }

   if(!isset($conf['filter_dir']))
   {
      $filter_permit_list = FALSE;
      return;
   }

   $filter_permit_list = array();
   $filter_count = 0;

   foreach($choose_filter as $filter_shortname => $filter_choice)
   {
      if($filter_choice == "")
         continue; 

      $filter_params = $filter_defs[$filter_shortname];
      if($filter_count == 0)
      {
         foreach($filter_params["data"] as $key => $value)
         {
            if($value == $filter_choice)
               $filter_permit_list[$key] = $key;
         }
      }
      else
      {
         foreach($filter_permit_list as $key => $value)
         {
            $remove_key = TRUE;
            if(isset($filter_params["data"][$key]))
            {
               if($filter_params["data"][$key] == $filter_choice)
               {
                  $remove_key = FALSE;
               } 
            }
            if($remove_key)
            {
               unset($filter_permit_list[$key]);
            }
         }
      }
      $filter_count++;
   }

   if($filter_count == 0)
      $filter_permit_list = FALSE;

}

#------------------------------------------------------------------------------
# Decide whether the given source is permitted by the filters, if any
function filter_permit($source_name)
{
   global $filter_permit_list;

   filter_init();
   
   # Handle the case where filtering is not active
   if(!is_array($filter_permit_list))
      return true;

   return isset($filter_permit_list[$source_name]);
}


///////////////////////////////////////////////////////////////////////////////
// Get all the available views
///////////////////////////////////////////////////////////////////////////////
function get_available_views() {
  global $conf;
  
  /* -----------------------------------------------------------------------
  Find available views by looking in the GANGLIA_DIR/conf directory
  anything that matches view_*.json. Read them all and build a available_views
  array
  ----------------------------------------------------------------------- */
  $available_views = array();

  if ($handle = opendir($conf['views_dir'])) {

      while (false !== ($file = readdir($handle))) {

	if ( preg_match("/^view_(.*)\.json$/", $file, $out) ) {
	  $view_config_file = $conf['views_dir'] . "/" . $file;
	  if ( ! is_file ($view_config_file) ) {
	    echo("Can't read view config file " . $view_config_file . ". Please check permissions");
	  }

	  $view = json_decode(file_get_contents($view_config_file), TRUE);
	  // Check whether view type has been specified ie. regex. If not it's standard view
	  isset($view['view_type']) ? $view_type = $view['view_type'] : $view_type = "standard";
	  $available_views[] = array ( "file_name" => $view_config_file, "view_name" => $view['view_name'],
	    "items" => $view['items'], "view_type" => $view_type);
	  unset($view);

	}
      }

      closedir($handle);
  }

  foreach ($available_views as $key => $row) {
    $name[$key]  = strtolower($row['view_name']);
  }

  @array_multisort($name,SORT_ASC, $available_views);

  return $available_views;

}

///////////////////////////////////////////////////////////////////////////////
// Get image graph URLS
// This function returns an array of graph URLs to be used when rendering the 
// view. It returns only the base ie. cluster, host, metric information. 
// It is up to the caller to add proper size information, time ranges etc.
///////////////////////////////////////////////////////////////////////////////
function get_view_graph_elements($view) {

  global $conf, $index_array;

  retrieve_metrics_cache();

  $view_elements = array();

  switch ( $view['view_type'] ) {

    case "standard":
    // Does view have any items/graphs defined
    if ( sizeof($view['items']) == 0 ) {
      continue;
      // print "No graphs defined for this view. Please add some";
    } else {


      // Loop through graph items
      foreach ( $view['items'] as $item_id => $item ) {

	// Check if item is an aggregate graph
	if ( isset($item['aggregate_graph']) ) {

	  foreach ( $item['host_regex'] as $reg_id => $regex_array ) {
	    $graph_args_array[] = "hreg[]=" . urlencode($regex_array["regex"]);
	  }

	  if ( isset($item['metric_regex']) ) {
	    foreach ( $item['metric_regex'] as $reg_id => $regex_array ) {
	      $graph_args_array[] = "mreg[]=" . urlencode($regex_array["regex"]);
              $mreg[] = $regex_array["regex"];
	    }
	  }

	  // If graph type is not specified default to line graph
	  if ( isset($item['graph_type']) && in_array($item['graph_type'], array('line', 'stack') ) )
	    $graph_args_array[] = "gtype=" . $item['graph_type'];
	  else
	    $graph_args_array[] = "gtype=line";

	  if (isset($item['upper_limit']))
	    $graph_args_array[] = "x=" .$item['upper_limit'];

	  if (isset($item['lower_limit']))
	    $graph_args_array[] = "n=" .$item['lower_limit'];

	  if (isset($item['vertical_label']))
	    $graph_args_array[] = "vl=" .$item['vertical_label'];

	  if (isset($item['title']))
	    $graph_args_array[] = "title=" . urlencode($item['title']);

	  if ( isset($item['metric']) ) {
	    $graph_args_array[] = "m=" . $item['metric'];
	  }

    if ( isset($item['glegend']) )
      $graph_args_array[] = "glegend=" . $item["glegend"];

	  if ( isset($item['cluster']) ) {
	    $graph_args_array[] = "c=" . $item['cluster'];
	  }

	  if ( isset($item['exclude_host_from_legend_label']) ) {
	    $graph_args_array[] = "lgnd_xh=" . $item['exclude_host_from_legend_label'];
	  }
	  
	  $graph_args_array[] = "aggregate=1";
	  $view_elements[] = array ( "graph_args" => join("&", $graph_args_array), 
	      "aggregate_graph" => 1,
	      "name" => isset($item['title']) && $item['title'] != "" ? $item['title'] : $mreg[0] . " Aggregate graph"
	  );

	  unset($graph_args_array);
	// It's standard metric graph
	} else {
	  // Is it a metric or a graph(report)
	  if ( isset($item['metric']) ) {
	    $graph_args_array[] = "m=" . $item['metric'];
	    $name = $item['metric'];
	  } else {
	    $graph_args_array[] = "g=" . urlencode($item['graph']);
	    $name = $item['graph'];
	  }

	  if (isset($item['hostname'])) {
            $hostname = $item['hostname'];
            $cluster = array_key_exists($hostname, $index_array['cluster']) ?
	      $index_array['cluster'][$hostname] : NULL;
          } else if (isset($item['cluster'])) {
	    $hostname = "";
            $cluster = $item['cluster'];
	  } else {
            $hostname = "";
            $cluster = "";
	  }

	  $graph_args_array[] = "h=$hostname";
	  $graph_args_array[] = "c=$cluster";

	  if (isset($item['vertical_label']))
	    $graph_args_array[] = "vl=" .$item['vertical_label'];

	  if (isset($item['title']))
	    $graph_args_array[] = "title=" . urlencode($item['title']);

	  $view_elements[] = array ( "graph_args" => join("&", $graph_args_array), 
	    "hostname" => $hostname,
	    "cluster" => $cluster,
	    "name" => $name
	  );

	  unset($graph_args_array);

	}

      } // end of foreach ( $view['items']
    } // end of if ( sizeof($view['items'])
    break;
    ;;

    ///////////////////////////////////////////////////////////////////////////
    // Currently only supports matching hosts.
    ///////////////////////////////////////////////////////////////////////////
    case "regex":
      foreach ( $view['items'] as $item_id => $item ) {
	// Is it a metric or a graph(report)
	if ( isset($item['metric']) ) {
	  $metric_suffix = "m=" . $item['metric'];
	  $name = $item['metric'];
	} else {
	  $metric_suffix = "g=" . $item['graph'];
	  $name = $item['graph'];
	}

	// Find hosts matching a criteria
	$query = $item['hostname'];
	foreach ( $index_array['hosts'] as $key => $host_name ) {
	  if ( preg_match("/$query/", $host_name ) ) {
	    $cluster = $index_array['cluster'][$host_name];
	    $graph_args_array[] = "h=$host_name";
	    $graph_args_array[] = "c=$cluster";

	    $view_elements[] = array ( "graph_args" => $metric_suffix . "&" . join("&", $graph_args_array), 
	      "hostname" => $host_name,
	      "cluster" => $cluster,
	      "name" => $name);

	    unset($graph_args_array);

	  }
	}
	
      } // end of foreach ( $view['items'] as $item_id => $item )
    break;;
  
  } // end of switch ( $view['view_type'] ) {


  return ($view_elements);

}

function legendEntry($vname, $legend_items) {
  $legend = "";
  if (in_array("now", $legend_items))
    $legend .= "VDEF:{$vname}_last={$vname},LAST ";

  if (in_array("min", $legend_items))
    $legend .= "VDEF:{$vname}_min={$vname},MINIMUM ";

  if (in_array("avg", $legend_items))
    $legend .= "VDEF:{$vname}_avg={$vname},AVERAGE ";

  if (in_array("max", $legend_items))
    $legend .= "VDEF:{$vname}_max={$vname},MAXIMUM ";

  $terminate = FALSE;
  if (in_array("now", $legend_items)) {
    $legend .= "GPRINT:'{$vname}_last':'Now\:%5.1lf%s";
    $terminate = TRUE;
  }

  if (in_array("min", $legend_items)) {
    if ($terminate)
      $legend .= "' ";
    $legend .= "GPRINT:'{$vname}_min':'Min\:%5.1lf%s";
    $terminate = TRUE;
  }

  if (in_array("avg", $legend_items)) {
    if ($terminate)
      $legend .= "' ";
    $legend .= "GPRINT:'{$vname}_avg':'Avg\:%5.1lf%s";
    $terminate = TRUE;
  }

  if (in_array("max", $legend_items)) {
    if ($terminate)
      $legend .= "' ";
    $legend .= "GPRINT:'{$vname}_max':'Max\:%5.1lf%s";
    $terminate = TRUE;
  }
  
  if ($terminate)
    $legend .= "\\l' ";

  return $legend;
}

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
  if ( $series == "" ) 
    $rrdtool_graph[ 'series' ] = 
      'HRULE:1#FFCC33:"No matching metrics detected"';   
  else
    $rrdtool_graph[ 'series' ] = $series;
  
  
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


/**
 * Check if current user has a privilege (view, edit, etc) on a resource.
 * If resource is unspecified, we assume GangliaAcl::ALL.
 *
 * Examples
 *   checkAccess( GangliaAcl::ALL_CLUSTERS, GangliaAcl::EDIT, $conf ); // user has global edit?
 *   checkAccess( GangliaAcl::ALL_CLUSTERS, GangliaAcl::VIEW, $conf ); // user has global view?
 *   checkAccess( $cluster, GangliaAcl::EDIT, $conf ); // user can edit current cluster?
 *   checkAccess( 'cluster1', GangliaAcl::EDIT, $conf ); // user has edit privilege on cluster1?
 *   checkAccess( 'cluster1', GangliaAcl::VIEW, $conf ); // user has view privilege on cluster1?
 */
function checkAccess($resource, $privilege, $conf) {
  
  if(!is_array($conf)) {
    trigger_error('checkAccess: $conf is not an array.', E_USER_ERROR);
  }
  if(!isSet($conf['auth_system'])) {
    trigger_error("checkAccess: \$conf['auth_system'] is not defined.", E_USER_ERROR);
  }
  
  switch( $conf['auth_system'] ) {
    case 'readonly':
      $out = ($privilege == GangliaAcl::VIEW);
      break;
      
    case 'enabled':
      // TODO: 'edit' needs to check for writeability of data directory.  error log if edit is allowed but we're unable to due to fs problems.
      
      $acl = GangliaAcl::getInstance();
      $auth = GangliaAuth::getInstance();
      
      if(!$auth->isAuthenticated()) {
        $user = GangliaAcl::GUEST;
      } else {
        $user = $auth->getUser();
      }
      
      if(!$acl->has($resource)) {
        $resource = GangliaAcl::ALL_CLUSTERS;
      }
      
      $out = false;
      if($acl->hasRole($user)) {
        $out = (bool) $acl->isAllowed($user, $resource, $privilege);
      }
      // error_log("checkAccess() user=$user, resource=$resource, priv=$privilege == $out");
      break;
    
    case 'disabled':
      $out = true;
      break;
    
    default:
      trigger_error( "Invalid value '".$conf['auth_system']."' for \$conf['auth_system'].", E_USER_ERROR );
      return false;
  }
  
  return $out;
}

function viewId($view_name) {
  $id = 'v_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $view_name);
  return $id;
}

///////////////////////////////////////////////////////////////////////////////
// Taken from
// http://au2.php.net/manual/en/function.json-encode.php#80339
// Pretty print JSON 
///////////////////////////////////////////////////////////////////////////////
function json_prettyprint($json) 
{ 
    $tab = "  "; 
    $new_json = ""; 
    $indent_level = 0; 
    $in_string = false; 

    $len = strlen($json); 

    for($c = 0; $c < $len; $c++) 
    { 
        $char = $json[$c]; 
        switch($char) 
        { 
            case '{': 
            case '[': 
                if(!$in_string) 
                { 
                    $new_json .= $char . "\n" . str_repeat($tab, $indent_level+1); 
                    $indent_level++; 
                } 
                else 
                { 
                    $new_json .= $char; 
                } 
                break; 
            case '}': 
            case ']': 
                if(!$in_string) 
                { 
                    $indent_level--; 
                    $new_json .= "\n" . str_repeat($tab, $indent_level) . $char; 
                } 
                else 
                { 
                    $new_json .= $char; 
                } 
                break; 
            case ',': 
                if(!$in_string) 
                { 
                    $new_json .= ",\n" . str_repeat($tab, $indent_level); 
                } 
                else 
                { 
                    $new_json .= $char; 
                } 
                break; 
            case ':': 
                if(!$in_string) 
                { 
                    $new_json .= ": "; 
                } 
                else 
                { 
                    $new_json .= $char; 
                } 
                break; 
            case '"': 
                if($c > 0 && $json[$c-1] != '\\') 
                { 
                    $in_string = !$in_string; 
                } 
            default: 
                $new_json .= $char; 
                break;                    
        } 
    } 

    return $new_json; 
} 

function ganglia_cache_metrics() {
    global $conf, $index_array, $hosts, $grid, $clusters, $debug, $metrics;

    if (!isset($debug)) { $debug = 0; }

    if($conf['cachedata'] == 1 && file_exists($conf['cachefile'])) {
        // check for the cached file
        // snag it and return it if it is still fresh
        $time_diff = time() - filemtime($conf['cachefile']);
        $expires_in = $conf['cachetime'] - $time_diff;
        if( $time_diff < $conf['cachetime']){
                if ( $debug == 1 ) {
                  echo("DEBUG: Fetching data from cache. Expires in " . $expires_in . " seconds.\n");
                }
                $index_array = unserialize(file_get_contents($conf['cachefile']));
        }
    }

    if ( ! isset($index_array) ) {

        if ( $debug == 1 ) {
            echo("DEBUG: Querying GMond for new data\n");
        }
        // Set up for cluster summary
        $context = "index_array";
        include_once $conf['gweb_root'] . "/functions.php";
        include_once $conf['gweb_root'] . "/ganglia.php";
        include_once $conf['gweb_root'] . "/get_ganglia.php";

        foreach ( $index_array['cluster'] as $hostname => $elements ) {
            $hosts[] = $hostname;
        }

        asort($hosts);
        $index_array['hosts'] = $hosts;

        file_put_contents($conf['cachefile'], serialize($index_array));

    }

} // end function ganglia_cache_metrics


//////////////////////////////////////////////////////////////////////////////
//
//////////////////////////////////////////////////////////////////////////////
function build_aggregate_graph_config ($graph_type, 
                                       $line_width, 
                                       $hreg,
                                       $mreg,
                                       $glegend,
                                       $exclude_host_from_legend_label) {

  global $conf, $index_array, $hosts, $grid, $clusters, $debug, $metrics;
  
  retrieve_metrics_cache();
  
  $color_count = sizeof($conf['graph_colors']);

  $graph_config["report_name"]=isset($mreg)  ?  sanitize(implode($mreg))   : NULL;
  $graph_config["title"]=isset($mreg)  ?  sanitize(implode($mreg))   : NULL;
  $graph_config["glegend"]=isset($glegend) ? sanitize($glegend) : "show";

  $counter = 0;

  ///////////////////////////////////////////////////////////////////////////
  // Find matching hosts    
  foreach ( $hreg as $key => $query ) {
    foreach ( $index_array['hosts'] as $key => $host_name ) {
      if ( preg_match("/$query/i", $host_name ) ) {
        // We can have same hostname in multiple clusters
        $matches[] = $host_name . "|" . $index_array['cluster'][$host_name]; 
      }
    }
  } 

  if( isset($mreg)) {
    // Find matching metrics
    foreach ( $mreg as $key => $query ) {
      foreach ( $index_array['metrics'] as $key => $m_name ) {
        if ( preg_match("/$query/i", $key, $metric_subexpr ) ) {
          if (isset($metric_subexpr) && count($metric_subexpr) > 1) {
            $legend = "";
            for ($i = 1; $i < count($metric_subexpr); $i++)
              $legend .= $metric_subexpr[$i];
	    $metric_matches[$key] = $legend;
          } else
            $metric_matches[$key] = $key;
        }
      }
    }
    ksort($metric_matches);
  }
  
  if( isset($metric_matches)){
    $metric_matches_unique = array_unique($metric_matches);
  }
  else{
    $metric_matches_unique = array($metric_name => $metric_name);
  }

  if ( isset($matches)) {

    $matches_unique = array_unique($matches);

    // Create graph_config series from matched hosts and metrics
    foreach ( $matches_unique as $key => $host_cluster ) {

      $out = explode("|", $host_cluster);

      $host_name = $out[0];
      $cluster_name = $out[1];

      foreach ( $metric_matches_unique as $m_name => $legend ) {

        // We need to cycle the available colors
        $color_index = $counter % $color_count;

        // next loop if there is no metric for this hostname
        if( !in_array($host_name, $index_array['metrics'][$m_name]))
          continue;

        $label = '';
        if ($exclude_host_from_legend_label) {
	  $label = $legend;
        } else {
          if ($conf['strip_domainname'] == True )
            $label = strip_domainname($host_name);
          else
            $label = $host_name;

 	  if (isset($metric_matches) and sizeof($metric_matches_unique) > 1)
            $label .= " $legend";
	}

        $graph_config['series'][] = array ( "hostname" => $host_name , "clustername" => $cluster_name,
          "metric" => $m_name,  "color" => $conf['graph_colors'][$color_index], "label" => $label, "line_width" => $line_width, "type" => $graph_type);

        $counter++;

      }
      }
   }

   return $graph_config;

} // function build_aggregate_graph_config () {


//////////////////////////////////////////////////////////////////////////////
//
//////////////////////////////////////////////////////////////////////////////
function retrieve_metrics_cache () {

   global $conf, $index_array, $hosts, $grid, $clusters, $debug, $metrics, $context;

   if($conf['cachedata'] == 1 && file_exists($conf['cachefile'])) {
      // check for the cached file
      // snag it and return it if it is still fresh
      $time_diff = time() - filemtime($conf['cachefile']);
      $expires_in = $conf['cachetime'] - $time_diff;
      if( $time_diff < $conf['cachetime']){
          if ( $debug == 1 ) {
            echo("DEBUG: Fetching data from cache. Expires in " . $expires_in . " seconds.\n");
          }
          $index_array = unserialize(file_get_contents($conf['cachefile']));
      }
   }

   if ( ! isset($index_array) || sizeof($index_array) == 0 ) {

      if ( $debug == 1 ) {
         echo("DEBUG: Querying GMond for new data\n");
      }
      // Set up for cluster summary
      $context = "index_array";
      include_once $conf['gweb_root'] . "/ganglia.php";
      Gmetad($conf['ganglia_ip'], $conf['ganglia_port']);

      foreach ( $index_array['cluster'] as $hostname => $elements ) {
         $hosts[] = $hostname;
      }
      asort($hosts);
      $index_array['hosts'] = $hosts;

      file_put_contents($conf['cachefile'], serialize($index_array));

   }

} // end of function get_metrics_cache () {

function getHostOverViewData($hostname, 
                             $metrics, 
                             $cluster,
                             $hosts_up, 
                             $hosts_down, 
                             $always_timestamp, 
                             $always_constant, 
                             $data) {
  $data->assign("extra", template("host_extra.tpl"));

  $data->assign("host", $hostname);
  $data->assign("node_image", node_image($metrics));

  if ($hosts_up)
    $data->assign("node_msg", "This host is up and running."); 
  else
    $data->assign("node_msg", "This host is down."); 

  # No reason to go on if this node is down.
  if ($hosts_down)
    return;

  foreach ($metrics as $name => $v) {
    if ($v['TYPE'] == "string" or $v['TYPE']=="timestamp" or
        (isset($always_timestamp[$name]) and $always_timestamp[$name])) {
      $s_metrics[$name] = $v;
    } elseif ($v['SLOPE'] == "zero" or
              (isset($always_constant[$name]) and $always_constant[$name])) {
      $c_metrics[$name] = $v;
    }
  }

  # in case this is not defined, set to LOCALTIME so uptime will be 0 in the display
  $boottime = null;
  if (isset($metrics['boottime']['VAL']))
    $boottime = $metrics['boottime']['VAL'];
  else
    $boottime = $cluster['LOCALTIME'];

  # Add the uptime metric for this host. Cannot be done in ganglia.php,
  # since it requires a fully-parsed XML tree. The classic contructor problem.
  $s_metrics['uptime']['TYPE'] = "string";
  $s_metrics['uptime']['VAL'] = uptime($cluster['LOCALTIME'] - $boottime);
  $s_metrics['uptime']['TITLE'] = "Uptime";

  # Add the gmond started timestamps & last reported time (in uptime format) from
  # the HOST tag:
  $s_metrics['gmond_started']['TYPE'] = "timestamp";
  $s_metrics['gmond_started']['VAL'] = $hosts_up['GMOND_STARTED'];
  $s_metrics['gmond_started']['TITLE'] = "Gmond Started";
  $s_metrics['last_reported']['TYPE'] = "string";
  $s_metrics['last_reported']['VAL'] = uptime($cluster['LOCALTIME'] - $hosts_up['REPORTED']);
  $s_metrics['last_reported']['TITLE'] = "Last Reported";

  $s_metrics['ip_address']['TITLE'] = "IP Address";
  $s_metrics['ip_address']['VAL'] = $hosts_up['IP'];
  $s_metrics['ip_address']['TYPE'] = "string";
  $s_metrics['location']['TITLE'] = "Location";
  $s_metrics['location']['VAL'] = $hosts_up['LOCATION'];
  $s_metrics['location']['TYPE'] = "string";

  # String metrics
  if (is_array($s_metrics)) {
    $s_metrics_data = array();
    ksort($s_metrics);
    foreach ($s_metrics as $name => $v) {
      # RFM - If units aren't defined for metric, make it be the empty string
      ! array_key_exists('UNITS', $v) and $v['UNITS'] = "";
      if (isset($v['TITLE'])) {
        $s_metrics_data[$name]["name"] = $v['TITLE'];
      } else {
        $s_metrics_data[$name]["name"] = $name;
      }
      if ($v['TYPE']=="timestamp" or 
          (isset($always_timestamp[$name]) and $always_timestamp[$name])) {
        $s_metrics_data[$name]["value"] = date("r", $v['VAL']);
      } else {
        $s_metrics_data[$name]["value"] = $v['VAL'] . " " . $v['UNITS'];
      }
    }
  }
  $data->assign("s_metrics_data", $s_metrics_data);

  # Constant metrics.
  $c_metrics_data = null;
  if (isset($c_metrics) and is_array($c_metrics)) {
    $c_metrics_data = array();
    ksort($c_metrics);
    foreach ($c_metrics as $name => $v) {
      if (isset($v['TITLE']))  {
        $c_metrics_data[$name]["name"] =  $v['TITLE'];
      } else {
        $c_metrics_data[$name]["name"] = $name;
      }
      $c_metrics_data[$name]["value"] = "$v[VAL] $v[UNITS]";
    }
  }
  $data->assign("c_metrics_data", $c_metrics_data);
}

function get_load($host, $metrics) {
  if (isset($metrics[$host]["cpu_num"]['VAL']) and 
      $metrics[$host]["cpu_num"]['VAL'] != 0 ) {
    $cpus = $metrics[$host]["cpu_num"]['VAL'];
  } else {
    $cpus = 1;
  }

  if (isset($metrics[$host]["load_one"]['VAL']) ){
    $load_one = $metrics[$host]["load_one"]['VAL'];
  } else {
    $load_one = 0;
  }
  $load = ((float) $load_one) / $cpus;
  return $load;
}

function get_cluster_load_pie($showhosts, 
			      $hosts_up, 
			      $hosts_down, 
			      $user, 
			      $conf,
			      $metrics, 
			      $cluster,
			      $name,
			      $data) {
  if ($showhosts) {
    $percent_hosts = array();
    foreach ($hosts_up as $host => $val) {
      // If host_regex is defined
      if (isset($user['host_regex']) && 
          ! preg_match("/" .$user['host_regex'] . "/", $host))
        continue;
      
      $load = get_load($host, $metrics);

      if (isset($percent_hosts[load_color($load)])) { 
        $percent_hosts[load_color($load)] += 1;
      } else {
        $percent_hosts[load_color($load)] = 1;
      }
    }
         
    foreach ($hosts_down as $host => $val) {
      $load = -1.0;
      if (isset($percent_hosts[load_color($load)])) {
        $percent_hosts[load_color($load)] += 1;
      } else {
        $percent_hosts[load_color($load)] = 1;
      }
    }

    // Show pie chart of loads
    $pie_args = "title=" . rawurlencode("Cluster Load Percentages");
    $pie_args .= "&amp;size=250x150";
    foreach ($conf['load_colors'] as $name => $color) {
      if (!array_key_exists($color, $percent_hosts))
        continue;
      $n = $percent_hosts[$color];
      $name_url = rawurlencode($name);
      $pie_args .= "&amp;$name_url=$n,$color";
    }
    $data->assign("pie_args", $pie_args);
  } else {
    // Show pie chart of hosts up/down
    $pie_args = "title=" . rawurlencode("Host Status");
    $pie_args .= "&amp;size=250x150";
    $up_color = $conf['load_colors']["25-50"];
    $down_color = $conf['load_colors']["down"];
    $pie_args .= "&amp;Up=$cluster[HOSTS_UP],$up_color";
    $pie_args .= "&amp;Down=$cluster[HOSTS_DOWN],$down_color";
    $data->assign("pie_args", $pie_args);
  }
}

function get_host_metric_graphs($showhosts, 
                                $hosts_up, 
                                $hosts_down, 
                                $user, 
                                $conf,
                                $metrics, 
                                $metricname,
                                $sort,
                                $clustername,
                                $get_metric_string,
                                $cluster,
                                $always_timestamp,
                                $reports,
                                $clustergraphsize,
                                $range,
                                $cs,
                                $ce,
                                $vlabel,
			        $data) {
  $sorted_hosts = array();
  $down_hosts = array();
  if ($showhosts) {
    foreach ($hosts_up as $host => $val) {
      // If host_regex is defined
      if (isset($user['host_regex']) && 
          ! preg_match("/" .$user['host_regex'] . "/", $host))
        continue;
      
      $load = get_load($host, $metrics);
      $host_load[$host] = $load;

      if ($metricname == "load_one")
        $sorted_hosts[$host] = $load;
      else if (isset($metrics[$host][$metricname]))
        $sorted_hosts[$host] = $metrics[$host][$metricname]['VAL'];
      else
        $sorted_hosts[$host] = "";
    } // foreach hosts_up
         
    foreach ($hosts_down as $host => $val) {
      $down_hosts[$host] = -1.0;
    }

    $data->assign("node_legend", 1);
  }

  if (!is_array($hosts_up) or !$showhosts)
    return;

  switch ($sort) {
  case "descending":
    arsort($sorted_hosts);
    break;
  case "by name":
    uksort($sorted_hosts, "strnatcmp");
    break;
  default:
  case "ascending":
    asort($sorted_hosts);
    break;
  }

  $sorted_hosts = array_merge($down_hosts, $sorted_hosts);

  if (isset($user['max_graphs']))
    $max_graphs = $user['max_graphs'];
  else
    $max_graphs = $conf['max_graphs'];

  // First pass to find the max value in all graphs for this
  // metric. The $start,$end variables comes from get_context.php, 
  // included in index.php.
  // Do this only if person has not selected a maximum set of graphs to display
  if ($max_graphs == 0)
    list($min, $max) = find_limits($sorted_hosts, $metricname);

  // Second pass to output the graphs or metrics.
  $i = 1;

  // Initialize overflow list
  $overflow_list = array();
  $overflow_counter = 1;
  $cluster_url = rawurlencode($clustername);
  $size = isset($clustergraphsize) ? $clustergraphsize : 'small';
  if ($conf['hostcols'] == 0) // enforce small size in multi-host report
    $size = 'small';
  // set host zoom class based on the size of the graph shown
  if (isset($conf['zoom_support']) && $conf['zoom_support'] === true)
    $additional_host_img_html_args = "class=host_${size}_zoomable";

  foreach ($sorted_hosts as $host => $value) {
    $host_url = rawurlencode($host);
    
    $host_link="\"?c=$cluster_url&amp;h=$host_url&amp;$get_metric_string\"";
    $textval = "";

    //echo "$host: $value, ";

    if (isset($hosts_down[$host]) and $hosts_down[$host]) {
      $last_heartbeat = $cluster['LOCALTIME'] - $hosts_down[$host]['REPORTED'];
      $age = $last_heartbeat > 3600 ? 
        uptime($last_heartbeat) : "${last_heartbeat}s";

      $class = "down";
      $textval = "down <br>&nbsp;<font size=\"-2\">Last heartbeat $age ago</font>";
    } else {
      if (isset($metrics[$host][$metricname]))
        $val = $metrics[$host][$metricname];
      else
        $val = NULL;
      $class = "metric";
      
      if ($val['TYPE']=="timestamp" or 
          (isset($always_timestamp[$metricname]) and
           $always_timestamp[$metricname])) {
        $textval = date("r", $val['VAL']);
      } elseif ($val['TYPE']=="string" or 
                $val['SLOPE']=="zero" or
                (isset($always_constant[$metricname]) and
                 $always_constant[$metricname] or
                 ($max_graphs > 0 and $i > $max_graphs))) {
        if (isset($reports[$metricname]) and $reports[$metricname])
          // No "current" values available for reports
          $textval = "N/A";
        else
          $textval = "$val[VAL]";
        if (isset($val['UNITS']))
          $textval .= " $val[UNITS]";
      }
    }
    
    $graphargs = "z=$size&amp;c=$cluster_url&amp;h=$host_url";
    
    if (isset($host_load[$host])) {
      $load_color = load_color($host_load[$host]);
      $graphargs .= "&amp;l=$load_color&amp;v=$val[VAL]";
    }
    $graphargs .= "&amp;r=$range&amp;su=1&amp;st=$cluster[LOCALTIME]";
    if ($cs)
      $graphargs .= "&amp;cs=" . rawurlencode($cs);
    if ($ce)
      $graphargs .= "&amp;ce=" . rawurlencode($ce);
    
    if ($showhosts == 1 && $max_graphs == 0 )
      $graphargs .= "&amp;x=$max&amp;n=$min";
    
    if (isset($vlabel))
      $graphargs .= "&amp;vl=" . urlencode($vlabel);
    
    if ($textval) {
      $cell = "<td class=$class>" .
	"<b><a href=$host_link>$host</a></b><br>" .
	"<i>$metricname:</i> <b>$textval</b></td>";
    } else {
      $cell = "<td><div><font style='font-size: 8px'>$host</font><br><a href=$host_link><img $additional_host_img_html_args src=\"./graph.php?";
      $cell .= (isset($reports[$metricname]) and 
                $reports[$metricname]) ? "g=$metricname" : "m=$metricname";
      $cell .= "&amp;$graphargs\" title=\"$host\" border=0 style=\"padding:2px;\"></a></div></td>";
    }

    if ($conf['hostcols'] == 0) {
      $pre = "<td><a href=$host_link><img src=\"./graph.php?g=";
      $post = "&amp;$graphargs\" $additional_host_img_html_args title=\"$host\" border=0 style=\"padding:2px;\"></a></td>";
      $cell .= $pre . "load_report" . $post;
      $cell .= $pre . "mem_report" . $post;
      $cell .= $pre . "cpu_report" . $post;
      $cell .= $pre . "network_report" . $post;
    }
    
    // Check if max_graphs is set. 
    // If it put cells in an overflow list since that one is hidden by default
    if ($max_graphs > 0 and $i > $max_graphs ) {
      $overflow_list[$host]["metric_image"] = $cell;
      if (! ($overflow_counter++ % $conf['hostcols']) ) {
        $overflow_list[$host]["br"] = "</tr><tr>";
      } else {
        $overflow_list[$host]["br"] = "";
      }
    } else {
      $sorted_list[$host]["metric_image"] = $cell;
      if (! ($i++ % $conf['hostcols']) ) {
        $sorted_list[$host]["br"] = "</tr><tr>";
      } else {
        $sorted_list[$host]["br"] = "";
      }
    } // end of if ($max_graphs > 0 and $i > $max_graphs ) {
  } // foreach sorted_hosts
  
  $data->assign("sorted_list", $sorted_list);
  
  // If there is an overflow list
  if (sizeof($overflow_list) > 0) {
    $data->assign("overflow_list_header", '<p><table width=80%><tr><td align=center class=metric>
    <a href="#" id="overflow_list_button"onclick="$(\'#overflow_list\').toggle();" class="button ui-state-default ui-corner-all" title="Toggle overflow list">Show more hosts (' 
    . ($overflow_counter - 1) .')</a>
    </td></tr></table>
    <div style="display: none;" id="overflow_list"><table>
    <tr>
    ');
    $data->assign("overflow_list_footer", "</div></tr></table></div>");
  } else {
    $data->assign("overflow_list_header", "");
    $data->assign("overflow_list_footer", "");
  }
  $data->assign("overflow_list", $overflow_list);
}

function get_cluster_overview($showhosts, 
                              $metrics,
                              $cluster,
                              $range, 
                              $clustername, 
                              $data) {
  $cpu_num = !$showhosts ? $metrics["cpu_num"]['SUM'] : 
                           cluster_sum("cpu_num", $metrics);
  $data->assign("cpu_num", $cpu_num);

  if (isset($cluster['HOSTS_UP'])) {
    $data->assign("num_nodes", intval($cluster['HOSTS_UP']));
  } else {
    $data->assign("num_nodes", 0);
  }

  if (isset($cluster['HOSTS_DOWN'])) {
    $data->assign("num_dead_nodes", intval($cluster['HOSTS_DOWN']));
  } else {
    $data->assign("num_dead_nodes", 0);
  }

  $load_one_sum = !$showhosts ? $metrics["load_one"]['SUM'] : 
                                cluster_sum("load_one", $metrics);
  $load_five_sum = !$showhosts ? $metrics["load_five"]['SUM'] : 
                                 cluster_sum("load_five", $metrics);
  $load_fifteen_sum = !$showhosts ? $metrics["load_fifteen"]['SUM'] : 
                                    cluster_sum("load_fifteen", $metrics);

  if (!$cpu_num) 
    $cpu_num = 1;
  $cluster_load15 = sprintf("%.0f", 
                            ((double) $load_fifteen_sum / $cpu_num) * 100);
  $cluster_load5 = sprintf("%.0f", ((double) $load_five_sum / $cpu_num) * 100);
  $cluster_load1 = sprintf("%.0f", ((double) $load_one_sum / $cpu_num) * 100);
  $data->assign("cluster_load", 
                "$cluster_load15%, $cluster_load5%, $cluster_load1%");

  $avg_cpu_num = find_avg($clustername, "", "cpu_num");
  if ($avg_cpu_num == 0) 
    $avg_cpu_num = 1;
  $cluster_util = sprintf("%.0f", 
	  		  ((double) find_avg($clustername, 
                                             "",
					     "load_one") / $avg_cpu_num ) * 100);
  $data->assign("cluster_util", "$cluster_util%");
  $data->assign("range", $range);
}

?>
