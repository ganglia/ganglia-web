<!-- Begin cluster_view.tpl -->
{if $heatmap}
<script type="text/javascript" src="js/protovis-r3.2.js"></script>
<script type="text/javascript">
var heatmap = [
{$heatmap}
];
</script>
{/if}
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
      $('#edit_optional_graphs_content').html('<img src="img/spinner.gif">');
      $.get('edit_optional_graphs.php', "clustername={$cluster}", function(data) {
          $('#edit_optional_graphs_content').html(data);
      })
      return false;
    });

    $("#save_optional_graphs_button").click(function(event) {
       $.get('edit_optional_graphs.php', $("#edit_optional_reports_form").serialize(), function(data) {
          $('#edit_optional_graphs_content').html(data);
          $("#save_optional_graphs_button").hide();
          setTimeout(function() {
             $('#edit_optional_graphs').dialog('close');
          }, 5000);
        });
      return false;
    });

});
</script>

{if $graph_engine == "flot"}
<script language="javascript" type="text/javascript" src="js/jquery.flot.min.js"></script>
<script type="text/javascript" src="js/create-flot-graphs.js"></script>
<style type="text/css">
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
  #heatmap-fig {
    width: 200px;
    height: 200px;
  } 
</style>

<div id="metric-actions-dialog" title="Metric Actions">
  <div id="metric-actions-dialog-content">
    Available Metric actions.
  </div>
</div>

<style type="text/css">
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

<table border="0" cellspacing=5 width="100%">
<tr>
  <td class="title" colspan="2">
  <font size="+1">Overview of {$cluster} @ {$localtime}</font>
  </td>
</tr>

<tr>
<td align="left" valign="top">
<table cellspacing=1 cellpadding=1 width="100%" border=0>
 <tr><td>CPUs Total:</td><td align=left><B>{$cpu_num}</B></td></tr>
 <tr><td width="60%">Hosts up:</td><td align=left><B>{$num_nodes}</B></td></tr>
 <tr><td>Hosts down:</td><td align=left><B>{$num_dead_nodes}</B></td></tr>
 <tr><td>&nbsp;</td></tr>
 <tr><td colspan=2><font class="nobr">Current Load Avg (15, 5, 1m):</font><br>&nbsp;&nbsp;<b>{$cluster_load}</b></td></tr>
 <tr><td colspan=2>Avg Utilization (last {$range}):<br>&nbsp;&nbsp;<b>{$cluster_util}</b></td></tr>
 </table>

{if isset($extra)}
{include(file="$extra")}
{/if}
 <hr>
</td>
<td rowspan=2 align="center" valign=top>
<div id="optional_graphs" style="padding-bottom:2px;">
  {$optional_reports}<br>
  {foreach $optional_graphs_data graph}
  <a href="./graph_all_periods.php?{$graph.graph_args}&amp;g={$graph.name}_report&amp;z=large">
  <img border=0 {$additional_cluster_img_html_args} title="{$cluster} {$graph.name}" src="./graph.php?{$graph.graph_args}&amp;g={$graph.name}_report&amp;z=medium"></a>
  {/foreach}
</div>
{if $user_may_edit}
<button id="edit_optional_graphs_button">Edit Optional Graphs</button>
{/if}
</td>
</tr>

<tr>
 <td align="center" valign="top">
{if $php_gd && !$heatmap}
  <img src="./pie.php?{$pie_args}" title="Pie Chart" border="0" />
{/if}
{if $heatmap}
Utilization heatmap<br />
<div id="heatmap-fig">
    <script type="text/javascript+protovis">

var w = heatmap[0].length,
    h = heatmap.length;

var vis = new pv.Panel()
    .width(w * {$heatmap_size})
    .height(h * {$heatmap_size})
    .margin(2)
    .strokeStyle("#aaa")
    .lineWidth(4)
    .antialias(false);

vis.add(pv.Image)
    .imageWidth(w)
    .imageHeight(h)
    .image(pv.Scale.linear()
        .domain(0, 0.25, 0.5, 0.75, 1.00)
        .range("#e2ecff", "#caff98", "#ffde5e" , "#ffa15e","#ff634f")
        .by(function(i, j) heatmap[j][i]));

vis.render();
    </script>
 </div>
{/if}
 </td>
</tr>
</table>

<script type="text/javascript">
// Need to set the field value to metric name
$("#metrics-picker").val("{$metric_name}");
</script>


<div id="cluster_view_chooser">
<table border="0" width="100%">
  <tr>
  <td class="title" style="font-size: 12px">
  Show Hosts Scaled:
  {foreach $showhosts_levels id showhosts implode=""}
  <input type="radio" name="sh" value="{$id}" id="shch{$id}" OnClick="ganglia_form.submit();" {$showhosts.checked}><label for="shch{$id}">{$showhosts.name}</label>
  {/foreach}&nbsp;
  |
  <span class="nobr">{$cluster} <strong>{$metric}</strong>
  last <strong>{$range}</strong>
  sorted <strong>{$sort}</strong></span>
{if isset($columns_size_dropdown)}
  |
   <font size="-1">
   <span class="nobr">Size&nbsp;&nbsp;{$size_menu}</span>
   <span class="nobr">Columns&nbsp;&nbsp;{$cols_menu} (0 = metric + reports)</span>
   </font>
{/if}
  </td>
</tr>
</table>
</div>

<center>
<table id=graph_sorted_list>
<tr>
{foreach $sorted_list host}
{$host.metric_image}{$host.br}
{/foreach}
</tr>
</table>

{$overflow_list_header}
{foreach $overflow_list host}
{$host.metric_image}{$host.br}
{/foreach}
{$overflow_list_footer}

{if isset($node_legend)}
<p>
(Nodes colored by 1-minute load) | <a href="./node_legend.html">Legend</A>
</p>
{/if}
</center>
<script>
$(function() {
  $( "#cluster_view_chooser" ).buttonset();
});
</script>
<!-- End cluster_view.tpl -->
