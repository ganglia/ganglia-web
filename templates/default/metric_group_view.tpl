<table>
  <tr>
    {$i = 0}
    {foreach $g_metrics["metrics"] g_metric}
      <td>
        <div id="metric_{$g_metric.metric_name}">
        <font style="font-size: 8px">{$g_metric.metric_name} {if $g_metric.title != '' && $g_metric.title != $g_metric.metric_name}- {$g_metric.title}{/if}</font><br>
        {if $may_edit_views}
          {$graph_args = "&amp;";$graph_args .= $g_metric.graphargs;}
          <button class="cupid-green"
                  title="Metric Actions - Add to View, etc"
                  onclick="metricActions('{$g_metric.host_name}','{$g_metric.metric_name}', 'metric', '{$graph_args}'); return false;">+</button>
        {/if}
        <button title="Export to CSV"
                class="cupid-green"
                onClick="javascript:location.href='./graph.php?{$g_metric.graphargs}&amp;csv=1';return false;">CSV</button>
        <button title="Export to JSON"
                class="cupid-green"
                onClick="javascript:location.href='./graph.php?{$g_metric.graphargs}&amp;json=1';return false;">JSON</button>
        <button title="Inspect Graph"
                onClick="inspectGraph('{$g_metric.graphargs}'); return false;"
                class="shiny-blue">Inspect</button>
        <button title="6 month trend"
                onClick="drawTrendGraph('./graph.php?{$g_metric.graphargs}&amp;trend=1&amp;z=xlarge'); return false;"
                class="shiny-blue">Trend</button>

        {if $graph_engine == "flot"}
          <br>
          <div id="placeholder_{$g_metric.graphargs}"
               class="flotgraph2 img_view"></div>
          <div id="placeholder_{$g_metric.graphargs}_legend"
               class="flotlegend"></div>
        {else}
          {$graphId = cat($GRAPH_BASE_ID $mgId $i)}
          {$showEventsId = cat($SHOW_EVENTS_BASE_ID $mgId $i)}
          <input title="Hide/Show Events"
                 type="checkbox"
                 id="{$showEventsId}"
                 onclick="showEvents('{$graphId}', this.checked)"/>
          <label class="show_event_text"
                 for="{$showEventsId}">Hide/Show Events</label>
          {$timeShiftId = cat($TIME_SHIFT_BASE_ID $mgId $i)}
          <input title="Timeshift Overlay"
                 type="checkbox" id="{$timeShiftId}"
                 onclick="showTimeShift('{$graphId}', this.checked)"/>
          <label class="show_timeshift_text"
                 for="{$timeShiftId}">Timeshift</label>
          <br>
          <a href="./graph_all_periods.php?{$g_metric.graphargs}&amp;z=large">
            <img id="{$graphId}"
                 class="noborder {$additional_host_img_css_classes}"
                 style="margin:5px;"
                 alt="{$g_metric.alt}"
                 src="./graph.php?{$g_metric.graphargs}"
                 title="{$g_metric.desc}"/>
          </a>
        {/if}
        </div>
      </td>
      {$g_metric.new_row}
      {math "$i + 1" assign=i}
    {/foreach}
  </tr>
</table>
