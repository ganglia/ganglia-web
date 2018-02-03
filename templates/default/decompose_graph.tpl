<script>
  function refreshDecomposeGraph() {
    $("#decompose-graphs img").each(function (index) {
	var src = $(this).attr("src");
	if (src.indexOf("graph.php") == 0) {
          var l = src.indexOf("&_=");
          if (l != -1)
            src = src.substring(0, l);
	  var d = new Date();
	  $(this).attr("src", src + "&_=" + d.getTime());
	}    
    });
  }

  $(function() {
    $("#create_view_button")
      .button()
      .click(function() {
	$( "#create-new-view-dialog" ).dialog( "open" );
    });;
  });
</script>
<div id="decompose-graph-content">
  <div id=decompose-graphs>
    {if $number_of_items == 0 }
    <div class="ui-widget">
      <div class="ui-state-default ui-corner-all" style="padding: 0 .7em;"> 
        <p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>
          No graphs decomposed
      </div>
    </div>
    {else}
      {foreach $items item}
      <div class="img_view">
        <font style="font-size: 7px">{$item.title}</font>
        <button title="Export to CSV" class="cupid-green" onClick="javascript:location.href='graph.php?{$item.url_args}&amp;csv=1';return false;">CSV</button>
        <button title="Export to JSON" class="cupid-green" onClick="javascript:location.href='graph.php?{$item.url_args}&amp;json=1';return false;">JSON</button>
        <button title="Inspect Graph" onClick="inspectGraph('{$item.url_args}'); return false;" class="shiny-blue">Inspect</button>
        <br /><a href="graph_all_periods.php?{$item.url_args}"><img class="noborder {$additional_host_img_css_classes}" style="margin-top:5px;" src="graph.php?{$item.url_args}" /></a>
      </div>
      {/foreach}
    {/if}
  </div>
</div>
<div style="clear: left"></div>
