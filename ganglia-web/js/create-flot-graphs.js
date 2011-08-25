// Requires the variables 'default_time', 'metric', and 'base_url' already be set.

$(document).ready(function () {
  var flot_options = {
    xaxis: { mode: "time"},
    lines: { show: true },
    points: { show: false },
    grid: { hoverable: true },
    legend: {
      show: true,
      position: "sw",
      backgroundColor: "#ccccff",
      backgroundOpacity: 0.5,
      labelBoxBorderColor: "#000000",
      noColumns: 3,
      container: "", // populate later programatically
    },
  };

  // Iterate over every graph available and plot it
  $(".flotgraph").each(function () {
    var placeholder = $(this);
    var time = placeholder.attr('id').replace('placeholder_', '');
    var url = base_url.replace(default_time, time);

    function onDataReceived(series) {
      series[0].label += " last " + time;
      flot_options.legend.container = '#' + placeholder.attr('id') + '_legend';
      $.plot(placeholder, series, flot_options);
    }

    $.ajax({
      url: url,
      method: 'GET',
      dataType: 'json',
      success: onDataReceived
    });

  });

  // Used in host view
  $(".flotgraph2").each(function () {
    var placeholder = $(this);
    var url = "graph.php?" + placeholder.attr('id').replace('placeholder_', '') + "&flot=1";

    function onDataReceived(series) {
      series[0].label += " last ";
      flot_options.legend.container = '#' + placeholder.attr('id') + '_legend';
      $.plot(placeholder, series, flot_options);
    }

    $.ajax({
      url: url,
      method: 'GET',
      dataType: 'json',
      success: onDataReceived
    });

  });

  function showEnlargeTooltip(x, y, contents) {
      $('<div id="enlargeTooltip">' + contents + '</div>').css( {
          position: 'absolute',
          display: 'none',
          top: y + 5,
          left: x + 5,
          border: '1px solid #fdd',
          padding: '2px',
          'background-color': '#fee',
          opacity: 0.80,
          'z-index': 1004
      }).appendTo("body").fadeIn(200);
  }


  $(".flotgraph-enlarge").each(function () {
    var placeholder = $(this);
    var plot = null;
    var url = "graph.php?" + placeholder.attr('id').replace('placeholder_', '') + "&flot=1";

    function onDataReceived(series) {
      // flot_options.legend.container = $('#' + placeholder.attr('id') + '_legend');
      
      plot = $.plot(placeholder, series, {
          crosshair: { mode: "x" },
          xaxis: { mode: "time"},
          lines: { show: true },
          points: { show: false },
          grid: { hoverable: true, autoHighlight: true }   }
      );
 
      var previousPoint = null;
      placeholder.bind("plothover", function (event, pos, item) {
          $("#x").text(pos.x.toFixed(2));
          $("#y").text(pos.y.toFixed(2));

          if (item) {
              if (previousPoint != item.dataIndex) {
                  previousPoint = item.dataIndex;
                    
                  $("#enlargeTooltip").remove();
                  var x = item.datapoint[0].toFixed(2),
                      y = item.datapoint[1].toFixed(2);

                  showEnlargeTooltip(item.pageX, item.pageY,
                      item.series.label ? item.series.label.replace(/=.*/, "= " + y) : y);
              }
          }
      });
    }

    var legends = $("#" + placeholder.attr("id") + " .legend");
    legends.each(function () {
        // fix the widths so they don't jump around
        $(this).css('width', $(this).width());
    });
 
    var updateLegendTimeout = null;
    var latestPosition = null;
    var hasUpdatedLegend = false;
    
    function updateLegend() {
        updateLegendTimeout = null;
        
        var pos = latestPosition;
        
        var axes = plot.getAxes();
        if (pos.x < axes.xaxis.min || pos.x > axes.xaxis.max ||
            pos.y < axes.yaxis.min || pos.y > axes.yaxis.max)
            return;
 
        var i, j, dataset = plot.getData();
        for (i = 0; i < dataset.length; ++i) {
            var series = dataset[i];
 
            // find the nearest points, x-wise
            for (j = 0; j < series.data.length; ++j)
                if (series.data[j][0] > pos.x)
                    break;
            
            // now interpolate
            var y, p1 = series.data[j - 1], p2 = series.data[j];
            if (p1 == null)
                y = p2[1];
            else if (p2 == null)
                y = p1[1];
            else
                y = p1[1] + (p2[1] - p1[1]) * (pos.x - p1[0]) / (p2[0] - p1[0]);

            // console.debug("legends " + i + " = " + legends.eq(i).text());
            if (!hasUpdatedLegend) {
                series.label = series.label + " = ";
                legends.eq(i).text(series.label);
                hasUpdatedLegend = true;
            }
            legends.eq(i).text(series.label.replace(/=.*/, "= " + y));
        }
    }
    
    placeholder.bind("plothover",  function (event, pos, item) {
        latestPosition = pos;
        if (!updateLegendTimeout)
            updateLegendTimeout = setTimeout(updateLegend, 50);
    });

    $.ajax({
      url: url,
      method: 'GET',
      dataType: 'json',
      success: onDataReceived
    });

  });

  // display heading on top of flot legend containers
  $('.flotlegend').each(function() {
    var placeholder = $(this);
    placeholder.before("<span class=\"flotlegendtoplabel\"><label>Legend</label></span>"); 
  }); 
  
});

