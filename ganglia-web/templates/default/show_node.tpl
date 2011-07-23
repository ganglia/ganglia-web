<!-- Begin show_node.tpl -->
<table border="0" width="100%">
<tr>
  <td colspan="2" class=title>{$name} Info</td>
</tr>
<tr><td colspan="1">&nbsp;</td></tr>
<tr>
<td align="center">

<table cellpadding="2" cellspacing="7" border="1">
<tr>
<td class="{$class}">
   <table cellpadding="1" cellspacing="10" border="0">
   <tr><td valign="top"><font size="+2"><b>{$name}</b></font><br />
   <i>{$ip}</i><br />
   <em>Location:</em> {$location}<p>

   Cluster local time {$clustertime}<br />
   Last heartbeat received {$age} ago.<br />
   Uptime {$uptime}<br />

   </td>
   <td align="right" valign="top">
   <table cellspacing="4" cellpadding="3" border="0"><tr>
   <tr><td><i>Load:</i></td>
   <td class={$load1}><small>{$load_one}</small></td>
   <td class={$load5}><small>{$load_five}</small></td>
   <td class={$load15}><small>{$load_fifteen}</small></td>
   </tr><tr><td></td><td><em>1m</em></td><td><em>5m</em></td><td><em>15m</em></td></tr>
   </table><br />

   <table cellspacing="4" cellpadding="3" border="0"><tr>
   <td><i>CPU Utilization:</i></td>
   <td class={$user}><small>{$cpu_user}</small></td>
   <td class={$sys}><small>{$cpu_system}</small></td>
   <td class={$idle}><small>{$cpu_idle}</small></td>
   </tr><tr><td></td><td><em>user</em></td><td><em>sys</em></td><td><em>idle</em></td></tr>
   </table>
   </td>
   </tr>
   <tr><td align="left" valign="top">

   <b>Hardware</b><br />
   <em>CPU{$s}:</em> {$cpu}<br />
   <em>Memory (RAM):</em> {$mem}<br />
   <em>Local Disk:</em> {$disk}<br />
   <em>Most Full Disk Partition:</em> {$part_max_used}
   </td>
   <td align="left" valign="top">

   <b>Software</b><br />
   <em>OS:</em> {$OS}<br />
   <em>Booted:</em> {$booted}<br />
   <em>Uptime:</em> {$uptime}<br />
   <em>Swap:</em> {$swap}<br />

   </td></tr></table>
</td>
</tr></table>

 </td>
</tr>
<tr>
<td align="center" valign="middle">
 <a href="{$physical_view}">Physical View</a> | <a href="{$self}">Reload</a>
</td>
</tr>
<tr>
 <td>
{if isset($extra)}
{include(file="$extra")}
{/if} 
</td>
</tr>
</table>
<!-- End show_node.tpl -->
