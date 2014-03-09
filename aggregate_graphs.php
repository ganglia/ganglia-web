<script>
  function refreshAggregateGraph() {
    $("#aggregate_graph_display img").each(function (index) {
	var src = $(this).attr("src");
	if (src.indexOf("graph.php") == 0) {
	  var d = new Date();
	  $(this).attr("src", jQuery.param.querystring(src, "&_=" + d.getTime()));
	}    
    });
  }

  function createAggregateGraph() {
    if ($('#hreg').val() == "" || $('#metric_chooser').val() == "") {
      alert("Host regular expression and metric name can't be blank");
      return false;
    }

    var params = $("#aggregate_graph_form").serialize() + "&aggregate=1";
    $("#show_direct_link").html("<a href='graph_all_periods.php?" + params + "'>Direct Link to this aggregate graph</a>");
    $("#aggregate_graph_display").html('<img src="img/spinner.gif">');
    $.ajax({url: 'graph_all_periods.php', 
	    cache: false,
	    data: params + "&embed=1" , 
	    success: function(data) {
      $("#aggregate_graph_display").html(data);
	}});
    return false;
  }

$(function() {
   
  var availablemetrics = [
<?php

  require_once('./eval_conf.php');
  require_once('./functions.php');

  $available_metrics = array();
  retrieve_metrics_cache("metric_list");

  ksort($index_array['metrics']);
  foreach ($index_array['metrics'] as $value) {
    $available_metrics[] = "\"$value\"";
  }

  print join(",", $available_metrics);
  unset($available_metrics);
?>];
   
  $( ".ag_buttons" ).button();
  $( "#graph_type_menu" ).buttonset();
  $( "#graph_legend_menu" ).buttonset();

  $("#hreg").change(function() {
    $.cookie("ganglia-aggregate-graph-hreg" + window.name,
      $("#hreg").val());
  });

  $("#vl").change(function() {
    $.cookie("ganglia-aggregate-graph-vl" + window.name,
      $("#vl").val());
  });

  $("#x").change(function() {
    $.cookie("ganglia-aggregate-graph-upper" + window.name,
      $("#x").val());
  });

  $("#n").change(function() {
    $.cookie("ganglia-aggregate-graph-lower" + window.name,
      $("#n").val());
  });

  $("#title").change(function() {
    $.cookie("ganglia-aggregate-graph-title" + window.name,
      $("#title").val());
  });

  $("#aggregate_graph_table_form input[name=gtype]").change(function() {
    var gtype = $("#aggregate_graph_table_form input[name=gtype]:checked").val();
    $.cookie("ganglia-aggregate-graph-gtype" + window.name, gtype);
  });

  $("#aggregate_graph_table_form input[name=glegend]").change(function() {
    var glegend = $("#aggregate_graph_table_form input[name=glegend]:checked").val();
    $.cookie("ganglia-aggregate-graph-glegend" + window.name, glegend);
  });

  function restoreAggregateGraph() {
    var hreg = $.cookie("ganglia-aggregate-graph-hreg" + window.name);
    if (hreg != null)
      $("#hreg").val(hreg);
  
    var gtype = $.cookie("ganglia-aggregate-graph-gtype" + window.name);
    if (gtype != null) {
      if (gtype == "line")
	$("#gtline").click();
      else
	$("#gtstack").click();
    }

    var glegend = $.cookie("ganglia-aggregate-graph-glegend" + window.name);
    if (glegend != null) {
      if (glegend == "show")
	$("#glshow").click();
      else
	$("#glhide").click();
    }
  
    var metric = $.cookie("ganglia-aggregate-graph-metric" + window.name);
    if (metric != null)
      $("#metric_chooser").val(metric);

    var title = $.cookie("ganglia-aggregate-graph-title" + window.name);
    if (title != null)
      $("#title").val(title);

    var vl = $.cookie("ganglia-aggregate-graph-vl" + window.name);
    if (vl != null)
      $("#vl").val(vl);

    var upper = $.cookie("ganglia-aggregate-graph-upper" + window.name);
    if (upper != null)
      $("#x").val(upper);

    var lower = $.cookie("ganglia-aggregate-graph-lower" + window.name);
    if (lower != null)
      $("#n").val(lower);
  
    if (hreg != null && metric != null)
      return true;
    else
      return false;
  }

  $( "#metric_chooser" ).autocomplete({
      source: availablemetrics,
      change: function(event, ui) {
	$.cookie("ganglia-aggregate-graph-metric" + window.name,
	         $("#metric_chooser").val());
      }
  });

  if (restoreAggregateGraph())
    createAggregateGraph();
});
</script>
<div id="aggregate_graph_header">
<h2>Create aggregate graphs</h2>
<form id="aggregate_graph_form">
<table id="aggregate_graph_table_form">
<tr>
<td>Title:</td>
<td colspan=2><input name="title" id="title" value="" size=60></td>
</tr>
<tr>
<td>Vertical (Y-Axis) label:</td>
<td colspan=2><input name="vl" id="vl" value="" size=60></td>
</tr>
<tr>
<td>Limits</td><td>Upper:<input style="margin-left:5px;margin-right:10px;" name="x" id="x" value="" size=10>Lower:<input style="margin-left:5px;" name="n" id="n" value="" size=10></td>
</tr>
<tr>
<td>Host Regular expression e.g. web-[0,4], web or (web|db):</td>
<td colspan=2><input name="hreg[]" id="hreg" size=60></td>
</tr>
<tr><td>Metric Regular expression (not a report e.g. load_one, bytes_(in|out)):</td>
<td colspan=2><input name="mreg[]" id="metric_chooser" size=60></td>
</tr>
<tr>
<td>Graph Type:</td><td>
<div id="graph_type_menu"><input type="radio" name="gtype" id="gtline" value="line" checked /><label for="gtline">Line</label>
<input type="radio" name="gtype" id="gtstack" value="stack" /><label for="gtstack">Stacked</label></div>
</td>
</tr>
<tr>
<td>Legend options:</td><td>
<div id="graph_legend_menu"><input type="radio" name="glegend" id="glshow" value="show" checked /><label for="glshow">Show legend</label>
<input type="radio" name="glegend" id="glhide" value="hide" /><label for="glhide">Hide legend</label></div>
</td>
</tr>
<tr>
<td>
</td>
<td>
<button class="ag_buttons" onclick="createAggregateGraph(); return false">Create Graph</button></td>
</tr>
</table>
</form>
</div>
<div style="margin-bottom:5px;background-color:#eeeeee;text-align:center;padding:5px;" id="show_direct_link"></div>
<div id="aggregate_graph_display">
</div>

