<!-- A place to put custom HTML for the cluster view. -->
<td rowspan=2 align="center" valign=top>
<a href="./graph.php?g=load_report&amp;z=large&amp;{$graph.graph_args}">
<img border=0 alt="{$cluster} load"
   src="./graph.php?g=load_report&amp;z=medium&amp;{$graph.graph_args}">
</a>
<a href="./graph.php?g=cpu_report&amp;z=large&amp;{$graph.graph_args}">
<img border=0 alt="{$cluster} cpu"
   src="./graph.php?g=cpu_report&amp;z=medium&amp;{$graph.graph_args}">
</a>
<a href="./graph.php?g=mem_report&amp;z=large&amp;{$graph.graph_args}">
<img border=0 alt="{$cluster} mem"
   src="./graph.php?g=mem_report&amp;z=medium&amp;{$graph.graph_args}">
</a>
<a href="./graph.php?g=network_report&amp;z=large&amp;{$graph.graph_args}">
<img border=0 alt="{$cluster} network"
    src="./graph.php?g=network_report&amp;z=medium&amp;{$graph.graph_args}">
</a>
<!-- start block : optional_graphs -->
{foreach $optional_graphs_data graph}
<a href="./graph.php?g={$graph.name}_report&amp;z=large&amp;{$graph.graph_args}">
<img border=0 alt="{$cluster} {$graph.name}" src="./graph.php?g={$graph.name}_report&amp;z=medium&amp;{$graph.graph_args}">
</a>
{/foreach}
<!-- end block : optional_graphs -->
</td>
