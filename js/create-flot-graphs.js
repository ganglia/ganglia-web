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

  // display heading on top of flot legend containers
  $('.flotlegend').each(function() {
    var placeholder = $(this);
    placeholder.before("<span class=\"flotlegendtoplabel\"><label>Legend</label></span>"); 
  }); 
  
});

