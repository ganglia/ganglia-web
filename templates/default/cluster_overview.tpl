<table cellspacing=1 cellpadding=1 width="100%" border=0>
 <tr><td>CPUs Total:</td><td align=left><B>{$overview.cpu_num}</B></td></tr>
 <tr><td width="60%">Hosts up:</td><td align=left><B>{$overview.num_nodes}</B></td></tr>
 <tr><td>Hosts down:</td><td align=left><B>{$overview.num_dead_nodes}</B></td></tr>
 <tr><td>&nbsp;</td></tr>
 <tr><td colspan=2><font class="nobr">Current Load Avg (15, 5, 1m):</font><br>&nbsp;&nbsp;<b>{$overview.cluster_load}</b></td></tr>
 <tr><td colspan=2>Avg Utilization (last {$overview.range}):<br>&nbsp;&nbsp;<b>{$overview.cluster_util}</b></td></tr>
 </table>
