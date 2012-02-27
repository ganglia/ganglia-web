<center>
<table id=graph_sorted_list>
<tr>
{foreach $sorted_list host}
{$host.metric_image}{$host.br}
{/foreach}
</tr>
</table>

{$overflow_list_header}
{foreach $overflow_list host}
{$host.metric_image}{$host.br}
{/foreach}
{$overflow_list_footer}

{if isset($node_legend)}
<p>
(Nodes colored by 1-minute load) | <a href="./node_legend.html">Legend</A>
</p>
{/if}
</center>
