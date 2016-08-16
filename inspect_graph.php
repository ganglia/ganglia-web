<?php
include_once "./eval_conf.php";
?>

<div id="inspect_graph_container"></div>

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
  var zooming = false;
  var graphContainer = $("#inspect_graph_container");
  var placeHolder = null;
  var graphControls = null;
  var spacer = null;
  var selectSeries = null;
  var selectLine = null;
  var selectStack = null;
  var tooltip = null;
  var lastUpdate = 0;
  var tz = getTimezone();

  function zeroOutMissing(dataset, index) {
    if (dataset.data == null || index < 0)
      return;

    for (var i = 0; i <= index; i++) {
      if (dataset.data[i][1] == "NaN")
	dataset.data[i][1] = 0;
    }
  }

  function lastUpdateIndex(datasets) {
    var index = 0;
    $.each(datasets, function(key, dataset) {
      if (dataset.data == null)
	return;

      for (var i = dataset.data.length - 1;
	   i >= 0 && i > index;
	   i--) {
	if (dataset.data[i][1] != "NaN") {
	  index = i;
	  break;
	}
      }
    });
    return index;
  }

  function getTimezone() {
    var tzValue = "browser";
    var tz = $("#tz");
    if (tz[0] && tz.val() === "")
      tzValue = server_timezone;
    return tzValue;
  }

  function resize() {
    var popupDialog = $("#popup-dialog");
    placeHolder.height(popupDialog.height() -
		       graphControls.height() -
		       spacer.height());
    graphContainer.width(popupDialog.width() - 20);
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

  graphContainer.append('<div id="placeholder" style="overflow:hidden"></div><div id="spacer" style="height:5px;"></div><div id="graphcontrols"></div>');
  spacer = graphContainer.find("#spacer");

  var datasets = []; // global array of dataset objects {label, data, color}
  var graph_title = null;

  placeHolder = graphContainer.find("#placeholder");
  placeHolder.bind("plothover", hoverHandler);
  placeHolder.bind("plotselected", selectRangeHandler);

  var dataurl = '<?php print $dataurl; ?>';
  var refresh_interval = '<?php print $refresh_interval; ?>';

  graphControls = graphContainer.find("#graphcontrols");
  var series_select = '<select id="select_series" name="select_series" multiple="multiple"></select>';
  // Add multi-select menu to controls
  graphControls.append(series_select);

  selectSeries = graphControls.find("#select_series");
  selectSeries.multiselect({
    height: "auto",
    position : {
      my: "right bottom",
      at: "left top"
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
    }).multiselectfilter();
  $(".ui-multiselect-menu").draggable();

  var html = '<span id="gopt" style="margin-left:10px;"><input type="radio" id="line" name="gopt"/><label style="font-size:0.825em;" for="line">Line</label><input type="radio" id="stack" name="gopt"/><label style="font-size:0.825em" for="stack">Stack</label></span>';
  html += '<input id="resetzoom" type="button" style="font-size:0.825em;" value="Reset zoom"/>';

  // Add option buttons to controls
  graphControls.append(html);

  graphControls.find("#gopt").controlgroup();
  selectLine = graphControls.find("#line");
  selectLine.checkboxradio({icon: false}).click(function() {
      plotAccordingToChoices();
    });
  selectStack = graphControls.find("#stack");
  selectStack.checkboxradio({icon: false}).click(function() {
      plotAccordingToChoices();
    });
  var resetZoomElem = graphControls.find("#resetzoom");
  resetZoomElem.button();
  resetZoomElem.click(function () {
    delete plotOpt.xaxis.min;
    delete plotOpt.xaxis.max;
    delete plotOpt.yaxis.min;
    delete plotOpt.yaxis.max;
    zooming = false;
    plotAccordingToChoices();
  });
  var plotOpt =  {
    lines: { show: true, fill: false },
    points: { show: false },
    crosshair: { mode: "x" },
    xaxis: { mode: "time", timezone: tz },
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
    var series_labels =
      selectSeries.children("option").map(function(){
	  var text = $(this).text();
	  var label = text;
	  if (text.indexOf(VALUE_SEPARATOR) != -1)
	    label = text.split(VALUE_SEPARATOR)[0];
	  return label;
	});
    var series_options = selectSeries.children("option").map(function(){
	return $(this);
      });

    var start_time = Number.MAX_VALUE;
    var end_time = 0;

    // Determine point index corresponding to last update
    // Typically the dataset will have trailing NaNs that
    // should be ignored
    lastUpdate = lastUpdateIndex(datasets);

    var i = 0;
    $.each(datasets, function(key, dataset) {
      if (dataset.data == null)
	return;

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

      var current_value = dataset.data[lastUpdate][1];
      if (current_value != "NaN")
	current_value = formattedSiVal(current_value, 2);
      else
        current_value = "";
      var seriesIndex = $.inArray(dataset.label, series_labels);
      if (seriesIndex == -1) {
	var option = $('<option/>',
	  {value: key,
	   text: dataset.label +
	      VALUE_SEPARATOR +
	      current_value});
	option.prop('selected', true);
	option.appendTo(selectSeries);
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

    selectSeries.multiselect('refresh');

    if (first_time) {
      $("#popup-dialog-navigation").html("");
      var gopt = stacked ? selectStack : selectLine;
      gopt.prop("checked", true).checkboxradio("refresh");

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

    if ((!zooming) && (dataurl.indexOf("&r=custom") == -1))
      refresh_timer = setTimeout(refresh, refresh_interval * 1000);
  }

  $.ajax({
    url: dataurl + '&maxrows=' + placeHolder.width(),
    method: 'GET',
    dataType: 'json',
    success: onDataReceived
  });

  function showTooltip(x, y, contents) {
    tooltip = $('<div id="tooltip">' + contents + '</div>').css( {
      position: 'absolute',
      display: 'none',
      'z-index': 2000,
      top: y - 20,
      left: x + 10,
      border: '1px solid #fdd',
      padding: '2px',
      'background-color': '#fee',
      opacity: 1.0
    }).appendTo("body").fadeIn(200);
  }

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

    var plotRanges = ranges;

    if (plotRanges.xaxis.to - plotRanges.xaxis.from < 0.00001)
      plotRanges.xaxis.to = plotRanges.xaxis.from + 0.00001;
    if (plotRanges.yaxis.to - plotRanges.yaxis.from < 0.00001)
      plotRanges.yaxis.to = plotRanges.yaxis.from + 0.00001;

    plotOpt.xaxis.min = plotRanges.xaxis.from;
    plotOpt.xaxis.max = plotRanges.xaxis.to;
    plotOpt.yaxis.min = plotRanges.yaxis.from;
    plotOpt.yaxis.max = plotRanges.yaxis.to;

    zooming = true;
    plotAccordingToChoices();
  }

  function hoverHandler(event, pos, item) {
    if (item) {
      if (tooltip != null)
	tooltip.remove();
      var y = formattedSiVal(item.datapoint[1], 2);
      var time = (tz === "browser") ?
	moment(item.datapoint[0]) : moment(item.datapoint[0]).tz(tz);
      showTooltip(item.pageX,
		  item.pageY,
		  item.series.label + " at " +
		  time.format("MM/DD/YYYY HH:mm:ss") +
		  " = " + y);
    } else {
      if (tooltip != null)
	tooltip.remove();
    }
  }

  function refresh() {
    $.ajax({
      url: dataurl + '&maxrows=' + placeHolder.width(),
      method: 'GET',
      dataType: 'json',
      success: onDataReceived});
  }

  function updatePlotOptions() {
    plot.getOptions().events.data = plotOpt.events.data;
    if (zooming) {
      plot.getOptions().xaxes[0].min = plotOpt.xaxis.min;
      plot.getOptions().xaxes[0].max = plotOpt.xaxis.max;
      plot.getOptions().yaxes[0].min = plotOpt.yaxis.min;
      plot.getOptions().yaxes[0].max = plotOpt.yaxis.max;
    } else {
      delete plot.getOptions().xaxes[0].min;
      delete plot.getOptions().xaxes[0].max;
      delete plot.getOptions().yaxes[0].min;
      delete plot.getOptions().yaxes[0].max;
    }
    plot.getOptions().series.stack = plotOpt.series.stack;
    plot.getOptions().series.lines.fill = plotOpt.lines.fill;
    tz = getTimezone();
    plot.getOptions().xaxis.timezone = tz;
  }

  function plotAccordingToChoices() {
    var stack = selectStack.prop('checked');

    var selected_series = selectSeries.multiselect("getChecked").map(function(){return this.value}).get();
    var data = [];
    for (var i = 0; i < selected_series.length; i++) {
      var dataset = datasets[selected_series[i]];
      data.push(dataset);
      // The Flot stack plugin does not handle missing data correctly
      if (stack)
	zeroOutMissing(dataset, lastUpdate);
    }

    plotOpt.lines.fill = stack;
    plotOpt.series.stack = stack ? 1 : null;

    if (plot == null) {
      plot = $.plot(placeHolder, data, plotOpt);
    } else {
      plot.clearEvents();
      plot.clearSelection();
      updatePlotOptions(); // must precede call to setData()
      plot.setData(data);
      plot.setupGrid();
      plot.draw();
    }
  }
});
</script>
