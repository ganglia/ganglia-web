<div id="inspect-graph-dialog" title="Inspect Graph">
  <div id="inspect-graph-dialog-content">
  </div>
</div>
<div>
  Enter host regular expression: 
  <input type="text" name="hreg[]" value="{$hreg_arg}">
  <button>Go</button>
</div>
<div id="compare-hosts-content">
  <div id=compare-hosts>
    {if $hreg_arg != ''}
    {if $number_of_metrics == 0}
    <div class="ui-widget">
      <div class="ui-state-default ui-corner-all" style="padding: 0 .7em;"> 
        <p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>
          No matching metrics
      </div>
    </div>
    {else}
      {$i = 0}
      {foreach $host_metrics metric}
      {$graphId = cat($GRAPH_BASE_ID "dg_" $i)}
      {$showEventsId = cat($SHOW_EVENTS_BASE_ID "dg_" $i)}
      <div class="img_view"><font style="font-size: 9px">{$metric}</font>
        <button title="Export to CSV" class="cupid-green" onClick="javascript:location.href='graph.php?{$metric}{$hreg}{$graphargs}&amp;csv=1';return false;">CSV</button>
        <button title="Export to JSON" class="cupid-green" onClick="javascript:location.href='graph.php?{$metric}{$hreg}{$graphargs}&amp;json=1';return false;">JSON</button>
        <button title="Inspect Graph" onClick="inspectGraph('mreg[]=%5E{$metric}%24{$hreg}&aggregate=1'); return false;" class="shiny-blue">Inspect</button>
        <button title="Decompose aggregate graph" class="shiny-blue" onClick="javascript:location.href='?mreg[]=%5E{$metric}%24{$hreg}&amp;dg=1';return false;">Decompose</button>
        <input title="Hide/Show Events" type="checkbox" id="{$showEventsId}" onclick="showEvents('{$graphId}', this.checked)"/><label class="show_event_text" for="{$showEventsId}">Hide/Show Events</label>
        <br /><a href="graph_all_periods.php?title={$metric}&mreg[]=%5E{$metric}%24{$hreg}&aggregate=1&hl={$host_list}"><img id="{$graphId}" style="margin-top:5px;" class="noborder {$additional_host_img_css_classes}" src="graph.php?title={$metric}&mreg[]=%5E{$metric}%24${$hreg}{$graphargs}&aggregate=1&hl={$host_list}" /></a>
      </div>
      {math "$i + 1" assign=i}
      {/foreach}
    {/if}
    {/if}
  </div>
</div>
<div style="clear: left"></div>
