<?php
#
# Retrieves and parses the XML output from gmond. Results stored
# in global variables: $clusters, $hosts, $hosts_down, $metrics.
# Assumes you have already called get_context.php.
#

# If we are in compare_hosts, views and decompose_graph context we shouldn't attempt
# any connections to the gmetad
if (! in_array($context, $SKIP_GMETAD_CONTEXTS) ) {
   if (! Gmetad($conf['ganglia_ip'], $conf['ganglia_port']) )
      {
         print "<H4>There was an error collecting ganglia data ".
            "(${conf['ganglia_ip']}:${conf['ganglia_port']}): $error</H4>\n";
         exit;
      }
      
      
   # If we have no child data sources, assume something is wrong.
   if (!count($grid) and !count($cluster))
      {
         print "<H4>Ganglia cannot find a data source. Is gmond running?</H4>";
         exit;
      }
   # If we only have one cluster source, suppress MetaCluster output.
   if (count($grid) < 2 and $context=="meta")
      {
         # Lets look for one cluster (the other is our grid).
         foreach($grid as $source)
            if (isset($source['CLUSTER']) and $source['CLUSTER'])
               {
                  $standalone = 1;
                  $context = "cluster";
                  # Need to refresh data with new context.
                  Gmetad($conf['ganglia_ip'], $conf['ganglia_port']);
                  $clustername = $source['NAME'];
               }
      }

}

?>
