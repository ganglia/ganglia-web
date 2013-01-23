<script language="javascript" type="text/javascript" src="js/jquery.flot.min.js"></script>
<script language="javascript" type="text/javascript" src="js/jquery.flot.crosshair.min.js"></script>
<script language="javascript" type="text/javascript" src="js/jquery.flot.stack.min.js"></script>
<script language="javascript" type="text/javascript" src="js/jquery.multiselect.js"></script>
<script language="javascript" type="text/javascript" src="js/jquery.flot.selection.min.js"></script>
<script language="javascript" type="text/javascript" src="js/jquery.flot.events.js"></script>
<script type="text/javascript" src="js/create-flot-graphs.js"></script>

<div id="inspect_graph_container">
  <div id="placeholder" style="overflow:hidden"></div>
  <div id="spacer" style="height:5px;"></div>
  <div id="graphcontrols"></div>
</div>

<?php
include_once "./eval_conf.php";

$dataurl = "graph.php?" . $_SERVER['QUERY_STRING'];
$refresh_interval = $conf['default_refresh'];
?>

<script>
$(function () {
  var refresh_timer = null;
  var first_time = true;
  var VALUE_SEPARATOR = " :: ";
  var plot = null;

  function resize() {
    var popupDialog = $("#popup-dialog");
    $("#placeholder").height(popupDialog.height() -
			     $("#graphcontrols").height() - 
			     $("#spacer").height());
    $("#inspect_graph_container").width(popupDialog.width() - 20);
  }

  $("#popup-dialog").bind("dialogresizestop.inspect", 
			  function() {
			    resize();
			    plot.resize();
			    plotAccordingToChoices();
			  });
  $("#popup-dialog").bind("dialogclose.inspect", 
			  function(event) {
			    $(this).unbind(".inspect");
                            if (refresh_timer) {
			      clearTimeout(refresh_timer);
			      refresh_timer = null;
			    }
			  });
    
  var datasets = []; // global array of dataset objects {label, data, color}
  var plotRanges = null;
  var graph_title = null;

  var placeHolder = $("#placeholder");
  placeHolder.bind("plothover", hoverHandler);
  placeHolder.bind("plotselected", selectRangeHandler);
    
  var dataurl = '<?php print $dataurl; ?>';
  var refresh_interval = '<?php print $refresh_interval; ?>';

  var graphControls = $("#graphcontrols");
  var series_select = '<select id="select_series" name="select_series" multiple="multiple"></select>';
  // Add multi-select menu to controls
  graphControls.append(series_select);
    
  var select_series = $("#select_series");
  select_series.multiselect({
    height: "auto",
    position : {
      my: "left top",
      at: "left bottom"
    },
    checkAll: function(event, ui) {
      plotAccordingToChoices();
    },
    uncheckAll: function(event, ui) {
      plotAccordingToChoices();
    },
    click: function(event, ui) {
      plotAccordingToChoices();
    }
  });

  var html = '<span id="gopt" style="margin-left:10px;"><input type="radio" id="line" name="gopt"/><label style="font-size:0.825em;" for="line">Line</label><input type="radio" id="stack" name="gopt"/><label style="font-size:0.825em" for="stack">Stack</label></span>';
  html += '<input id="resetzoom" type="button" style="font-size:0.825em;" value="Reset zoom"/>';

  // Add option buttons to controls 
  graphControls.append(html);
  
  $("#gopt").buttonset();
  $("#line").button().click(function() {
    plotAccordingToChoices();
  });
  $("#stack").button().click(function() {
    plotAccordingToChoices();
  });
  $("#resetzoom").button();
  $("#resetzoom").click(function () {
    plotRanges = null;
    plotAccordingToChoices();  
  });
  var plotOpt =  {
    lines: { show: true, fill: false },
    points: { show: false },
    crosshair: { mode: "x" },
    xaxis: { mode: "time" },
    yaxis: {tickFormatter: suffixFormatter},
    selection: { mode: "xy" },
    legend: {show : false},
    grid: { hoverable: true, autoHighlight: true },
    series: {stack: null}
  };

  // then fetch the data with jQuery
  function onDataReceived(series) {
    datasets = series;

    var stacked = false;
    var series_select = $("#select_series");
    var series_labels = 
      $("#select_series>option").map(function(){
	  var text = $(this).text();
	  var label = text;
	  if (text.indexOf(VALUE_SEPARATOR) != -1)
	    label = text.split(VALUE_SEPARATOR)[0];
	  return label;
	});
    var series_options = $("#select_series>option").map(function(){
	return $(this);
      });

    var start_time = Number.MAX_VALUE;
    var end_time = 0;

    var i = 0;
    $.each(datasets, function(key, dataset) {
      start_time = Math.min(dataset.data[0][0], start_time);
      end_time = Math.max(dataset.data[dataset.data.length - 1][0], 
			  end_time);

      // Explicity delete the stack attribute if it exists because stacking
      // is controlled locally. The incoming datasets will contain a 
      // stack attribute if they were generated from a stacked graph.
      if ("stack" in dataset) {
	delete dataset.stack;
	stacked = true;
      }

      if ((graph_title == null) && ("graph_title" in dataset))
        $("#popup-dialog").dialog('option', 'title', dataset.graph_title);

      if (typeof dataset.color == 'undefined')
	dataset.color = i;

      i++;

      var current_value = 
	formattedSiVal(dataset.data[dataset.data.length - 1][1], 2);
      var seriesIndex = $.inArray(dataset.label, series_labels);
      if (seriesIndex == -1) {
	var option = $('<option/>', 
	  {value: key, 
	   text: dataset.label +
	      VALUE_SEPARATOR +
	      current_value});
	option.attr('selected', 'selected');
	option.appendTo(series_select);
	var colorBox = '<div style="border:1px solid #ccc;padding:1px;display:inline-block;"><div style="width:4px;height:0;border:5px solid ' + dataset.color + ';overflow:hidden"></div></div>';
	option.data("pre_checkbox_html", colorBox);
      } else {
	var option = series_options[seriesIndex];
	var label = series_labels[seriesIndex] + 
	  VALUE_SEPARATOR + 
	  current_value;
	option.text(label);
      }
    });
      
    series_select.multiselect('refresh');

    if (first_time) {
      var gopt = stacked ? $("#stack") : $("#line");
      gopt.attr("checked", "checked");
      gopt.button("refresh");

      if (($("#popup-dialog").dialog("option", "height") == "auto") ||
	  ($("#popup-dialog").dialog("option", "width") == "auto")) {
	$("#popup-dialog").dialog("option", "height", 500);
	$("#popup-dialog").dialog("option", "width", 800);
      }
      resize();
      first_time = false;
    }

    $.ajax({
      url: "get_overlay_events.php?start=" + (start_time/1000) + "&end=" + (end_time/1000),
      method: 'GET',
      dataType: 'json',
      success: onEventsReceived
    });
  }

  function onEventsReceived(overlay_events) {
    var events = [];
    $.each(overlay_events, function(key, val) {
	var event = {};
	event['min'] = val['start_time'] * 1000;
	event['max'] = val['end_time'] * 1000;
	event['eventType'] = 'info';
	event['title'] = val['summary'];
	event['description'] = val['description'];
	events[events.length] = event;
      });

    var event_types = {};
    event_types["info"] = {eventType: "info", 
                           level: 1,
                           icon: {image: "img/red-pointer.png", 
                                  width: 10, 
                                  height: 10}};
                
    plotOpt.events = {
      levels: 1,
      data: events,
      types: event_types,
      xaxis: 1
    };

    plotAccordingToChoices();

    if ((plotRanges == null) && (dataurl.indexOf("&r=custom") == -1))
      refresh_timer = setTimeout(refresh, refresh_interval * 1000);
  }
  
  $.ajax({
    url: dataurl,
    method: 'GET',
    dataType: 'json',
    success: onDataReceived
  });

  function utcTimeStr(tstamp) {
    var date = new Date(tstamp);

    var month = date.getUTCMonth() + 1;
    if ( month < 10 )
      month = "0" + month;
    var day = date.getUTCDate();
    if ( day < 10 )
      day = "0" + day;
    var hr = date.getUTCHours();
    if (hr < 10)
      hr = "0" + hr; 
    var min = date.getUTCMinutes();
    if (min < 10)
      min = "0" + min; 
    var sec = date.getUTCSeconds();
    if (sec < 10)
      sec = "0" + sec; 
    return date.getUTCFullYear() + "-" + month + "-" + day + " " + hr + ":" + min + ":" + sec;
  }

  function showTooltip(x, y, contents) {
    $('<div id="tooltip">' + contents + '</div>').css( {
      position: 'absolute',
      display: 'none',
      'z-index': 2000,
      top: y + 5,
      left: x + 5,
      border: '1px solid #fdd',
      padding: '2px',
      'background-color': '#fee',
      opacity: 0.80
    }).appendTo("body").fadeIn(200);
  }

  var previousPoint = null;

  function formattedSiVal(val, places) {
    if (val >= 1000000000) {
      return (val / 1000000000).toFixed(places) + " G";
    }
    if (val >= 1000000) {
      return (val / 1000000).toFixed(places) + " M";
    }
    if (val >= 1000) {
      return (val / 1000).toFixed(places) + " k";
    }
        
    return (val/1).toFixed(places);
  }
  
  function suffixFormatter(val, axis) {
    var tickd = axis.tickDecimals;
    if (tickd <= 0) {
      tickd = 1;
    }
        
    return formattedSiVal(val, tickd);
  }

  function selectRangeHandler(event, ranges) {
    if ($("#event_tooltip")[0])
      return;
    plotRanges = ranges;
    plotAccordingToChoices();
  }

  function hoverHandler(event, pos, item) {
    $("#x").text(utcTimeStr(pos.x));
    $("#y").text(pos.y.toFixed(2));

    if (item) {
      if (previousPoint != item.dataIndex) {
	previousPoint = item.dataIndex;
                
	$("#tooltip").remove();
	var y = formattedSiVal(item.datapoint[1], 2);
	showTooltip(item.pageX, 
		    item.pageY,
		    item.series.label + " at " + 
		    utcTimeStr(item.datapoint[0]) + 
		    " = " + y);
      }
    } else {
      $("#tooltip").remove();
      previousPoint = null;            
    }
  }

  function refresh() {
    $.ajax({
      url: dataurl,
      method: 'GET',
      dataType: 'json',
      success: onDataReceived});
  }

  function plotAccordingToChoices() {
    var selected_series = $("#select_series").multiselect("getChecked").map(function(){return this.value}).get();
    var data = [];
    for (var i = 0; i < selected_series.length; i++) {
      data.push(datasets[selected_series[i]]);
    }

    var stack = $("#stack").attr('checked') == 'checked';

    plotOpt.lines.fill = stack;
    plotOpt.series.stack = stack ? 1 : null;

    // Apply zoom if set
    if (plotRanges != null) {
      if (plotRanges.xaxis.to - plotRanges.xaxis.from < 0.00001)
        plotRanges.xaxis.to = plotRanges.xaxis.from + 0.00001;
      if (plotRanges.yaxis.to - plotRanges.yaxis.from < 0.00001)
        plotRanges.yaxis.to = plotRanges.yaxis.from + 0.00001;

      plotOpt.xaxis.min = plotRanges.xaxis.from;
      plotOpt.xaxis.max = plotRanges.xaxis.to;
      plotOpt.yaxis.min = plotRanges.yaxis.from;
      plotOpt.yaxis.max = plotRanges.yaxis.to;
    } else {
      delete plotOpt.xaxis.min;
      delete plotOpt.xaxis.max;
      delete plotOpt.yaxis.min;
      delete plotOpt.yaxis.max;
    }

    plot = $.plot($("#placeholder"), data, plotOpt);
  }
});
</script>
