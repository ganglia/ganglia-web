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
function template ($name) {

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
function hiddenvar ($name, $var) {


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
function uptime($uptimeS) {

   $uptimeD=intval($uptimeS/86400);
   $uptimeS=$uptimeD ? $uptimeS % ($uptimeD*86400) : $uptimeS;
   $uptimeH=intval($uptimeS/3600);
   $uptimeS=$uptimeH ? $uptimeS % ($uptimeH*3600) : $uptimeS;
   $uptimeM=intval($uptimeS/60);
   $uptimeS=$uptimeM ? $uptimeS % ($uptimeM*60) : $uptimeS;

   $s = ($uptimeD!=1) ? "s" : "";
   return sprintf("$uptimeD day$s, %d:%02d:%02d", $uptimeH, $uptimeM, $uptimeS);
}

#------------------------------------------------------------------------------
# Try to determine a nodes location in the cluster. Attempts to find the
# LOCATION attribute first. Requires the host attribute array from
# $hosts[$cluster][$name], where $name is the hostname.
# Returns [-1,-1,-1] if we could not determine location.
#
function findlocation($attrs) {

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
function cluster_sum($name, $metrics) {

   $sum = 0;

   foreach ($metrics as $host => $val)
      {
         if(isset($val[$name]['VAL'])) $sum += $val[$name]['VAL'];
      }

   return $sum;
}

#------------------------------------------------------------------------------
function cluster_min($name, $metrics) {

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
function load_image ($scope, $value) {

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
function load_color ($value) {

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
function node_image ($metrics) {

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
function find_limits($clustername,
		     $nodes,
		     $metricname,
		     $start,
		     $end,
		     $metrics,
		     $conf,
		     $rrd_options) {
  if (!count($metrics))
    return array(0, 0);

  $firsthost = key($metrics);

  if (array_key_exists($metricname, $metrics[$firsthost])) {
    if ($metrics[$firsthost][$metricname]['TYPE'] == "string"
        or $metrics[$firsthost][$metricname]['SLOPE'] == "zero")
      return array(0,0);
  } else {
    return array(0,0);
  }

  $max = 0;
  $min = 0;
  if ($conf['graph_engine'] == "graphite") {
    $target = $conf['graphite_prefix'] .
      $clustername . ".[a-zA-Z0-9]*." . $metricname . ".sum";
    $raw_highestMax = file_get_contents($conf['graphite_url_base'] . "?target=highestMax(" . $target . ",1)&from=" . $start . "&until=" . $end . "&format=json");
    $highestMax = json_decode($raw_highestMax, TRUE);
    $highestMaxDatapoints = $highestMax[0]['datapoints'];
    $maxdatapoints = array();
    foreach ($highestMaxDatapoints as $datapoint) {
      array_push($maxdatapoints, $datapoint[0]);
    }
    $max = max($maxdatapoints);
  } else {
    foreach (array_keys($nodes) as $host) {
      $rrd_dir = "{$conf['rrds']}/$clustername/$host";
      $rrd_file = "$rrd_dir/$metricname.rrd";
      if (file_exists($rrd_file)) {
	if (extension_loaded('rrd')) {
	  $values = rrd_fetch($rrd_file,
			      array("--start", $start,
				    "--end", $end,
				    "AVERAGE"));

	  $values = (array_filter(array_values($values['data']['sum']),
				  'is_finite'));
	  $thismax = max($values);
	  $thismin = min($values);
	} else {
	  $command = $conf['rrdtool'] . " graph /dev/null $rrd_options ".
	    "--start '$start' --end '$end' ".
	    "DEF:limits='$rrd_dir/$metricname.rrd':'sum':AVERAGE ".
	    "PRINT:limits:MAX:%.2lf ".
	    "PRINT:limits:MIN:%.2lf";
	  $out = array();
	  exec($command, $out);
	  if (isset($out[1])) {
	    $thismax = $out[1];
	  } else {
	    $thismax = NULL;
	  }
	  if (!is_numeric($thismax))
	    continue;
	  $thismin = $out[2];
	  if (!is_numeric($thismin))
	    continue;
	}

	if ($max < $thismax)
	  $max = $thismax;

	if ($min > $thismin)
	  $min = $thismin;
	//echo "$host: $thismin - $thismax<br>\n";
      }
    }
  }
  return array($min, $max);
}

#------------------------------------------------------------------------------
#
# Finds the avg of the given cluster & metric from the summary rrds.
#
function find_avg($clustername, $hostname, $metricname) {

    global $conf, $start, $end, $rrd_options;
    $avg = 0;

    if ($hostname)
        $sum_dir = "${conf['rrds']}/$clustername/$hostname";
    else
        $sum_dir = "${conf['rrds']}/$clustername/__SummaryInfo__";
    
    # Confirm that sum_dir exists:
    if ( is_dir($sum_dir) ) {
        $rrd_file = "$sum_dir/$metricname.rrd";
        if ( file_exists($rrd_file) ) {
            $command = $conf['rrdtool'] . " graph /dev/null $rrd_options ".
                "--start $start --end $end ".
                "DEF:avg='$rrd_file':'sum':AVERAGE ".
                "PRINT:avg:AVERAGE:%.2lf ";
            exec($command, $out);
            if ( isset($out[1]) )
              $avg = $out[1];
            else
              $avg = 0;
            #echo "$sum_dir: avg($metricname)=$avg<br>\n";
        }
    }
    return $avg;
}

#------------------------------------------------------------------------------
# Alternate between even and odd row styles.
function rowstyle() {

   static $style;

   if ($style == "even") { $style = "odd"; }
   else { $style = "even"; }

   return $style;
}

#------------------------------------------------------------------------------
# Return a version of the string which is safe for display on a web page.
# Potentially dangerous characters are converted to HTML entities.
# Resulting string is not URL-encoded.
function clean_string( $string ) {

  return htmlentities( $string, ENT_QUOTES | ENT_HTML401 );
}
#------------------------------------------------------------------------------
function sanitize ( $string ) {
  return  escapeshellcmd( clean_string( rawurldecode( $string ) ) ) ;
}

#------------------------------------------------------------------------------
# If arg is a valid number, return it.  Otherwise, return null.
function clean_number( $value ) {

  return is_numeric( $value ) ? $value : null;
}

#------------------------------------------------------------------------------
# Return true if string is a 3 or 6 character hex color.Return false otherwise.
function is_valid_hex_color( $string ) {

  $return_value = false;
  if( strlen( $string ) == 6 || strlen( $string ) == 3 ) {
    if( preg_match( '/^[0-9a-fA-F]+$/', $string ) ) {
      $return_value = true;
    }
  }
  return $return_value;

}

#------------------------------------------------------------------------------
# Allowed view name characters are alphanumeric plus space, dash and underscore
function is_proper_view_name( $string ) {

  if(preg_match("/[^a-zA-z0-9_\-\ ]/", $string)){
    return false;
  } else {
    return true;
  }
}


#------------------------------------------------------------------------------
# Return a shortened version of a FQDN
# if "hostname" is numeric only, assume it is an IP instead
#
function strip_domainname( $hostname ) {
    $postition = strpos($hostname, '.');
    $name = substr( $hostname, 0, $postition );
    if ( FALSE === $postition || is_numeric($name) ) {
        return $hostname;
    } else {
        return $name;
    }
}

#------------------------------------------------------------------------------
# Read a file containing key value pairs
function file_to_hash($filename, $sep) {


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
function file_to_hash_multi($filename, $sep) {


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
function hash_get_distinct_values($h) {

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
function discover_filters() {

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
function filter_init() {

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
function filter_permit($source_name) {

   global $filter_permit_list;

   filter_init();

   # Handle the case where filtering is not active
   if(!is_array($filter_permit_list))
      return true;

   return isset($filter_permit_list[$source_name]);
}

$VIEW_NAME_SEP = '--';

function viewName($view) {
  global $VIEW_NAME_SEP;

  $vn = '';
  if ($view['parent'] != NULL)
    $vn = str_replace('/', $VIEW_NAME_SEP, $view['parent']) . $VIEW_NAME_SEP;
  $vn .= $view['view_name'];
  return $vn;
}

class ViewList {
  private $available_views;

  public function __construct() {
    $this->available_views = get_available_views();
  }

  public function viewExists($view_name) {
    foreach ($this->available_views as $view) {
      if ($view['view_name'] == $view_name) {
	return TRUE;
      }
    }
    return FALSE;
  }

  public function getView($view_name) {
    foreach ($this->available_views as $view) {
      if (viewName($view) == $view_name) {
	return $view;
      }
    }
    return NULL;
  }

  public function removeView($view_name) {
    foreach ($this->available_views as $key => $view) {
      if (viewName($view) == $view_name) {
	unset($this->available_views[$key]);
	return;
      }
    }
  }

  public function getViews() {
    return $this->available_views;
  }
}

function getViewItems($view, $range, $cs, $ce) {
  $view_elements = get_view_graph_elements($view);
  $view_items = array();
  if (count($view_elements) != 0) {
    $custom_time_args = "";
    if ($cs)
      $custom_time_args .= "&cs=" . rawurlencode($cs);
    if ($ce)
      $custom_time_args .= "&ce=" . rawurlencode($ce);

    foreach ($view_elements as $element) {
      $canBeDecomposed = isset($element['aggregate_graph']) ||
	((strpos($element['graph_args'], 'vn=') !== FALSE) &&
	 (strpos($element['graph_args'], 'item_id=') !== FALSE));
      $view_items[] =
	array("legend" => isset($element['hostname']) ?
	      $element['hostname'] : "Aggregate graph",
	      "url_args" => $element['graph_args'] .
	      "&r=" . $range . $custom_time_args,
	      "aggregate_graph" => isset($element['aggregate_graph']) ? 1 : 0,
	      "canBeDecomposed" => $canBeDecomposed ? 1 : 0);
    }
  }
  return $view_items;
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
      if (preg_match("/^view_(.*)\.json$/", $file, $out)) {
	$view_config_file = $conf['views_dir'] . "/" . $file;
	if (!is_file ($view_config_file)) {
	  echo("Can't read view config file " .
	       $view_config_file . ". Please check permissions");
	}

	$view = json_decode(file_get_contents($view_config_file), TRUE);
	// Check whether view type has been specified ie. regex.
	// If not it's standard view
	$view_type =
	  isset($view['view_type']) ? $view['view_type'] : "standard";
	$default_size = isset($view['default_size']) ?
	  $view['default_size'] : $conf['default_view_graph_size'];
	$view_parent =
	  isset($view['parent']) ? $view['parent'] : NULL;
	$common_y_axis =
	  isset($view['common_y_axis']) ? $view['common_y_axis'] : 0;

	$available_views[] = array ("file_name" => $view_config_file,
				    "view_name" => $view['view_name'],
				    "default_size" => $default_size,
				    "items" => $view['items'],
				    "view_type" => $view_type,
				    "parent" => $view_parent,
				    "common_y_axis" => $common_y_axis);
	unset($view);
      }
    }
    closedir($handle);
  }

  foreach ($available_views as $key => $row) {
    $name[$key] = strtolower($row['view_name']);
  }

  @array_multisort($name, SORT_ASC, $available_views);

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

  // set the default size from the view or global config
  if ( isset($conf['default_view_graph_size']) ) {
    $default_size = $conf['default_view_graph_size'];
  }

  if ( isset($view['default_size']) ) {
    $default_size = $view['default_size'];
  }


  switch ( $view['view_type'] ) {
  case "standard":
    // Does view have any items/graphs defined
    if ( count($view['items']) == 0 ) {
      continue;
      // print "No graphs defined for this view. Please add some";
    } else {
      // Loop through graph items
      foreach ($view['items'] as $item_id => $item) {
	// Check if item is an aggregate graph
	if (isset($item['aggregate_graph'])) {
	  foreach ( $item['host_regex'] as $reg_id => $regex_array ) {
	    $graph_args_array[] = "hreg[]=" . urlencode($regex_array["regex"]);
	  }

	  if (isset($item['metric_regex'])) {
	    foreach ( $item['metric_regex'] as $reg_id => $regex_array ) {
	      $graph_args_array[] =
		"mreg[]=" . urlencode($regex_array["regex"]);
              $mreg[] = $regex_array["regex"];
	    }
	  }

          if ( isset($item['size']) ) {
            $graph_args_array[] = "z=" . $item['size'];
          } else {
            $graph_args_array[] = "z=" . $default_size;
          }

          if ( isset($item['sortit']) ) {
            $graph_args_array[] = "sortit=" . $item['sortit'];
          }

	  // If graph type is not specified default to line graph
	  if (isset($item['graph_type']) &&
	      in_array($item['graph_type'], array('line', 'stack')))
	    $graph_args_array[] = "gtype=" . $item['graph_type'];
	  else
	    $graph_args_array[] = "gtype=line";

	  if (isset($item['upper_limit']))
	    $graph_args_array[] = "x=" . $item['upper_limit'];

	  if (isset($item['lower_limit']))
	    $graph_args_array[] = "n=" . $item['lower_limit'];

	  if (isset($item['vertical_label']))
	    $graph_args_array[] = "vl=" . urlencode($item['vertical_label']);

	  if (isset($item['title']))
	    $graph_args_array[] = "title=" . urlencode($item['title']);

	  if (isset($item['metric']))
	    $graph_args_array[] = "m=" . $item['metric'];

          if (isset($item['glegend']))
            $graph_args_array[] = "glegend=" . $item["glegend"];

	  if (isset($item['cluster']))
	    $graph_args_array[] = "c=" . urlencode($item['cluster']);

	  if (isset($item['exclude_host_from_legend_label']))
	    $graph_args_array[] =
	      "lgnd_xh=" . $item['exclude_host_from_legend_label'];

	  $graph_args_array[] = "aggregate=1";
	  $view_elements[] =
	    array("graph_args" => join("&", $graph_args_array),
		  "aggregate_graph" => 1,
		  "name" => isset($item['title']) && $item['title'] != "" ?
		  $item['title'] : $mreg[0] . " Aggregate graph");

	  unset($graph_args_array);

	  // Check whether it's a composite graph/report.
	  // It needs to have an item id
	} else if ($item['item_id']) {
	  $graph_args_array[] = "vn=" . $view['view_name'];
          $graph_args_array[] = "item_id=" . $item['item_id'];

	  $view_elements[] =
	    array("graph_args" => join("&", $graph_args_array));
          unset($graph_args_array);

	  // It's standard metric graph
        } else {
	  // Is it a metric or a graph(report)
	  if (isset($item['metric'])) {
	    $graph_args_array[] = "m=" . $item['metric'];
	    $name = $item['metric'];
	  } else {
	    $graph_args_array[] = "g=" . urlencode($item['graph']);
	    $name = $item['graph'];
	  }
          if ( isset($item['size']) ) {
            $graph_args_array[] = "z=" . $item['size'];
          } else {
            $graph_args_array[] = "z=" . $default_size;
          }

	  if (isset($item['hostname'])) {
            $hostname = $item['hostname'];
            $cluster = array_key_exists($hostname, $index_array['cluster']) ?
	      $index_array['cluster'][$hostname][0] : NULL;
	    $graph_args_array[] = "h=" . urlencode($hostname);
          } else if (isset($item['cluster'])) {
	    $hostname = "";
            $cluster = $item['cluster'];
	  } else {
            $hostname = "";
            $cluster = "";
	  }
	  $graph_args_array[] = "c=" . urlencode($cluster);

	  if (isset($item['upper_limit']))
	    $graph_args_array[] = "x=" . $item['upper_limit'];

	  if (isset($item['lower_limit']))
	    $graph_args_array[] = "n=" . $item['lower_limit'];

	  if (isset($item['vertical_label']))
	    $graph_args_array[] = "vl=" . urlencode($item['vertical_label']);

	  if (isset($item['title']))
	    $graph_args_array[] = "title=" . urlencode($item['title']);

          if (isset($item['warning'])) {
            $view_e['warning'] = $item['warning'];
            $graph_args_array[] = "warn=" . $item['warning'];
          }
          if (isset($item['critical'])) {
            $view_e['critical'] = $item['critical'];
            $graph_args_array[] = "crit=" . $item['critical'];
          }

          if (isset($item['alias'])) {
	    $view_e['alias'] = $item['alias'];
          }

          $view_e["graph_args"] = join("&", $graph_args_array);
          $view_e['hostname'] = $hostname;
          $view_e['cluster'] = $cluster;
          $view_e['name'] = $name;

	  $view_elements[] = $view_e;

          unset($view_e);
	  unset($graph_args_array);
	}
      } // end of foreach ( $view['items']
    } // end of if ( count($view['items'])
    break;

    ///////////////////////////////////////////////////////////////////////////
    // Currently only supports matching hosts.
    ///////////////////////////////////////////////////////////////////////////
  case "regex":
    foreach ($view['items'] as $item_id => $item) {
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
	if (preg_match("/$query/", $host_name)) {
	  $clusters = $index_array['cluster'][$host_name];
	  foreach ($clusters as $cluster) {
	    $graph_args_array[] = "h=" . urlencode($host_name);
	    $graph_args_array[] = "c=" . urlencode($cluster);

	    $view_elements[] =
	      array("graph_args" => $metric_suffix . "&" . join("&", $graph_args_array),
		    "hostname" => $host_name,
		    "cluster" => $cluster,
		    "name" => $name);

	    unset($graph_args_array);
	  }
	}
      }
    } // end of foreach ( $view['items'] as $item_id => $item )
    break;
;
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
  if(!isset($conf['auth_system'])) {
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
function json_prettyprint($json) {

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

    require dirname(__FILE__) . '/lib/cache.php';
} // end function ganglia_cache_metrics


//////////////////////////////////////////////////////////////////////////////
//
//////////////////////////////////////////////////////////////////////////////
function build_aggregate_graph_config ($graph_type,
                                       $line_width,
                                       $hreg,
                                       $mreg,
                                       $glegend,
                                       $exclude_host_from_legend_label,
                                       $sortit = true) {

  global $conf, $index_array, $hosts, $grid, $clusters, $debug, $metrics;

  retrieve_metrics_cache();

  $color_count = count($conf['graph_colors']);

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
        foreach ($index_array['cluster'][$host_name] as $cluster) {
            $host_matches[] = $host_name . "|" . $cluster;
        }
      }
    }
  }

  sort($host_matches);

  if( isset($mreg)) {
    // Find matching metrics
    foreach ( $mreg as $key => $query ) {
      foreach ( $index_array['metrics'] as $metric_key => $m_name ) {
        if ( preg_match("/$query/i", $metric_key, $metric_subexpr ) ) {
          if (isset($metric_subexpr) && count($metric_subexpr) > 1) {
            $legend = array();
            foreach ($metric_subexpr as $m) {
              $legend[] = $m;
            }
	    $metric_matches[$metric_key] = implode(' ', $legend);
          } else {
            $metric_matches[$metric_key] = $metric_key;
          }
        }
      }
    }
    if($sortit) {
      ksort($metric_matches);
    }
  }
  if( isset($metric_matches)){
    $metric_matches_unique = array_unique($metric_matches);
  }
  else{
    $metric_matches_unique = array($metric_name => $metric_name);
  }

  if ( isset($host_matches)) {

    $host_matches_unique = array_unique($host_matches);

    // Create graph_config series from matched hosts and metrics
    foreach ( $host_matches_unique as $key => $host_cluster ) {

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

 	  if (isset($metric_matches) and count($metric_matches_unique) > 1)
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
function retrieve_metrics_cache ( $index = "all" ) {

   global $conf, $index_array, $hosts, $grid, $clusters, $debug, $metrics, $context;

   $index; // PHPCS
   require dirname(__FILE__) . '/lib/cache.php';
   return;
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

function buildMetricMaps($metrics,
			 $always_timestamp,
			 $always_constant,
			 $baseGraphArgs) {
  $metricMap = NULL;
  $metricGroupMap = NULL;
  foreach ($metrics as $name => $metric) {
    if ($metric['TYPE'] == "string" or
	$metric['TYPE'] == "timestamp" or
	(isset($always_timestamp[$name]) and $always_timestamp[$name])) {
      continue;
    } elseif ($metric['SLOPE'] == "zero" or
	      (isset($always_constant[$name]) and $always_constant[$name])) {
      continue;
    } else {
      $graphArgs = $baseGraphArgs . "&amp;v=$metric[VAL]&amp;m=$name";
      # Adding units to graph 2003 by Jason Smith <smithj4@bnl.gov>.
      if ($metric['UNITS']) {
	$encodeUnits = rawurlencode($metric['UNITS']);
	$graphArgs .= "&amp;vl=$encodeUnits";
      }
      if (isset($metric['TITLE'])) {
	$title = $metric['TITLE'];
	$encodeTitle = rawurlencode($title);
	$graphArgs .= "&amp;ti=$encodeTitle";
      }
      // dump_var($graphArgs, "graphArgs");

      $metricMap[$name]['graph'] = $graphArgs;
      $metricMap[$name]['description'] =
	isset($metric['DESC']) ? $metric['DESC'] : '';
      $metricMap[$name]['title'] =
	isset($metric['TITLE']) ? $metric['TITLE'] : '';

      # Setup an array of groups that can be used for sorting in group view
      if ( isset($metrics[$name]['GROUP']) ) {
	$groups = $metrics[$name]['GROUP'];
      } else {
	$groups = array("");
      }

      foreach ($groups as $group) {
	if (isset($metricGroupMap[$group])) {
	  $metricGroupMap[$group] =
	    array_merge($metricGroupMap[$group], (array)$name);
	} else {
	  $metricGroupMap[$group] = array($name);
	}
      }
      continue;
    } // if
  } // foreach
  return array($metricMap, $metricGroupMap);
}

// keep url decoding until it looks good
function heuristic_urldecode($blob) {
  while (substr($blob, 0, 1) == "%") {
    $blob = rawurldecode($blob);
  }
  return $blob;
}

// alternative passthru() implementation to avoid incomplete images shown in
// browsers.
function my_passthru($command) {
  $tf = tempnam('/tmp', 'ganglia-graph.');
  $ret = exec("$command > $tf");
  $size = filesize($tf);
  header("Content-Length: $size");
  $fp = fopen($tf, 'rb');
  fpassthru($fp);
  fclose($fp);
  unlink($tf);
}

// Get timestamp of textual date/time specified relative to gweb timezone
function tzTimeToTimestamp($tzTime) {
  if (isset($_SESSION['tz']) && ($_SESSION['tz'] != '')) {
    $dtz = new DateTimeZone($_SESSION['tz']);
    $dt = new DateTime($tzTime, $dtz);
    return $dt->getTimestamp();
  } else {
    return strtotime($tzTime); // server timezone
  }
}
?>
