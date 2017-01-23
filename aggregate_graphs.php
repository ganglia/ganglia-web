<script type="text/javascript" src="js/jquery-ui-timepicker-addon.js"></script>
<script type="text/javascript">
  function refreshAggregateGraph() {
    $("#aggregate_graph_display img").each(function (index) {
	var src = $(this).attr("src");
	if (src.indexOf("graph.php") == 0) {
	  var d = new Date();
	  $(this).attr("src", jQuery.param.querystring(src, "&_=" + d.getTime()));
	}
    });
  }

  function clearTime() {
    $("#datepicker_cs").val("");
    $("#datepicker_ce").val("");
  }

  function createAggregateGraph() {
    if ($('#hreg').val() == "" || $('#aggregate_graph_metric_chooser').val() == "") {
      alert("Host regular expression and metric name can't be blank");
      return false;
    }

    var params = $("#aggregate_graph_form").serialize() + "&aggregate=1";
    $("#aggregate_graph_display").html('<img src="img/spinner.gif">');
    if ($('#datepicker_cs').val() == "" && $('#datepicker_ce').val() == "") {
      $("#show_direct_link").html("<a href='graph_all_periods.php?" + params + "'>Direct Link to this aggregate graph</a>");
      $.ajax({url: 'graph_all_periods.php',
	    cache: false,
	    data: params + "&embed=1",
	    success: function(data) {
      $("#aggregate_graph_display").html(data);
	}});
    } else {
      $("#show_direct_link").html("<a href='graph.php?" + params + "'>Direct Link to this aggregate graph</a>");
      $("#aggregate_graph_display").html('<img src="graph.php?'  + params + '">');
    }
    return false;
  }

$(function() {
  $( ".ag_buttons" ).button();
  $( "#gtstack" ).checkboxradio( { icon: false } );
  $( "#gtline" ).checkboxradio( { icon: false } );
  $( "#graph_type_menu" ).controlgroup();
  $( "#glshow" ).checkboxradio( { icon: false } );
  $( "#glhide" ).checkboxradio( { icon: false } );
  $( "#graph_legend_menu" ).controlgroup();

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
    else
      $("#hreg").val(".*");

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

    var mreg = $.cookie("ganglia-aggregate-graph-metric" + window.name);
    if (mreg != null) {
      var metric_chooser = $("#aggregate_graph_metric_chooser");
      var metrics = mreg.split("_|_");
      for (var i = 0; i < metrics.length; i++) {
	// Ensure that all selected options are included in the list
        if (metric_chooser.find("option[value='" + metrics[i] + "']").length == 0) {
	  var option = new Option(metrics[i], metrics[i], true, true);
	  metric_chooser.append(option);
	}
      }
      metric_chooser.val(metrics).trigger("change");
    }

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

    if (mreg != null)
      return true;
    else
      return false;
  }

  $( "#aggregate_graph_metric_chooser" ).select2({
    placeholder: "Select and/or define metrics to be plotted",
    tags: true,
    tokenSeparators: [',', ' ']
  }).
  on('change', function(event) {
    if (event.target == this) {
      var selected_metrics = $(this).val();
      $.cookie("ganglia-aggregate-graph-metric" + window.name,
	       selected_metrics ? selected_metrics.join("_|_") : "");
    }
  });

  if (restoreAggregateGraph())
    createAggregateGraph();

  var dateTimePickerOptions = {
    showOn: "button",
    constrainInput: false,
    buttonImage: "img/calendar.gif",
    buttonImageOnly: true
  };

  $("#datepicker_cs").datetimepicker(dateTimePickerOptions);
  $("#datepicker_ce").datetimepicker(dateTimePickerOptions);

});
</script type="text/javascript">
<div id="aggregate_graph_header">
<h2>Create aggregate graphs</h2>
<form id="aggregate_graph_form">
<table id="aggregate_graph_table_form">
<tr>
<td>Title:</td>
<td colspan=2><input name="title" id="title" value="" size=60></td>
</tr>
<tr>
<td>Time:</td>
<td>From:<input name="cs" id="datepicker_cs" value="" size=17>
To:<input name="ce" id="datepicker_ce" value="" size=17>&nbsp;
<input type="button" value="Clear" onclick="clearTime()"></td>
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
<tr><td>Metric Regular expression(s):</td>
<td colspan=2><select name="mreg[]" id="aggregate_graph_metric_chooser" multiple style="width:100%;">
<?php
  require_once('./eval_conf.php');
  require_once('./functions.php');

  retrieve_metrics_cache("metric_list");

  # If metric_list hash exists we pulled it out of cache. Otherwise
  # it was just fetched from gmetad so we need to massage the output
  if (!isset($index_array['metric_list'])) {
    $index_array['metric_list'] = array_keys($index_array["metrics"]);
  }

  asort($index_array['metric_list']);
  foreach ($index_array['metric_list'] as $metric) {
    print "<option value='" . $metric . "'>" . $metric . "</option>";
  }
?>
</select></td>
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
