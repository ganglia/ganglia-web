<br>
<table border="0" width="100%">

<tr>
 <td align="left" valign="TOP">

<img src="{$node_image}" class="noborder" height="60" width="30" title="{$host}"/>
{$node_msg}

<table border="0" width="100%">
<tr>
  <td colspan="2" class="title">Time and String Metrics</td>
</tr>

{foreach $s_metrics_data s_metric}
<tr>
 <td class="footer" width="30%">{$s_metric.name}</td><td>{$s_metric.value}</td>
</tr>
{/foreach}

<tr><td>&nbsp;</td></tr>

<tr>
  <td colspan="2" class="title">Constant Metrics</td>
</tr>

{foreach $c_metrics_data c_metric}
<tr>
 <td class="footer" width="30%">{$c_metric.name}</td><td>{$c_metric.value}</td>
</tr>
{/foreach}
</table>

 <hr />
{if isset($extra)}
{include(file="$extra")}
{/if}
</td> 
</table>

