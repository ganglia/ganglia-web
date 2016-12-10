<!-- Begin cluster_view.tpl -->
{if $heatmap_data}
<script type="text/javascript" src="{$conf['protovis_js_path']}"></script>
{/if}
<script type="text/javascript">
function Heatmap(elem_id, data) {
  this.elem_id = elem_id;
  this.data = data;
  this.num_cols = data.length;
  this.cell_size = $("#" + elem_id).height() / this.num_cols;
  var this_map = this;

  this.vis = new pv.Panel()
    .width($("#" + elem_id).width())
    .height($("#" + elem_id).height())
    .margin(2)
    .strokeStyle("#aaa")
    .lineWidth(4)
    .antialias(false);

  this.fill = pv.Scale.linear().
    domain(0, 0.25, 0.5, 0.75, 1.00).
    range("#e2ecff", "#caff98", "#ffde5e", "#ffa15e", "#ff634f");

  this.row = this.vis.add(pv.Panel)
    .data(pv.range(this.num_cols))
    .height(this.cell_size)
    .top(function(d) { return d * this_map.cell_size;});

  this.cell = this.row.add(pv.Panel)
    .data(pv.range(this.num_cols))
    .height(this.cell_size)
    .width(this.cell_size)
    .left(function(d) { return d * this_map.cell_size;})
    .fillStyle(function(col_index, row_index) { return this_map.fill(this_map.data[row_index][col_index].load);})
    .title(function(col_index, row_index) { return this_map.data[row_index][col_index].host + ", load = " + (this_map.data[row_index][col_index].load * 100).toFixed(0) + "%";});
}

Heatmap.prototype.setData = function(data) {
  this.data = data;
  this.num_cols = data.length;
  this.cell_size = $("#" + this.elem_id).height() / this.num_cols;
}

Heatmap.prototype.render = function() {
  this.vis.render();
}

function refreshClusterView() {
  $.get('cluster_view.php?' + jQuery.param.querystring() + '&refresh', function(data) {
    var item = data.split("<!-- || -->");

    $('#cluster_title').html(item[1]);

    $('#cluster_overview').html(item[2]);

    if ($('#load_pie').length)
      $('#load_pie').attr("src", item[3].replace(/&amp;/g, "&"));

    if ($('#heatmap-fig').length) {
      eval("heatmap.setData(" + item[4] + ")");
      heatmap.render();
    }

    if ($('#stacked_graph').length) {
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
  // run the currently selected effect
  function runEffect(id){
    // most effect types need no options passed by default
    var options = { };

    options = { to: { width: 200,height: 60 } };

    // run the effect
    $("#"+id+"_div").toggle("blind",options,500);
  };

  // set effect from select menu value
  $('.button').click(function(event) {
    runEffect(event.target.id);
    return false;
  });

  $("#edit_optional_graphs").dialog({ autoOpen:
                                      false, minWidth: 550,
                                      beforeClose: function(event, ui) {
                                        location.reload(true);
                                      }});
  $("#edit_optional_graphs_button").button();
  $("#save_optional_graphs_button").button();
  $("#close_edit_optional_graphs_link").button();

  $("#edit_optional_graphs_button").click(function(event) {
    $("#edit_optional_graphs").dialog('open');
    $('#edit_optional_graphs_content').html('<img src="img/spinner.gif">');
    $.get('edit_optional_graphs.php',
          "clustername={$cluster}",
          function(data) {
            $('#edit_optional_graphs_content').html(data);
          });
    return false;
  });

  $("#save_optional_graphs_button").click(function(event) {
    $.get('edit_optional_graphs.php',
          $("#edit_optional_reports_form").serialize(),
          function(data) {
            $('#edit_optional_graphs_content').html(data);
            $("#save_optional_graphs_button").hide();
            setTimeout(function() {
              $('#edit_optional_graphs').dialog('close');}, 5000);
          });
    return false;
  });

   var $show_hosts_scaled = $("#show_hosts_scaled");
   $show_hosts_scaled.children(":radio").each(function() {
     $(this).checkboxradio( { icon: false } );
   });
   $show_hosts_scaled.controlgroup();


  {if $picker_autocomplete}
    var cache = { }, lastXhr;
    $("#metrics-picker").autocomplete({
      minLength: 2,
      source: function( request, response ) {
        var term = request.term;
        if ( term in cache ) {
          response( cache[term] );
          return;
        }
        lastXhr = $.getJSON("api/metrics_autocomplete.php",
                            request,
                            function( data, status, xhr ) {
                              cache[term] = data.message;
                              if ( xhr == lastXhr ) {
                                response(data.message);
                              }
                            });
      }
    });
  {else}
    $("#metrics-picker").chosen({ max_selected_options:1,
                                  search_contains:true,
                                  no_results_text:"No metrics matched",
                                  placeholder_text_single:"Select a metric"}).
    on('change', function (evt, params) { ganglia_form.submit();});
  {/if}
});
</script>

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

<div id="edit_optional_graphs">
  <div style="text-align:center">
    <button  id='save_optional_graphs_button'>Save</button>
  </div>
  <div id="edit_optional_graphs_content">Empty</div>
</div>

<div style="background:rgb(238,238,238);text-align:center;">
  <font size="+1"
        id="cluster_title">Overview of {$cluster} @ {$localtime}</font>
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
        {foreach $optional_reports graph}
          <a href="./graph_all_periods.php?{$graph.graph_args}&amp;g={$graph.name}&amp;z=large">
            <img border=0
                 {if $graph.zoom_support}class="cluster_zoomable"{/if}
                 title="{$cluster} {$graph.name}"
                 src="./graph.php?{$graph.graph_args}&amp;g={$graph.name}&amp;z={$graph.size}"></a>
        {/foreach}
        <br>
        {foreach $optional_graphs graph}
          <a href="./graph_all_periods.php?{$graph.graph_args}&amp;g={$graph.name}_report&amp;z=large">
            <img border=0
                 {if $graph.zoom_support}class="cluster_zoomable"{/if}
                 title="{$cluster} {$graph.name}"
                 src="./graph.php?{$graph.graph_args}&amp;g={$graph.name}_report&amp;z=medium"></a>
        {/foreach}
      </div>
      {if $user_may_edit}
        <button id="edit_optional_graphs_button">Edit Optional Graphs</button>
      {/if}
    </td>
  </tr>
  <tr>
    <td align="center" valign="top">
      {if $php_gd && !$heatmap_data}
        <img id="load_pie" src="./pie.php?{$pie_args}" border="0" />
      {/if}
      {if $heatmap_data && $overview.num_nodes > 0}
        Server Load Distribution<br />
        <div id="heatmap-fig">
          <script type="text/javascript">
           var heatmap = new Heatmap("heatmap-fig", {$heatmap_data});
           heatmap.render();
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
          <font size="+1"
                style="text-align:center">Stacked Graph - {$metric}</font>
        </td>
      </tr>
      <tr>
        <td>
          <center><img id="stacked_graph"
                       src="stacked.php?{$stacked_graph_args}"
                       alt="{$cluster} {$metric}"></center>
        </td>
      </tr>
    </table>
  </center>
{/if}

<div id="cluster_view_chooser" style="padding:5px;background:rgb(238,238,238);">
  <div style="text-align:center;padding:5px;">
    {if $showhosts != 0}
      <div class="nobr">{$cluster} <strong>{$metric}</strong>
        last <strong>{$overview.range}</strong>
        sorted <strong>{$sort}</strong>
      </div>
      <div style="display:inline;padding:5px 0 0 0;">
        Metric&nbsp;
        {if $picker_autocomplete}
          <input name="m" id="metrics-picker" />
        {else}
          <select name="m" id="metrics-picker">{$picker_metrics}</select>
        {/if}
      </div>
    {/if}
    <div style="padding:5px 0 0 0;">Show Hosts Scaled:&nbsp;&nbsp;
      <div id="show_hosts_scaled">
        {foreach $showhosts_levels id showhosts implode=""}
          <input type="radio"
                 name="sh"
                 value="{$id}"
                 id="shch{$id}"
                 OnClick="ganglia_form.submit();" {$showhosts.checked}>
          <label for="shch{$id}">{$showhosts.name}</label>
        {/foreach}
      </div>
      {if isset($columns_size_dropdown) && ($showhosts != 0)}
        <div style="display:inline;padding-left:10px;"
             class="nobr">Size&nbsp;&nbsp;{$size_menu}</div>
        <div style="display:inline;padding-left:10px;"
             class="nobr">Columns&nbsp;&nbsp;{$cols_menu} (0 = metric + reports)</div>
      {/if}
    </div>
    <div style="text-align:center;padding:5px 0 0 0;">
      {$additional_filter_options}
    </div>
  </div>
</div>

<div id="host_metric_graphs">
  {include('cluster_host_metric_graphs.tpl')}
</div>

<!-- End cluster_view.tpl -->
