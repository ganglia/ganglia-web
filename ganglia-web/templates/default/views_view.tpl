<style>
.img_view {
  float: left;
  margin: 0 0 10px 10px;
}
</style>

<script>
  $(function() {
    $( "#enlarge-graph-dialog" ).dialog({ autoOpen: false, minWidth: 850 });
    $("#create_view_button")
      .button()
      .click(function() {
	$( "#create-new-view-dialog" ).dialog( "open" );
    });;
  });
</script>

<div id="enlarge-graph-dialog" title="Enlarge Graph">
  <div id="enlarge-graph-dialog-content">
  </div>
</div>


<div id="views-content">
  <div id=view_graphs>
    {if $number_of_view_items == 0 }
    <div class="ui-widget">
      <div class="ui-state-default ui-corner-all" style="padding: 0 .7em;"> 
        <p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>
          No graphs defined for this view. Please add some
      </div>
    </div>
    {else}
      {foreach $view_items view_item}
      <div class="img_view">
        <button title="Export to CSV" class="cupid-green" onClick="javascript:location.href='graph.php?{$view_item.url_args}&amp;csv=1';return false;">CSV</button>
        <button title="Export to JSON" class="cupid-green" onClick="javascript:location.href='graph.php?{$view_item.url_args}&amp;json=1';return false;">JSON</button>
        {if $view_item.aggregate_graph == 1}
        <button title="Decompose aggregate graph" class="cupid-green" onClick="javascript:location.href='?{$view_item.url_args}&dg=1';return false;">Decompose</button>
        {/if}
        <button title="Enlarge Graph" onClick="enlargeGraph('{$view_item.url_args}'); return false;" class="cupid-green">Enlarge</button>
        <br /><a href="graph_all_periods.php?{$view_item.url_args}"><img class="noborder {$additional_host_img_css_classes}" style="margin-top:5px;" src="graph.php?{$view_item.url_args}" /></a>
      </div>
      {/foreach}
    {/if}
  </div>
</div>
<div style="clear: left"></div>
