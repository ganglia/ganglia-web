<?php
#
# Parses ganglia XML tree.
#
# The arrays defined in the first part of this file to hold XML info. 
#
# sacerdoti: These are now context-sensitive, and hold only as much
# information as we need to make the page.
#

$gweb_root = dirname(__FILE__);

include_once($gweb_root . "/version.php");
include_once("./global.php");

$error="";

# Gives time in seconds to retrieve and parse XML tree. With subtree-
# capable gmetad, should be very fast in all but the largest cluster configurations.
$parsetime = 0;

# 2key = "Source Name" / "NAME | AUTHORITY | HOSTS_UP ..." = Value.
$grid = array();

# 1Key = "NAME | LOCALTIME | HOSTS_UP | HOSTS_DOWN" = Value.
$cluster = array();

# 2Key = "Cluster Name / Host Name" ... Value = Array of Host Attributes
$hosts_up = array();
# 2Key = "Cluster Name / Host Name" ... Value = Array of Host Attributes
$hosts_down = array();

# Context dependant structure.
$metrics = array();

# 1Key = "Component" (gmetad | gmond) = Version string
$version = array();

# The web frontend version, from conf.php.
$version["webfrontend"] = $GLOBALS["ganglia_version"];

# Get rrdtool version
$rrdtool_version = array();
exec($conf['rrdtool'], $rrdtool_version);
$rrdtool_version = explode(" ", $rrdtool_version[0]);
$rrdtool_version = $rrdtool_version[1];
$version["rrdtool"] = "$rrdtool_version";
 
# The name of our local grid.
$self = " ";

$index_array = array();

# Returns true if the host is alive. Works for both old and new gmond sources.
function host_alive($host, $cluster) {

   $TTL = 60;

   if ($host['TN'] and $host['TMAX']) {
      if ($host['TN'] > $host['TMAX'] * 4)
         return FALSE;
         $host_up = FALSE;
   }
   else {      # The old method.
      if (abs($cluster["LOCALTIME"] - $host['REPORTED']) > (4*$TTL))
         return FALSE;
   }
   return TRUE;
}


# Called with <GANGLIA_XML> attributes.
function preamble($ganglia) {

   global $version;

   $component = $ganglia['SOURCE'];
   $version[$component] = $ganglia['VERSION'];
}


function start_meta ($parser, $tagname, $attrs) {

   global $metrics, $grid, $self, $debug;
   static $sourcename, $metricname;

   $parser; // PHPCS
   if ($debug) print "<br/>DEBUG: parser start meta [$tagname]\n";

   switch ($tagname)
      {
         case "GANGLIA_XML":
            preamble($attrs);
            break;

         case "GRID":
         case "CLUSTER":
            if ($debug) print "<br/>DEBUG: parser start meta GRID|CLUSTER\n";
            # Our grid will be first.
            if (!$sourcename) $self = $attrs['NAME'];

            $sourcename = $attrs['NAME'];
            $grid[$sourcename] = $attrs;

            # Identify a grid from a cluster.
            $grid[$sourcename][$tagname] = 1;
            break;

         case "METRICS":
            $metricname = rawurlencode($attrs['NAME']);
            $metrics[$sourcename][$metricname] = $attrs;
            break;

         case "HOSTS":
            $grid[$sourcename]['HOSTS_UP'] = $attrs['UP'];
            $grid[$sourcename]['HOSTS_DOWN'] = $attrs['DOWN'];
            break;

         default:
            break;
      }
}


function start_cluster ($parser, $tagname, $attrs) {

   global $metrics, $cluster, $self, $grid, $hosts_up, $hosts_down, $debug;
   static $hostname;

   $parser; // PHPCS
   if ($debug) print "<br/>DEBUG: parser start cluster [$tagname]\n";
   switch ($tagname)
      {
         case "GANGLIA_XML":
            preamble($attrs);
            break;
         case "GRID":
            $self = $attrs['NAME'];
            $grid = $attrs;
            break;

         case "CLUSTER":
            $cluster = $attrs;
            break;

         case "HOST":
            $hostname = $attrs['NAME'];

            if (host_alive($attrs, $cluster))
               {

		  isset($cluster['HOSTS_UP']) or $cluster['HOSTS_UP'] = 0;
                  $cluster['HOSTS_UP']++;
                  $hosts_up[$hostname] = $attrs;
               }
            else
               {
		  isset($cluster['HOSTS_DOWN']) or $cluster['HOSTS_DOWN'] = 0;
                  $cluster['HOSTS_DOWN']++;
                  $hosts_down[$hostname] = $attrs;
               }
            # Pseudo metrics - add useful HOST attributes like gmond_started & last_reported to the metrics list:
            $metrics[$hostname]['gmond_started']['NAME'] = "GMOND_STARTED";
            $metrics[$hostname]['gmond_started']['VAL'] = $attrs['GMOND_STARTED'];
            $metrics[$hostname]['gmond_started']['TYPE'] = "timestamp";
            $metrics[$hostname]['last_reported']['NAME'] = "REPORTED";
            $metrics[$hostname]['last_reported']['VAL'] = uptime($cluster['LOCALTIME'] - $attrs['REPORTED']);
            $metrics[$hostname]['last_reported']['TYPE'] = "string";
            $metrics[$hostname]['last_reported_uptime_in_sec']['VAL'] = $cluster['LOCALTIME'] - $attrs['REPORTED'];
            $metrics[$hostname]['last_reported_timestamp']['NAME'] = "REPORTED TIMESTAMP";
            $metrics[$hostname]['last_reported_timestamp']['VAL'] = $attrs['REPORTED'];
            $metrics[$hostname]['last_reported_timestamp']['TYPE'] = "uint32";
            $metrics[$hostname]['ip_address']['NAME'] = "IP";
            $metrics[$hostname]['ip_address']['VAL'] = $attrs['IP'];
            $metrics[$hostname]['ip_address']['TYPE'] = "string";
            $metrics[$hostname]['location']['NAME'] = "LOCATION";
            $metrics[$hostname]['location']['VAL'] = $attrs['LOCATION'];
            $metrics[$hostname]['location']['TYPE'] = "string";
            break;

         case "METRIC":
            $metricname = rawurlencode($attrs['NAME']);
            $metrics[$hostname][$metricname] = $attrs;
            break;

         default:
            break;
      }

}

function start_everything ($parser, $tagname, $attrs) {

   global $index_array, $hosts, $metrics, $cluster, $self, $grid, $hosts_up, $hosts_down, $debug;
   static $hostname, $cluster_name;

   $parser; // PHPCS
   if ($debug) print "<br/>DEBUG: parser start everything [$tagname]\n";

   switch ($tagname)
      {
         case "GANGLIA_XML":
            preamble($attrs);
            break;
         case "GRID":
            $self = $attrs['NAME'];
            $grid = $attrs;
            break;

         case "CLUSTER":
#	    $cluster = $attrs;
            $cluster_name = $attrs['NAME'];
            break;

         case "HOST":
            $hostname = $attrs['NAME'];
            # For some reason this occasionally will end up marking live hosts not alive
            # causing them to miss out from aggregate graphs
            # if (host_alive($attrs, $cluster_name))
            $index_array['cluster'][$hostname][] = $cluster_name;

         case "METRIC":
            $metricname = rawurlencode($attrs['NAME']);
	    if ( $metricname != $hostname ) 
	      $index_array['metrics'][$metricname][] = $hostname;
            break;

         default:
            break;
      }

}

function start_cluster_summary ($parser, $tagname, $attrs) {

   global $metrics, $cluster, $self, $grid;

   $parser; // PHPCS

   switch ($tagname)
      {
         case "GANGLIA_XML":
            preamble($attrs);
            break;
         case "GRID":
            $self = $attrs['NAME'];
            $grid = $attrs;
         case "CLUSTER":
            $cluster = $attrs;
            break;
         
         case "HOSTS":
            $cluster['HOSTS_UP'] = $attrs['UP'];
            $cluster['HOSTS_DOWN'] = $attrs['DOWN'];
            break;
            
         case "METRICS":
            $metrics[$attrs['NAME']] = $attrs;
            break;
            
         default:
            break;
      }
}


function start_host ($parser, $tagname, $attrs) {

   global $metrics, $cluster, $hosts_up, $hosts_down, $self, $grid;
   static $metricname;

   $parser; // PHPCS

   switch ($tagname)
      {
         case "GANGLIA_XML":
            preamble($attrs);
            break;
         case "GRID":
            $self = $attrs['NAME'];
            $grid = $attrs;
            break;
         case "CLUSTER":
            $cluster = $attrs;
            break;

         case "HOST":
            if (host_alive($attrs, $cluster))
               $hosts_up = $attrs;
            else
               $hosts_down = $attrs;
            break;

         case "METRIC":
            $metricname = rawurlencode($attrs['NAME']);
            $metrics[$metricname] = $attrs;
            break;

         case "EXTRA_DATA":
            break;

         case "EXTRA_ELEMENT":
            if ( isset($attrs['NAME']) && isset($attrs['VAL']) && ($attrs['NAME'] == "GROUP")) { 
               if ( isset($metrics[$metricname]['GROUP']) ) {
                  $group_array = array_merge( (array)$attrs['VAL'], $metrics[$metricname]['GROUP'] );
               } else {
                  $group_array = (array)$attrs['VAL'];
               }
               $attribarray = array($attrs['NAME'] => $attrs['VAL']);
               $metrics[$metricname] = array_merge($metrics[$metricname], $attribarray);
               $metrics[$metricname]['GROUP'] = $group_array;
            } else {
               $attribarray = array($attrs['NAME'] => $attrs['VAL']);
               $metrics[$metricname] = array_merge($metrics[$metricname], $attribarray);
            }
            break;

         default:
            break;
      }
}


function end_all ($parser, $tagname) {


}


function Gmetad () {

   global $conf, $error, $parsetime, $clustername, $hostname, $context, $debug;
   
   if ($debug) print "<br/>\n";
   # Parameters are optionalshow
   # Defaults...
   $ip = $conf['ganglia_ip'];
   $port = $conf['ganglia_port'];
   $timeout = 3.0;
   $errstr = "";
   $errno  = "";
   
   //TODO: all calls to this function (in get_ganglia.php) supply 2 args.  Why do we make that optional?
   switch( func_num_args() )
      {
         case 2:
            $port = func_get_arg(1);
         case 1:
            $ip = func_get_arg(0);
      }

   if ($debug) print "<br/>DEBUG: Creating parser\n";
   if ( in_array($context, $SKIP_GMETAD_CONTEXTS) ) {
      return TRUE;
   }
   $parser = xml_parser_create();
   $strip_extra = $conf['strip_extra'];
   switch ($context)
      {
         case "meta":
         case "control":
         case "tree":
         default:
            xml_set_element_handler($parser, "start_meta", "end_all");
            $request = "/?filter=summary";
            break;
         case "physical":
         case "cluster":
            xml_set_element_handler($parser, "start_cluster", "end_all");
            $request = "/$clustername";
            break;
         case "index_array":
         case "views":
            xml_set_element_handler($parser, "start_everything", "end_all");
            $request = "/";
            break;
         case "cluster-summary":
            xml_set_element_handler($parser, "start_cluster_summary", "end_all");
            $request = "/$clustername?filter=summary";
            break;
         case "node":
         case "host":
            xml_set_element_handler($parser, "start_host", "end_all");
            $request = "/$clustername/$hostname";
            $strip_extra = false;
            break;
      }

  $fp = fsockopen( $ip, $port, $errno, $errstr, $timeout);
   if (!$fp)
      {
         $error = "fsockopen error: $errstr";
         if ($debug) print "<br/>DEBUG: $error\n";
         return FALSE;
      }

   if ($port == 8649)
      {
         # We are connecting to a gmond. Non-interactive.
         xml_set_element_handler($parser, "start_cluster", "end_all");
      }
   else
      {
         $request .= "\n";
         $rc = fputs($fp, $request);
         if (!$rc)
            {
               $error = "Could not sent request to gmetad: $errstr";
               if ($debug) print "<br/>DEBUG: $error\n";
               return FALSE;
            }
      }

   $start = gettimeofday();

   while(!feof($fp))
      {
         $data = fread($fp, 16384);
         if($strip_extra) $data = preg_replace('/<EXTRA_DATA>.*?<\/EXTRA_DATA>/s', '', $data);
         if (!xml_parse($parser, $data, feof($fp)))
            {
               $error = sprintf("XML error: %s at %d",
                  xml_error_string(xml_get_error_code($parser)),
                  xml_get_current_line_number($parser));
               if ($debug) print "<br/>DEBUG: $error\n";
               fclose($fp);
               return FALSE;
            }
      }
   fclose($fp);

   $end = gettimeofday();
   $parsetime = ($end['sec'] + $end['usec']/1e6) - ($start['sec'] + $start['usec']/1e6);

   if ($debug) print "<br/>DEBUG: theoretically completed gmetad parsing\n";
   return TRUE;
}

?>
