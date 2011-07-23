<table cellpadding="1" border="0" width="100%">
<tr>
<td colspan=3 class="title">{$cluster} cluster - Physical View |
 <font size="-1">Columns&nbsp;&nbsp;{$cols_menu}</font>
</td>

<tr>
<td align="center" valign="top">
   Verbosity level (Lower is more compact):<br />
   {foreach $verbosity_levels verbosity checked}
   {$verbosity} <input type="radio" name="p" value="{$verbosity}" OnClick="ganglia_form.submit();" {$checked} />&nbsp;
   {/foreach}
</td>

<td align="left" valign="top" width="25%">
Total CPUs: <b>{$CPUs}</b><br />
Total Memory: <b>{$Memory}</b><br />
</td>

<td align="left" valign="top" width="25%">
Total Disk: <b>{$Disk}</b><br />
Most Full Disk: <b>{$most_full}</b><br />
</td>

</tr>
<tr>
<td align="left" colspan="3">

<table cellspacing="20">
<tr>
{foreach $racks rack}
   <td valign="top" align="center">
      <table cellspacing="5" border="0">
         {$rack.RackID}
         {$rack.nodes}
      </table>
   </td>
   {$rack.tr}
{/foreach}
</tr></table>

</td></tr>
</table>

<hr />


<table border="0">
<tr>
 <td align="left">
<font size="-1">
Legend
</font>
 </td>
</tr>
<tr>
<td class="odd">
<table width="100%" cellpadding="1" cellspacing="0" border="0">
<tr>
 <td style="color: blue">Node Name&nbsp;<br /></td>
 <td align="right" valign="top">
  <table cellspacing=1 cellpadding=3 border="0">
  <tr>
  <td class="L1" align="right">
  <font size="-1">1-min load</font>
  </td>
  </tr>
 </table>
<tr>
<td colspan="2" style="color: rgb(70,70,70)">
<font size="-1">
<em>cpu: </em> CPU clock (GHz) (num CPUs)<br />
<em>mem: </em> Total Memory (GB)
</font>
</td>
</tr>
</table>

</td>
</tr>
</table>
