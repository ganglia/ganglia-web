<?php
#
# Displays the cluster in a physical view. Cluster nodes in
# this view are located by Rack, Rank, and Plane in the physical
# cluster.
#
# Originally written by Federico Sacerdoti <fds@sdsc.edu>
# Part of the Ganglia Project, All Rights Reserved.
#

# Called from index.php, so cluster and xml tree vars
# ($metrics, $clusters, $hosts) are set, and header.php
# already called.

$tpl = new Dwoo_Template_File( template("physical_view.tpl") );
$data = new Dwoo_Data();
$data->assign("cluster", $clustername);
$cluster_url=rawurlencode($clustername);
$data->assign("cluster_url", $cluster_url);

$verbosity_levels = array('3' => "", '2' => "", '1' => "");

# Assign the verbosity level. Can take the value of the 'p' CGI variable.
$verbose = $physical ? $physical : 2;

$verbosity_levels[$verbose] = "checked";
$data->assign("verbosity_levels", $verbosity_levels);

#
# Give the capacities of this cluster: total #CPUs, Memory, Disk, etc.
#
$CPUs = cluster_sum("cpu_num", $metrics);
# Divide by 1024^2 to get Memory in GB.
$Memory = sprintf("%.1f GB", cluster_sum("mem_total", $metrics)/(float)1048576);
$Disk = cluster_sum("disk_total", $metrics);
$Disk = $Disk ? sprintf("%.1f GB", $Disk) : "Unknown"; 
list($most_full, $most_full_host) = cluster_min("part_max_used", $metrics);
$data->assign("CPUs", $CPUs);
$data->assign("Memory", $Memory);
$data->assign("Disk", $Disk);

# Show which node has the most full disk.
$most_full_hosturl=rawurlencode($most_full_host);
$most_full = $most_full ? "<a href=\"./?p=1&amp;c=$cluster_url&amp;h=$most_full_host\">".
   "$most_full_host ($most_full% Used)</a>" : "Unknown";
$data->assign("most_full", $most_full);
$data->assign("cols_menu", $cols_menu);

#------------------------------------------------------------------------------
# Organize hosts by rack locations.
# Works with or without "location" host attributes.
function physical_racks() {

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

         if ($rack>=0 and $rank>=0 and $plane>=0 and !array_key_exists($rank, $racks[$rack])) {
            $racks[$rack][$rank]=$v['NAME'];
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
         if ($rack>=0 and $rank>=0 and $plane>=0 and !array_key_exists($rank, $racks[$rack])) {
            $racks[$rack][$rank]=$v['NAME'];
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
#
# Generates the colored Node cell HTML. Used in Physical
# view and others. Intended to be used to build a table, output
# begins with "<tr><td>" and ends the same.
function nodebox($hostname, $verbose, $title="", $extrarow="") {

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
         sprintf("<em>mem: </em>%.2f<small>G</small>", $mem_total_gb);
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

#-------------------------------------------------------------------------------
# Displays a rack and all its nodes.
function showrack($ID) {

   global $verbose, $racks, $racks_data, $metrics, $cluster, $hosts_up, $hosts_down;
   global $cluster_url, $tpl, $clusters;

   $racks_data[$ID]["RackID"] = "";

   if ($ID>=0) {
      $racks_data[$ID]["RackID"] = "<tr><th>Rack $ID</th></tr>";
   }

   # A string of node HTML for the template.
   $nodes="";

   foreach ($racks[$ID] as $name)
   {
      $nodes .= nodebox($name, $verbose);
   }

   return $nodes;
}

#-------------------------------------------------------------------------------
#
# My Main
#

# 2Key = "Rack ID / Rank (order in rack)" = [hostname, UP|DOWN]
$racks = physical_racks();
$racks_data = array();

# Make a $cols-wide table of Racks.
$i=1;
foreach ($racks as $rack=>$v)
   {
      $racknodes = showrack($rack);

      $racks_data[$rack]["nodes"] = $racknodes;
      $racks_data[$rack]["tr"] = "";

      if (! ($i++ % $conf['hostcols'])) {
         $racks_data["tr"] = "</tr><tr>";
      }
   }

$data->assign("racks", $racks_data);
$dwoo->output($tpl, $data);
?>
