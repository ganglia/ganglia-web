<!-- Begin host_view.tpl -->
<script type="text/javascript">
$(function() {
  // Modified from http://jqueryui.com/demos/toggle/
  //run the currently selected effect
  function runEffect(id){
    //most effect types need no options passed by default
    var options = { };

    options = { to: { width: 200,height: 60 } }; 
    
    //run the effect
    $("#"+id+"_div").toggle("blind",options,500);
  };
 
  //set effect from select menu value
  $('.button').click(function(event) {
    runEffect(event.target.id);
    return false;
  });

    $(function() {
	    $( "#edit_optional_graphs" ).dialog({ autoOpen: false, minWidth: 550,
	      beforeClose: function(event, ui) {  location.reload(true); } })
	    $( "#edit_optional_graphs_button" ).button();
	    $( "#save_optional_graphs_button" ).button();
	    $( "#close_edit_optional_graphs_link" ).button();
    });

    $("#edit_optional_graphs_button").click(function(event) {
      $("#edit_optional_graphs").dialog('open');
      $('#edit_optional_graphs_content').html('<img src="img/spinner.gif" />');
      $.get('edit_optional_graphs.php', "hostname={$hostname}", function(data) {
	      $('#edit_optional_graphs_content').html(data);
      })
      return false;
    });

    $("#save_optional_graphs_button").click(function(event) {
       $.get('edit_optional_graphs.php', $("#edit_optional_reports_form").serialize(), function(data) {
	      $('#edit_optional_graphs_content').html(data);
	      $("#save_optional_graphs_button").hide();
	    });
      return false;
    });


});
</script>

{if $graph_engine == "flot"}
<script language="javascript" type="text/javascript" src="js/jquery.flot.min.js"></script>
<script type="text/javascript" src="js/create-flot-graphs.js"></script>
<style>
.flotgraph2 {
  height: {$graph_height}px;
  width:  {$graph_width}px;
}
</style>
{/if}

<style type="text/css">
  .toggler { width: 500px; height: 200px; }
  a.button { padding: .15em 1em; text-decoration: none; }
  #effect { width: 240px; height: 135px; padding: 0.4em; position: relative; }
  #effect h3 { margin: 0; padding: 0.4em; text-align: center; }
</style>

<div id="metric-actions-dialog" title="Metric Actions">
  <div id="metric-actions-dialog-content">
	Available Metric actions.
  </div>
</div>

<div style="padding-bottom:5px;">
<a href="#" id="host_overview" class="button ui-state-default ui-corner-all">Host overview</a>
</div>

<div style="display: none;" id="host_overview_div">
<br>
<table border="0" width="100%">

<tr>
 <td align="left" valign="TOP">

<img src="{$node_image}" height="60" width="30" title="{$host}" border="0" />
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
</div>
<style>
#edit_optional_graphs_button {
    font-size:12px;
}
#edit_optional_graphs_content {
    padding: .4em 1em .4em 10px;
}
</style>
<div id="edit_optional_graphs">
  <div style="text-align: center;">
    <button  id='save_optional_graphs_button'>Save</button>
  </div>
  <div id="edit_optional_graphs_content">Empty</div>
</div>

<div id="optional_graphs">

<table border="0" width="100%">

<tr>

<td align="center" valign="TOP" width="395">

{$optional_reports}
</td>
</tr>
{if $may_edit_cluster}
<tr>
<td style="text-align:center;padding-top:5px;">
<button id="edit_optional_graphs_button">Edit Optional Graphs</button>
</td>
</tr>
{/if}
</table>
</div>

<div id="sort_column_dropdowns">
<table border="0" width="100%">
<tr>
  <td class="title">
  {$host} <strong>graphs</strong> ({$host_metrics_count})
  last <strong>{$range}</strong>
  sorted <strong>{$sort}</strong>
{if isset($columns_dropdown)}
  <font size="-1">
    Columns&nbsp;&nbsp;{$metric_cols_menu}
    Size&nbsp;&nbsp;{$size_menu}
  </font>
{/if}
  </td>
</tr>
</table>

</div>

<div id=metrics>

<center>
<table>
<tr>
 <td>

{foreach $g_metrics_group_data group g_metrics}
<table border="0" width="100%">
<tr>
  <td class="metric">
  <a href="#" id="{$group}" class="button ui-state-default ui-corner-all" title="Toggle {$group} metrics group on/off">{$group} metrics ({$g_metrics.group_metric_count})</a>
  </td>
</tr>
</table>
{if isset($metric_groups_initially_collapsed) && $metric_groups_initially_collapsed}
<div id="{$group}_div" class="ui-helper-hidden">
{else}
<div id="{$group}_div">
{/if}
<table><tr>
{foreach $g_metrics["metrics"] g_metric}
<td>
<font style="font-size: 9px">{$g_metric.metric_name} {if $g_metric.title != '' && $g_metric.title != $g_metric.metric_name}- {$g_metric.title}{/if}</font>
{if $may_edit_views}
{$graph_args = "&amp;";$graph_args .= $g_metric.graphargs;}
<a style="background-color: #dddddd" onclick="metricActions('{$g_metric.host_name}','{$g_metric.metric_name}', 'metric', '{$graph_args}'); return false;" href="#">+</a>
{/if}
<a href="./graph.php?{$g_metric.graphargs}&amp;csv=1"><img title="Export to CSV" class="icon16" src="img/csv.png" /></a>
<a href="./graph.php?{$g_metric.graphargs}&amp;json=1"><img title="Export as JSON" class="icon16" src="img/js.png" /></a>
<br>
{if $graph_engine == "flot"}
<div id="placeholder_{$g_metric.graphargs}" class="flotgraph2 img_view"></div>
{else}
<a href="./graph_all_periods.php?{$g_metric.graphargs}&amp;z=large">
<img class="noborder" {$additional_host_img_html_args} alt="{$g_metric.alt}" src="./graph.php?{$g_metric.graphargs}" title="{$g_metric.desc}" />
</A>
{/if}
</td>
{$g_metric.new_row}
{/foreach}
</tr>
</table>
</div>
{/foreach}
 </td>
</tr>
</table>
</center>

</div>
<!-- End host_view.tpl -->
