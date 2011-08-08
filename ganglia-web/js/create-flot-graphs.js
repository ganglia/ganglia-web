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


  $(".flotgraph-enlarge").each(function () {
    var placeholder = $(this);
    var url = "graph.php?" + placeholder.attr('id').replace('placeholder_', '') + "&flot=1";

    function onDataReceived(series) {
   //   flot_options.legend.container = $('#' + placeholder.attr('id') + '_legend');
      
      $.plot(placeholder, series, {
          crosshair: { mode: "x" },
          grid: { hoverable: true, autoHighlight: true }   }
      );
    }

    var legends = $('#' + placeholder.attr('id') + " .legendLabel");
    legends.each(function () {
        // fix the widths so they don't jump around
        $(this).css('width', $(this).width());
    });
 
    var updateLegendTimeout = null;
    var latestPosition = null;
    
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
 
            legends.eq(i).text(series.label.replace(/=.*/, "= " + y.toFixed(2)));
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

