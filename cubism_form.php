<script>
  function refreshCubismGraph() {
    $("#cubism_graph_display img").each(function (index) {
	var src = $(this).attr("src");
	if (src.indexOf("graph.php") == 0) {
	  var d = new Date();
	  $(this).attr("src", jQuery.param.querystring(src, "&_=" + d.getTime()));
	}    
    });
  }

  function createCubismGraph() {
    if ($('#hreg').val() == "" || $('#metric_chooser').val() == "") {
      alert("Host regular expression and metric name can't be blank");
      return false;
    }

    this.form.submit();
/*    var params = $("#cubism_graph_form").serialize() + "&cubism=1";
    $("#cubism_graph_display").html('<img src="img/spinner.gif">');
    $.ajax({url: 'generate_cubism.php', 
	    cache: false,
	    data: params, 
	    success: function(data) {
      $("#cubism_graph_display").html(data);
	}});
    return false;
    */
  }

$(function() {
   
  var availablemetrics = [
<?php

  require_once('./eval_conf.php');
  require_once('./functions.php');

  $available_metrics = array();
  retrieve_metrics_cache("metric_list");

  asort($index_array['metrics']);
  foreach ($index_array['metrics'] as $key => $value) {
    $available_metrics[] = "\"$value\"";
  }

  print join(",", $available_metrics);
  unset($available_metrics);
?>];
   
  $( ".ag_buttons" ).button();

  $("#hreg").change(function() {
    $.cookie("ganglia-cubism-graph-hreg" + window.name,
      $("#hreg").val());
  });

  $("#max").change(function() {
    $.cookie("ganglia-cubism-graph-upper" + window.name,
      $("#max").val());
  });

  $("#min").change(function() {
    $.cookie("ganglia-cubism-graph-lower" + window.name,
      $("#min").val());
  });

  function restoreCubismGraph() {
    var hreg = $.cookie("ganglia-cubism-graph-hreg" + window.name);
    if (hreg != null)
      $("#hreg").val(hreg);
  
    var gtype = $.cookie("ganglia-cubism-graph-gtype" + window.name);
    if (gtype != null) {
      if (gtype == "line")
	$("#gtline").click();
      else
	$("#gtstack").click();
    }

    var glegend = $.cookie("ganglia-cubism-graph-glegend" + window.name);
    if (glegend != null) {
      if (glegend == "show")
	$("#glshow").click();
      else
	$("#glhide").click();
    }
  
    var metric = $.cookie("ganglia-cubism-graph-metric" + window.name);
    if (metric != null)
      $("#metric_chooser").val(metric);

    var title = $.cookie("ganglia-cubism-graph-title" + window.name);
    if (title != null)
      $("#title").val(title);

    var vl = $.cookie("ganglia-cubism-graph-vl" + window.name);
    if (vl != null)
      $("#vl").val(vl);

    var upper = $.cookie("ganglia-cubism-graph-upper" + window.name);
    if (upper != null)
      $("#x").val(upper);

    var lower = $.cookie("ganglia-cubism-graph-lower" + window.name);
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
	$.cookie("ganglia-cubism-graph-metric" + window.name,
	         $("#metric_chooser").val());
      }
  });

  if (restoreCubismGraph())
    createCubismGraph();
});

</script>
<div id="cubism_graph_header">
<form target="_blank" action="cubism.php" id="cubism_graph_form">
<table id="cubism_graph_table_form">
<tr>
  <td>Host Regular expression:</td>
  <td> <input name="hreg[]" id="hreg" size=30></td>
</tr>
<tr>
  <td>Metric Regular expression:</td>
  <td> <input name="mreg[]" id="metric_chooser" size=30></td>
</tr>
<tr>
  <td>Limits Min:</td>
  <td><input style="margin-left:5px;margin-right:10px;" name="min" id="min" value="" size=15></td>
</tr>
<tr>
  <td>Max:</td>
  <td><input style="margin-left:5px;" name="max" id="max" value="" size=15>
</td>
<tr>
  <td>Graph height:</td>
  <td><input style="margin-left:5px;" name="height" id="height" value="30" size=5>
</td>
<tr>
  <td>
    <button class="ag_buttons" onclick="createCubismGraph();">Create Graph</button>
  </td>
</tr>
</table>
</form>
</div>
<div style="margin-bottom:5px;background-color:#eeeeee;text-align:center;padding:5px;" id="show_direct_link"></div>
<div id="cubism_graph_display">
</div>