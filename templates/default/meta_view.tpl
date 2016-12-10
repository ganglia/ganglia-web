{if isset($filters)}
<table border="0" width="100%">
  <tr>
    {foreach $filters filter}
      <td>
        <b>{$filter.filter_name}</b>
        <select name="choose_filter[{$filter.filter_shortname}]" OnChange="ganglia_form.submit();">
          <option name=""></option>
          {foreach $filter.choice choice}
          {if $choose_filter.$filter.filter_shortname == $choice}
          <option name="{$choice}" selected>{$choice}</option>
          {else}
          <option name="{$choice}">{$choice}</option>
          {/if}
          {/foreach}
        </select>
      </td>
    {/foreach}
  </tr>
</table>
{/if}

<table border="0" width="100%">

<tr>
<td colspan="3">&nbsp;</td>
</tr>

{foreach $sources source}
<tr>
  <td class={$source.class} colspan="3">
    <a href="{$source.url}"><strong>{$source.name}</strong></a> {$source.alt_view}
  </td>
</tr>

<tr>
{if isset($source.public)}
<td align="LEFT" valign="TOP">
<table cellspacing="1" cellpadding="1" width="100%" border="0">
 <tr><td>CPUs Total:</td><td align="left"><B>{$source.cpu_num}</B></td></tr>
 <tr><td width="80%">Hosts up:</td><td align="left"><B>{$source.num_nodes}</B></td></tr>
 <tr><td>Hosts down:</td><td align="left"><B>{$source.num_dead_nodes}</B></td></tr>
 <tr><td>&nbsp;</td></tr>
 <tr><td class="footer" colspan="2">{$source.cluster_load}</td></tr>
 <tr><td class="footer" colspan="2">{$source.cluster_util}</td></tr>
 <tr><td class="footer" colspan="2">{$source.localtime}</td></tr>
</table>
</td>

{if isset($source.self_summary_graphs)}
<td>
 <table align="center" border="0">
  <tr>

   <td>
    <a href="./graph_all_periods.php?{$source.graph_url}&amp;g=load_report&amp;z=large">
      <img src="./graph.php?{$source.graph_url}&amp;g=load_report&amp;z=medium"
           alt="{$source.name} LOAD" border="0" />
    </a>
   </td>
   <td>
    <a href="./graph_all_periods.php?{$source.graph_url}&amp;g=mem_report&amp;z=large">
      <img src="./graph.php?{$source.graph_url}&amp;g=mem_report&amp;z=medium"
           alt="{$source.name} MEM" border="0" />
    </a>
   </td>
  </tr>

  <tr>
   <td>
    <a href="./graph_all_periods.php?{$source.graph_url}&amp;g=cpu_report&amp;z=large">
      <img src="./graph.php?{$source.graph_url}&amp;g=cpu_report&amp;z=medium"
           alt="{$source.name} CPU" border="0" />
    </a>
   </td>
   <td>
    <a href="./graph_all_periods.php?{$source.graph_url}&amp;g=network_report&amp;z=large">
      <img src="./graph.php?{$source.graph_url}&amp;g=network_report&amp;z=medium"
           alt="{$source.name} NETWORK" border="0" />
    </a>
   </td>

  </tr>
 </table>
</td>
{/if}

{if isset($source.summary_graphs)}
<td>
 <table align="center" border="0">
  <tr>

      <td>
      <a href="{$source.url}">
        <img src="./graph.php?{$source.graph_url}&amp;g=load_report&amp;z=medium&amp;r={$source.range}"
             alt="{$source.name} LOAD" border="0" />
      </a>
      </td>

      <td>
      <a href="{$source.url}">
        <img src="./graph.php?{$source.graph_url}&amp;g=network_report&amp;z=medium&amp;r={$source.range}"
             alt="{$source.name} NETWORK" border="0" />
      </a>
      </td>

  </tr>
 </td>
</table>
{/if}
{/if}


{if isset($source.private)}
  <td align="LEFT" valign="TOP">
<table cellspacing="1" cellpadding="1" width="100%" border="0">
 <tr><td>CPUs Total:</td><td align="left"><B>{$source.cpu_num}</B></td></tr>
 <tr><td width="80%">Nodes:</td><td align="left"><B>{$source.num_nodes}</B></td></tr>
 <tr><td>&nbsp;</td></tr>
 <tr><td class="footer" colspan="2">{$source.localtime}</td></tr>
</table>
   </td>
   <td colspan="2" align=center>This is a private cluster.</td>
{/if}
</tr>
{/foreach}
</table>

{if isset($show_snapshot)}
<table border="0" width="100%">
<tr>
  <td colspan="2" class="title">Snapshot of the {$self} |
    <font size="-1"><a href="./cluster_legend.html">Legend</a></font>
  </td>
</tr>
</table>

<center>
<table cellspacing="12" cellpadding="2">
{foreach $snap_rows snap_row}
<tr>{$snap_row.names}</tr>
<tr>{$snap_row.images}</tr>
{/foreach}
</table>
</center>
{/if}
