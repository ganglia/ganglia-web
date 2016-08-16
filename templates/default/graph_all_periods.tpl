{if $standalone}
  <!doctype html>
  <html>
    <head>
      <title>Ganglia: Graph all periods</title>
      {include('scripts.tpl')}
{/if}
<script type="text/javascript">
 function openDecompose($url) {
   $.cookie("ganglia-selected-tab-" + window.name, 0);
   location.href="./index.php" + $url + "&amp;tab=m";
 }

 $(function() {
   {if isset($graph_actions)}
   initShowEvent();
   {if $graph_actions['timeshift']}
   initTimeShift();
   {/if}
   {if $graph_actions['metric_actions'] && $standalone}
   initMetricActionsDialog();
   {/if}
   {if $standalone}
   $("#popup-dialog").dialog( { autoOpen: false,
                                width:800,
                                height:500,
                                position: { my: "top",
                                            at: "top+200",
                                            of: window } } );
   {/if}
   {/if}
 });
</script>

{if $conf['graph_engine'] == 'flot'}
  <style type="text/css">
   .flotgraph {
     height: {$flot_graph_height}px;
     width: {$flot_graph_width}px;
   }
  </style>

  <script type="text/javascript">
   var metric = "{if isset($g)} {$g} {else} {$m} {/if}";
   var base_url = "graph.php?flot=1&amp;{$m}&amp;{$query_string}&amp;r=hour";
  </script>
  <script type="text/javascript" src="js/create-flot-graphs.js"></script>
{/if}

{if $standalone}
    </head>
    <body onSubmit="return false;">

      <div id="popup-dialog" title="Inspect Graph">
        <div id="popup-dialog-navigation"></div>
        <div id="popup-dialog-content">
        </div>
      </div>

      <div id="metric-actions-dialog"
           style="display: none"
           title="Metric Actions">
        <div id="metric-actions-dialog-content">
          Available Metric actions.
        </div>
      </div>
{/if}

<form>

  {if isset($mobile)}
    <div data-role="page" class="ganglia-mobile" id="view-home">
      <div data-role="header">
        <a href="#" class="ui-btn-left"
           data-icon="arrow-l"
           onclick="history.back(); return false">Back</a>
        <h3>{if isset($html_g)} {$html_g} {else} {$html_m} {/if}</h3>
        <a href="#mobile-home">Home</a>
      </div>
      <div data-role="content">
  {/if}

  {if $standalone}
    <div style="background-color:#dddddd;{if $standalone} padding:5px; {/if}">
      <big>
        <b>{$host_type}: </b>{$host_description}&nbsp;
        <b>{$metric_type}: </b>{$metric_description}&nbsp;&nbsp;
      </big>
    </div>
  {/if}

  {if isset($graph_actions)}
    {if $standalone}
      <div style='height:10px;'></div>
    {/if}
    <div>
      <div style="float:right;">
        <input title="Hide/Show Events"
               type="checkbox"
               id="show_all_events_graph_all_periods"
               onclick="showAllEvents(this.checked)"/>
        <label class="show_event_text"
               for="show_all_events_graph_all_periods">Hide/Show Events All Graphs</label>
        {if $graph_actions['timeshift']}
          <input title="Timeshift Overlay"
                 type="checkbox"
                 id="timeshift_overlay"
                 onclick="showTimeshiftOverlay(this.checked)"/>
          <label class="show_timeshift_text"
                 for="timeshift_overlay">Timeshift Overlay</label>
          <br />
        {/if}
      </div>
      <div style="clear:both"></div>
    </div>
  {/if}

  {if isset($embed) || $standalone}
    <div style='height:10px;'></div>
  {/if}

  {foreach $conf['time_ranges'] key value}
    {if $value != 'job'}
      <div class="img_view">
        {$graphId = cat($GRAPH_BASE_ID $key)}

        {if isset($graph_actions)}
          <span style="padding-left: 4em; padding-right: 4em; text-weight: bold;">{$key}</span>

          {if $graph_actions['metric_actions']}
            <button class="cupid-green"
                    title="Metric Actions - Add to View, etc"
                    onclick="{if $is_aggregate} metricActionsAggregateGraph('{$query_string}') {else} metricActions('{$h}', {if isset($g)} '{$g}', 'graph' {else} '{$m}', 'metric' {/if}, '{$query_string}'){/if}; return false;">+</button>
          {/if}

          <button title="Export to CSV"
                  class="cupid-green"
                  onclick="window.location='./graph.php?r={$key}&amp;{$query_string}&amp;csv=1';return false">CSV</button>

          <button title="Export to JSON"
                  class="cupid-green"
                  onclick="window.location='./graph.php?r={$key}&amp;{$query_string}&amp;json=1';return false;">JSON</button>

          {if $graph_actions['decompose']}
            <button title="Decompose aggregate graph"
                    class="shiny-blue"
                    onClick="openDecompose('?r={$key}&amp;{$query_string}&amp;dg=1');return false;">Decompose</button>
          {/if}

          <button title="Inspect Graph"
                  onClick="inspectGraph('r={$key}&amp;{$query_string}'); return false;"
                  class="shiny-blue">Inspect</button>

          {$showEventsId = cat($SHOW_EVENTS_BASE_ID $key)}

          <input title="Hide/Show Events"
                 type="checkbox"
                 id="{$showEventsId}"
                 onclick="showEvents('{$graphId}', this.checked)"/>
          <label class="show_event_text"
                 for="{$showEventsId}">Hide/Show Events</label>

          {if $graph_actions['timeshift']}
            {$timeShiftId = cat($TIME_SHIFT_BASE_ID $key)}
            <input title="Timeshift Overlay"
                   type="checkbox"
                   id="{$timeShiftId}"
                   onclick="showTimeShift('{$graphId}', this.checked)"/>
            <label class="show_timeshift_text"
                   for="{$timeShiftId}">Timeshift</label>
          {/if}
        {/if}

        <br />

        {if $conf['graph_engine'] == "flot"}
          <div id="placeholder_{$key}" class="flotgraph img_view"></div>
          <div id="placeholder_{$key}_legend" class="flotlegend"></div>
        {else}
          <a href="./graph.php?r={$key}&amp;z={$xlargesize}&amp;{$query_string}">
            <img class="noborder"
                 id="{$graphId}"
                 style="margin-top:5px;"
                 title="Last {$key}"
                 src="graph.php?r={$key}&amp;z={$largesize}&amp;{$query_string}"></a>
        {/if}
      </div>
    {/if}
  {/foreach}
  <div style="clear: left"></div>
</form>
{if $standalone}
    </body>
  </html>
{/if}
