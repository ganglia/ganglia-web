{if isset($flot_graph)}
  <script type="text/javascript">
   $(function() {
     var viewGraphs = [];
     {foreach $view_items view_item}
     viewGraphs.push("{$view_item.url_args}");
     {/foreach}
     var tz = getTimezone();
     for (var i = 0; i < viewGraphs.length; i++) {
       var viewGraph = viewGraphs[i];
       console.log(viewGraph);
       var flotGraph = new FlotGraph(viewGraph,
                                     g_refreshInterval,
                                     tz);
       var $viewItem = $("#flot_view_item_" + i);
       $viewItem.html(flotGraph.getBaseHtml());
       flotGraph.initialize();
       flotGraph.start();
     }
   });
  </script>
{/if}
<div id="views-content">
  <div id=view_graphs>
    <script type="text/javascript">viewCommonYaxis={if $common_y_axis}true{else}false{/if};yAxisUpperLimit=null;yAxisLowerLimit=null;</script>
    {if isset($number_of_view_items)}
      {if $number_of_view_items == 0}
        <div class="ui-widget">
          <div class="ui-state-default ui-corner-all" style="padding: 0 .7em;">
            <p><span class="ui-icon ui-icon-alert"
                     style="float: left; margin-right: .3em;"></span>
              No graphs defined for this view. Please add some
          </div>
        </div>
      {else}
        {$i = 0}
        {foreach $view_items view_item}
          {$graphId = cat($GRAPH_BASE_ID "view_" $i)}
          {$showEventsId = cat($SHOW_EVENTS_BASE_ID "view_" $i)}
          <div class="img_view">
            <button title="Export to CSV"
                    class="cupid-green"
                    onClick="javascript:location.href='graph.php?{$view_item.url_args}&amp;csv=1';return false;">CSV</button>
            <button title="Export to JSON"
                    class="cupid-green"
                    onClick="javascript:location.href='graph.php?{$view_item.url_args}&amp;json=1';return false;">JSON</button>
            {if $view_item['canBeDecomposed'] == 1}
              <button title="Decompose graph"
                      class="shiny-blue"
                      onClick="javascript:location.href='?{$view_item.url_args}&amp;dg=1&amp;tab=v';return false;">Decompose</button>
            {/if}
            <button title="Inspect Graph"
                    onClick="inspectGraph('{$view_item.url_args}'); return false;" class="shiny-blue">Inspect</button>
            <input type="checkbox"
                   id="{$showEventsId}"
                   onclick="showEvents('{$graphId}', this.checked)"/>
            <label title="Hide/Show Events"
                   class="show_event_text"
                   for="{$showEventsId}">Hide/Show Events</label>
            <br />
            {if isset($flot_graph)}
              <div id="flot_view_item_{$i}"
                   style="height:280px;width:460px;"
                   class="flotgraph2 img_view"></div>
            {elseif $graph_engine == "flot"}
              <div id="placeholder_{$view_item.url_args}"
                   class="flotgraph2 img_view"></div>
              <div id="placeholder_{$view_item.url_args}_legend"
                   class="flotlegend"></div>
            {else}
              <a href="graph_all_periods.php?{$view_item.url_args}">
                <img id="{$graphId}"
                     class="noborder {$additional_host_img_css_classes}"
                     style="margin-top:5px;"
                     src="graph.php?{$view_item.url_args}" />
              </a>
            {/if}
          </div>
          {math "$i + 1" assign=i}
        {/foreach}
      {/if}
    {/if}
  </div>
</div>
