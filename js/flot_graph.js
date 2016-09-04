var VALUE_SEPARATOR = " :: ";

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

var g_flot_graph_counter = 0;

function FlotGraph(graphArgs, refreshInterval, timezone) {
  this.dataurl = "graph.php?flot=1&" + graphArgs;
  this.refreshInterval = refreshInterval;
  this.uuid = "flot_graph_" + g_flot_graph_counter++;
  this.refresh_timer = null;
  this.plot = null;
  this.zooming = false;
  this.$placeHolder = null;
  this.$graphControls = null;
  this.$spacer = null;
  this.$selectSeries = null;
  this.$tooltip = null;
  this.lastUpdate = 0;
  this.tz = timezone;
  this.html = "";
  this.plotOpt = null;
  this.datasets = null;
  this.graph_title = null;
  this.legendColorBox = {};
  this.stackOpt = null;
}

FlotGraph.prototype.placeHolderId = function() {
  return this.uuid + '_placeholder';
}

FlotGraph.prototype.spacerId = function() {
  return this.uuid + '_spacer';
}

FlotGraph.prototype.graphControlsId = function() {
  return this.uuid + '_graphcontrols';
}

FlotGraph.prototype.selectSeriesId = function() {
  return this.uuid + '_select_series';
}

FlotGraph.prototype.gOptId = function() {
  return this.uuid + '_gopt';
}

FlotGraph.prototype.lineId = function() {
  return this.uuid + '_line';
}

FlotGraph.prototype.stackId = function() {
  return this.uuid + '_stack';
}

FlotGraph.prototype.clearStackOptId = function() {
  return this.uuid + '_clear_stack_opt';
}

FlotGraph.prototype.resetZoomId = function() {
  return this.uuid + '_resetzoom';
}

FlotGraph.prototype.tooltipId = function() {
  return this.uuid + '_tooltip';
}

FlotGraph.prototype.getBaseHtml = function() {
  this.html = '<div id="' + this.placeHolderId() + '" style="overflow:hidden"></div><div id="' + this.spacerId() + '" style="height:5px;"></div><div id="' + this.graphControlsId() + '"><select id="' + this.selectSeriesId() + '" name="' + this.selectSeriesId() + '" multiple="multiple"></select><span id="' + this.gOptId() + '" style="margin-left:10px;"><input type="radio" id="' + this.lineId() + '" name="' + this.gOptId() + '"/><label style="font-size:0.825em;" for="' + this.lineId() + '">Line</label><input type="radio" id="' + this.stackId() + '" name="' + this.gOptId() + '"/><label style="font-size:0.825em" for="' + this.stackId() + '">Stack</label><input id="' + this.clearStackOptId() + '" type="button" style="font-size:0.825em;" value="Clear"/></span><input id="' + this.resetZoomId() + '" type="button" style="font-size:0.825em;margin-left:10px;" value="Reset zoom"/></div>';
  return this.html;
}

FlotGraph.prototype.onEventsReceived = function(overlay_events) {
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

  this.plotOpt.events = {
    levels: 1,
    data: events,
    types: event_types,
    xaxis: 1
  };

  this.plotAccordingToChoices();

  if ((!this.zooming) && (this.dataurl.indexOf("&r=custom") == -1)) {
    var thisGraph = this;
    this.refresh_timer = setTimeout(
      function() {
        thisGraph.refresh();
      },
      this.refreshInterval * 1000);
  }
}

FlotGraph.prototype.onDataReceived = function(series) {
  this.datasets = series;

  var series_labels =
    this.$selectSeries.children("option").map(function() {
      var text = $(this).text();
      var label = text;
      if (text.indexOf(VALUE_SEPARATOR) != -1)
	label = text.split(VALUE_SEPARATOR)[0];
      return label;
    });
  var series_options = this.$selectSeries.children("option").map(function() {
    return $(this);
  });

  var start_time = Number.MAX_VALUE;
  var end_time = 0;

  // Determine point index corresponding to last update
  // Typically the dataset will have trailing NaNs that
  // should be ignored
  this.lastUpdate = lastUpdateIndex(this.datasets);

  var thisGraph = this;
  var i = 0;
  $.each(this.datasets, function(key, dataset) {
    if (dataset.data == null)
      return;

    start_time = Math.min(dataset.data[0][0], start_time);
    end_time = Math.max(dataset.data[dataset.data.length - 1][0],
			end_time);

    if (thisGraph.stackOpt == "stack")
      dataset.stack = 1;

    if (thisGraph.stackOpt == "line")
      delete dataset.stack;

    dataset.lines = { show: true };
    dataset.lines.fill = ("stack" in dataset);

    if ((thisGraph.graph_title == null) && ("graph_title" in dataset))
      thisGraph.graph_title = dataset.graph_title;

    if (typeof dataset.color == 'undefined')
      dataset.color = i;

    i++;

    var current_value = dataset.data[thisGraph.lastUpdate][1];
    if (current_value != "NaN")
      current_value = formattedSiVal(current_value, 2);
    else
      current_value = "";
    var seriesIndex = $.inArray(dataset.label, series_labels);
    var option = null;
    if (seriesIndex == -1) {
      option = $('<option/>',
	             {value: key,
	              text: dataset.label +
	              VALUE_SEPARATOR +
	              current_value});
      option.prop('selected', true);
      thisGraph.legendColorBox[dataset.label] = '<div style="border:1px solid #ccc;padding:1px;display:inline-block;"><div style="width:4px;height:0;border:5px solid ' + dataset.color + ';overflow:hidden"></div></div>';
      option.appendTo(thisGraph.$selectSeries);
    } else {
      option = series_options[seriesIndex];
      var label = series_labels[seriesIndex] +
	    VALUE_SEPARATOR +
	    current_value;
      option.text(label);
    }
  });

  this.$selectSeries.multiselect('refresh');

  // Add color keys into the legend
  this.$selectSeries.multiselect("widget").find("label").each(function() {
    var datasetName = $(this).text().split(VALUE_SEPARATOR)[0];
    $(this).prepend($(thisGraph.legendColorBox[datasetName]));
  });

  $.ajax({
    url: "get_overlay_events.php?start=" + (start_time/1000) + "&end=" + (end_time/1000),
    method: 'GET',
    dataType: 'json',
    context: this,
    success: this.onEventsReceived
  });
}

FlotGraph.prototype.showTooltip = function(x, y, contents) {
  this.$tooltip = $('<div id="' + this.tooltipId() + '">' + contents + '</div>').css( {
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

function selectRangeHandler(event, ranges) {
  var thisGraph = event.data.thisGraph;

  // If this selection is being generated by the event plugin then
  // dont zoom into the specified region
  if (thisGraph.plot.viewingEvent())
    return;

  var plotRanges = ranges;

  if (plotRanges.xaxis.to - plotRanges.xaxis.from < 0.00001)
    plotRanges.xaxis.to = plotRanges.xaxis.from + 0.00001;
  if (plotRanges.yaxis.to - plotRanges.yaxis.from < 0.00001)
    plotRanges.yaxis.to = plotRanges.yaxis.from + 0.00001;

  thisGraph.plotOpt.xaxis.min = plotRanges.xaxis.from;
  thisGraph.plotOpt.xaxis.max = plotRanges.xaxis.to;
  thisGraph.plotOpt.yaxis.min = plotRanges.yaxis.from;
  thisGraph.plotOpt.yaxis.max = plotRanges.yaxis.to;

  thisGraph.zooming = true;
  thisGraph.plotAccordingToChoices();
}

function hoverHandler(event, pos, item) {
  var thisGraph = event.data.thisGraph;

  if (item) {
    if (thisGraph.$tooltip != null) {
      thisGraph.$tooltip.remove();
      thisGraph.$tooltip = null;
    }
    var y = formattedSiVal(item.datapoint[1], 2);
    var time = (thisGraph.tz === "browser") ?
	  moment(item.datapoint[0]) :
          moment(item.datapoint[0]).tz(thisGraph.tz);
    thisGraph.showTooltip(item.pageX,
		          item.pageY,
		          item.series.label + " at " +
		          time.format("MM/DD/YYYY HH:mm:ss") +
		          " = " + y);
  } else {
    if (thisGraph.$tooltip != null) {
      thisGraph.$tooltip.remove();
      thisGraph.$tooltip = null;
    }
  }
}

FlotGraph.prototype.refresh = function() {
  $.ajax({
    url: this.dataurl + '&maxrows=' + this.$placeHolder.width(),
    method: 'GET',
    dataType: 'json',
    context: this,
    success: this.onDataReceived});
};

FlotGraph.prototype.setTimezone = function(tz) {
  this.tz = getTimezone();
  this.plot.getOptions().xaxis.timezone = this.tz;
};

FlotGraph.prototype.updatePlotOptions = function() {
  this.plot.getOptions().events.data = this.plotOpt.events.data;
  if (this.zooming) {
    this.plot.getOptions().xaxes[0].min = this.plotOpt.xaxis.min;
    this.plot.getOptions().xaxes[0].max = this.plotOpt.xaxis.max;
    this.plot.getOptions().yaxes[0].min = this.plotOpt.yaxis.min;
    this.plot.getOptions().yaxes[0].max = this.plotOpt.yaxis.max;
  } else {
    delete this.plot.getOptions().xaxes[0].min;
    delete this.plot.getOptions().xaxes[0].max;
    delete this.plot.getOptions().yaxes[0].min;
    delete this.plot.getOptions().yaxes[0].max;
  }
};

FlotGraph.prototype.plotAccordingToChoices = function() {
  var selected_series = this.$selectSeries.multiselect("getChecked").map(
    function() {
      return this.value;
    }).get();

  var data = [];
  for (var i = 0; i < selected_series.length; i++) {
    var dataset = this.datasets[selected_series[i]];
    data.push(dataset);
    // The Flot stack plugin does not handle missing data correctly
    if ("stack" in dataset)
      zeroOutMissing(dataset, this.lastUpdate);
  }

  if (this.plot == null) {
    this.plot = $.plot(this.$placeHolder, data, this.plotOpt);
  } else {
    this.plot.clearEvents();
    this.plot.clearSelection();
    this.updatePlotOptions(); // must precede call to setData()
    this.plot.setData(data);
    this.plot.setupGrid();
    this.plot.draw();
  }
};

function setStackOpt(event) {
  var thisGraph = event.data.thisGraph;
  thisGraph.setStackOpt(event.data.stackOpt);
};

FlotGraph.prototype.resize = function() {
  var $parent = this.$placeHolder.parent();
  this.$placeHolder.height($parent.height() -
		           this.$graphControls.height() -
		           this.$spacer.height());
  this.$placeHolder.width($parent.width());
};

FlotGraph.prototype.initialize = function() {
  this.$spacer = $(document).find("#" + this.spacerId());
  this.$placeHolder = $(document).find("#" + this.placeHolderId());
  this.$placeHolder.bind("plothover", {thisGraph: this}, hoverHandler);
  this.$placeHolder.bind("plotselected", {thisGraph: this}, selectRangeHandler);
  this.$graphControls = $(document).find("#" + this.graphControlsId());

  var thisGraph = this;

  this.$selectSeries = this.$graphControls.find("#" + this.selectSeriesId());
  this.$selectSeries.multiselect({
    height: "auto",
    position : {
      my: "right bottom",
      at: "left top"
    },
    checkAll: function(event, ui) {
      thisGraph.plotAccordingToChoices();
    },
    uncheckAll: function(event, ui) {
      thisGraph.plotAccordingToChoices();
    },
    click: function(event, ui) {
      thisGraph.plotAccordingToChoices();
    }
  }).multiselectfilter();
  $(".ui-multiselect-menu").draggable();

  this.$graphControls.find("#" + this.gOptId()).controlgroup();

  var $selectLine = this.$graphControls.find("#" + this.lineId());
  $selectLine.checkboxradio({icon: false}).click(
    {thisGraph: this, stackOpt: "line"},
    setStackOpt);

  var $selectStack = this.$graphControls.find("#" + this.stackId());
  $selectStack.checkboxradio({icon: false}).click(
    {thisGraph: this, stackOpt: "stack"},
    setStackOpt);

  var $clearStackOpt = this.$graphControls.find("#" + this.clearStackOptId());
  $clearStackOpt.button();
  $clearStackOpt.click(
    {thisGraph: this, stackOpt: null},
    function (event) {
      $selectStack.prop("checked", false).checkboxradio("refresh");
      $selectLine.prop("checked", false).checkboxradio("refresh");
      setStackOpt(event);
    });

  this.resetZoomElem = this.$graphControls.find("#" + this.resetZoomId());
  this.resetZoomElem.button();
  this.resetZoomElem.click(
    {thisGraph: this},
    function (event) {
      var thisGraph = event.data.thisGraph;
      delete thisGraph.plotOpt.xaxis.min;
      delete thisGraph.plotOpt.xaxis.max;
      delete thisGraph.plotOpt.yaxis.min;
      delete thisGraph.plotOpt.yaxis.max;
      thisGraph.zooming = false;
      thisGraph.plotAccordingToChoices();
    });

  this.resize();

  this.plotOpt =  {
    points: { show: false },
    crosshair: { mode: "x" },
    xaxis: { mode: "time", timezone: this.tz },
    yaxis: {tickFormatter: suffixFormatter},
    selection: { mode: "xy" },
    legend: {show : false},
    grid: { hoverable: true, autoHighlight: true }
  };
};

FlotGraph.prototype.setStackOpt = function(stackOpt) {
  this.stackOpt = stackOpt;

  if (this.refresh_timer) {
    clearTimeout(this.refresh_timer);
    this.refresh_timer = null;
  }
  this.refresh();
};

FlotGraph.prototype.start = function() {
  $.ajax({
    url: this.dataurl + '&maxrows=' + this.$placeHolder.width(),
    method: 'GET',
    dataType: 'json',
    context: this,
    success: this.onDataReceived
  });
};

FlotGraph.prototype.shutdown = function() {
  if (this.refresh_timer) {
    clearTimeout(this.refresh_timer);
    this.refresh_timer = null;
  }
  this.plot.shutdown();
  this.$placeHolder.unbind("plothover");
  this.$placeHolder.unbind("plotselected");
};

function InspectGraph(graphArgs,
                      refreshInterval,
                      timezone,
                      dialog) {
  FlotGraph.call(this, graphArgs, refreshInterval, timezone);
  this.$dialog = $(dialog);
  this.first_time = true;
}

InspectGraph.prototype = Object.create(FlotGraph.prototype);

InspectGraph.prototype.resize = function() {
  this.$placeHolder.height(this.$dialog.height() -
		           this.$graphControls.height() -
		           this.$spacer.height());
};

InspectGraph.prototype.initialize = function() {
  FlotGraph.prototype.initialize.call(this);
  this.$dialog.bind("dialogresizestop.inspect",
                    {thisGraph: this},
		    function(event) {
                      var thisGraph = event.data.thisGraph;
		      thisGraph.resize();
		      thisGraph.plot.resize();
		      thisGraph.plotAccordingToChoices();
		    });
  this.$dialog.bind("dialogclose.inspect",
                    {thisGraph: this},
		    function(event) {
                      var thisGraph = event.data.thisGraph;
		      $(this).unbind(".inspect");
                      thisGraph.shutdown();
		    });
};

InspectGraph.prototype.onDataReceived = function(series) {
  if (this.first_time) {
    $("#popup-dialog-navigation").html("");

    if ((this.$dialog.dialog("option", "height") == "auto") ||
	(this.$dialog.dialog("option", "width") == "auto")) {
      this.$dialog.dialog("option", "height", 500);
      this.$dialog.dialog("option", "width", 800);
    }
    //this.resize();
    this.first_time = false;
  }

  FlotGraph.prototype.onDataReceived.call(this, series);

  if (this.graph_title)
    this.$dialog.dialog('option', 'title', this.graph_title);
};
