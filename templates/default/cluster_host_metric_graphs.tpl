{template display_host_graphs hosts metric_name reports_metricname hostcols}
{$index = 1}
{foreach $hosts host}
  {if isset($host.textval)}
    <td class={$host.class}>
      <b>
        <a href={$host.host_link}>{$host.name}</a>
      </b>
      <br>
      <i>{$metric_name}:</i> <b>{$host.textval}</b>
    </td>
  {else}
    <td>
      <div>
        <font style='font-size: 8px'>{$host.name}</font>
        <br>
        <a href={$host.host_link}>
          <img src="./graph.php?{if $reports_metricname}g{else}m{/if}={$metric_name}&amp;{$host.metric_graphargs}"
               {if $host.zoom_support}class="host_{$host.size}_zoomable"{/if}
               title="{$host.name}"
               border=0
               style="padding:2px;">
        </a>
      </div>
    </td>
    {if $hostcols == 0}
      {assign array("load_report" "mem_report" "cpu_report" "network_report") reports}
      {foreach $reports report}
        <td>
          <div>
            <font style='font-size: 8px'>{$host.name}</font>
            <br>
            <a href={$host.host_link}>
              <img src="./graph.php?g={$report}&amp;{$host.report_graphargs}"
                   {if $host.zoom_support}class="host_{$host.size}_zoomable"{/if}
                   title="{$host.name}"
                   border=0
                   style="padding:2px;">
            </a>
          </div>
        </td>
      {/foreach}
    {/if}
  {/if}
  {if $index % $hostcols == 0}
    </tr><tr>
  {/if}
  {math "$index + 1" assign="index"}
{/foreach}
{/template}

<center>
  <table id=graph_sorted_list>
    <tr>
      {display_host_graphs $sorted_list $metric_name $reports_metricname $hostcols}
    </tr>
  </table>

  {if $overflow.count > 0}
  <table width=80%>
    <tr>
      <td align=center class=metric>
        <a href="#"
           id="overflow_list_button"
           onclick="$('#overflow_list').toggle();"
           class="button ui-state-default ui-corner-all"
           title="Toggle overflow list">Show more hosts ({$overflow.count})</a>
      </td>
    </tr>
  </table>
  <div style="display: none;" id="overflow_list">
    <table>
      <tr>
        {display_host_graphs $overflow_list $metric_name $reports_metricname $hostcols}
      </tr>
    </table>
  </div>
  <div style="clear:both"></div>
{/if}

{if isset($node_legend)}
<p>
(Nodes colored by 1-minute load) | <a href="./node_legend.html">Legend</A>
</p>
{/if}
</center>
