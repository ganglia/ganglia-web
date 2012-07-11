<!-- Begin cluster_view.tpl -->
{if $heatmap}
<script type="text/javascript" src="js/protovis-r3.2.js"></script>
{/if}
<script type="text/javascript">
function refreshClusterView() {
  $.get('cluster_view.php?' + jQuery.param.querystring() + '&refresh', function(data) {
    var item = data.split("<!-- || -->");

    $('#cluster_title').html(item[1]);

    $('#cluster_overview').html(item[2]);

    if ($('#load_pie').size())
      $('#load_pie').attr("src", item[3].replace(/&amp;/g, "&"));

    if ($('#heatmap-fig').size()) {
      eval("heatmap = [" + item[4] + "]")	;
      vis.render();
    }

    if ($('#stacked_graph').size()) {
      var localtimestamp = parseInt(item[0]);
      var src = $('#stacked_graph').attr('src');
      $('#stacked_graph').attr("src", jQuery.param.querystring(src, "&st=" + localtimestamp));
    }

    var host_metric_graphs = $('#host_metric_graphs');
    host_metric_graphs.css('height', host_metric_graphs.height() + "px");
    host_metric_graphs.html(item[5]);
  });

  $("#optional_graphs img").each(function (index) {
    var src = $(this).attr("src");
    if ((src.indexOf("graph.php") == 0) ||
        (src.indexOf("./graph.php") == 0)) {
      var d = new Date();
      $(this).attr("src", jQuery.param.querystring(src, "&_=" + d.getTime()));
    }    
  });
}

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

<div id="edit_optional_graphs">
  <div style="text-align:center">
    <button  id='save_optional_graphs_button'>Save</button>
  </div>
  <div id="edit_optional_graphs_content">Empty</div>
</div>

<div style="background:rgb(238,238,238);text-align:center;">
  <font size="+1" id="cluster_title">Overview of {$cluster} @ {$localtime}</font>
</div>

<table border="0" cellspacing=4 width="100%">
<tr>
<td align="left" valign="top">
<div id="cluster_overview">
{include('cluster_overview.tpl')}
</div>
{if isset($extra)}
{include(file="$extra")}
{/if}
</td>
<td rowspan=2 align="center" valign=top>
<div id="optional_graphs" style="padding-bottom:4px">
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
  <img id="load_pie" src="./pie.php?{$pie_args}" border="0" />
{/if}
{if $heatmap && $num_nodes > 0}
Utilization heatmap<br />
<div id="heatmap-fig">
<script type="text/javascript+protovis">
var heatmap = [
{$heatmap}
];

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

{if $stacked_graph_args}
<center>
<table width="100%" border=0>
<tr>
  <td colspan="1">
  <font size="+1">Stacked Graph - {$metric}</font> 
  </td>
</tr>
<tr>
  <td>
  <center><img id="stacked_graph" src="stacked.php?{$stacked_graph_args}" alt="{$cluster} {$metric}"></center>
  </td>
</tr>
</table>
</center>
{/if}

<script type="text/javascript">
// Need to set the field value to metric name
$("#metrics-picker").val("{$metric_name}");
</script>


<div id="cluster_view_chooser">
<table border="0" width="100%">
  <tr>
  <td style="text-align:center;background:rgb(238,238,238);">
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
   <font>
   <span class="nobr">Size&nbsp;&nbsp;{$size_menu}</span>
   <span class="nobr">Columns&nbsp;&nbsp;{$cols_menu} (0 = metric + reports)</span>
   </font>
{/if}
  </td>
</tr>
</table>
</div>

<div id="host_metric_graphs">
{include('cluster_host_metric_graphs.tpl')}
</div>

<script type="text/javascript">
$(function() {
  $( "#cluster_view_chooser" ).buttonset();
});
</script>
<!-- End cluster_view.tpl -->
