<style>
.img_view {
  float: left;
  margin: 0 0 10px 10px;
}
</style>
<style>
.flotgraph-enlarge {
  height: 500px;
  width:  800px;
}
</style>
<script language="javascript" type="text/javascript" src="js/jquery.flot.min.js"></script>
<script language="javascript" type="text/javascript" src="js/jquery.flot.crosshair.min.js"></script>
<script language="javascript" type="text/javascript" src="js/jquery.flot.stack.js"></script>
<script language="javascript" type="text/javascript" src="js/jquery.multiselect.js"></script>
<script type="text/javascript" src="js/create-flot-graphs.js"></script>

<div id="placeholder" style="width:800px;height:500px;"></div>

<div id="legendcontainer" style="margin-top:5px;">
   <div id="graphlegend"></div>
</div>
<?php

$base_url = str_replace("inspect_graph.php", "", $_SERVER["SCRIPT_NAME"]);

$obj = json_decode(file_get_contents("http://" . $_SERVER['HTTP_HOST'] . $base_url . "graph.php?" . $_SERVER['QUERY_STRING']), TRUE);
$arr = array();
foreach ( $obj as $index => $series ) {
  $label = str_replace(" ", "_", $series['label']);
  $arr[$label] = $series;
}

?>

<script>
$(function () {
    $("#popup-dialog").bind("dialogresizestop.inspect", 
			    function() {
			      plotAccordingToChoices();
			    });
    $("#popup-dialog").bind("dialogclose.inspect", 
			    function(event) {
			      $(this).unbind(".inspect");
			    });

  var datasets = 
    <?php print json_encode($arr); ?>
  ;

  var stacked = false;
  var legendContainer = $("#legendcontainer");
  var series_select = '<select id="select_series" name="select_series" multiple="multiple">';

  // hard-code color indices to prevent them from shifting as
  // choices are turned on/off
  var i = 0;
  $.each(datasets, function(key, val) {
    if (typeof val.color == 'undefined')
      val.color = i;
    // Explicity delete the stack attribute if it exists because stacking
    // is controlled locally. The incoming datasets will contain a 
    // stack attribute if they were generated from a stacked graph.
    if ("stack" in val) {
      delete val.stack;
      stacked = true;
    }
    ++i;

    series_select += '<option value="' + key + '" selected="selected">' + val.label + '</option>';
  });
  
  series_select += '</select>';
  legendContainer.append(series_select);
  var select_series = $("#select_series");
  select_series.multiselect({
    height: "auto",
    position : {
      my: "left bottom",
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
    });

  var html = '<span id="gopt" style="margin-left:10px;"><input type="radio" id="line" name="gopt"';
  if (!stacked)
    html += 'checked="checked"';
  html += '/><label for="line">Line</label><input type="radio" id="stack" name="gopt"';
  if (stacked)
    html += 'checked="checked"';
  html += '/><label for="stack">Stack</label></span>';
  legendContainer.append(html);

  $("#gopt").buttonset();
  $("#line").button().click(plotAccordingToChoices);
  $("#stack").button().click(plotAccordingToChoices);

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
  
  function suffixFormatter(val, axis) {
    var tickd = axis.tickDecimals;
    if (tickd <= 0) {
      tickd = 1;
    }
        
    if (val >= 1000000000) {
      return (val / 1000000000).toFixed(tickd) + " G";
    }
    if (val >= 1000000) {
      return (val / 1000000).toFixed(tickd) + " M";
    }
    if (val >= 1000) {
      return (val / 1000).toFixed(tickd) + " k";
    }
        
    return (val/1).toFixed(axis.tickDecimals);
  }

  function plotAccordingToChoices() {
    var selected_series = $("#select_series").multiselect("getChecked").map(function(){return this.value}).get();
    var data = [];
    for (var i = 0; i < selected_series.length; i++)
      data.push(datasets[selected_series[i]]);

    var placeHolder = $("#placeholder");
    var placeHolderHeight = $("#popup-dialog").height() - 
      $("#legendcontainer").height() - 5;
    if ($("#graphlegend").height() == 0)
      placeHolderHeight -= 20; // estimate the height of the legend
    placeHolder.height(placeHolderHeight); 
    placeHolder.width($("#popup-dialog").width() - 20);

    var graphLegend = $("#graphlegend");
    var opt =  {lines: { show: true },
		points: { show: false },
		crosshair: { mode: "x" },
		xaxis: { mode: "time" },
                yaxis: {tickFormatter: suffixFormatter},
		legend: {
                  container: graphLegend,
                  noColumns: 7
                },
		grid: { hoverable: true, autoHighlight: true }};
    if ($("#stack").attr('checked')) {
      opt['series'] = {stack: 1};
      opt['bars'] = {show: true};
    }

    graphLegend.html("");

    $.plot(placeHolder, data, opt);

    placeHolder.unbind("plothover");
    if (data.length > 0) {
      placeHolder.bind("plothover", function (event, pos, item) {
        $("#x").text(utcTimeStr(pos.x));
        $("#y").text(pos.y.toFixed(2));

        if (item) {
            if (previousPoint != item.dataIndex) {
                previousPoint = item.dataIndex;
                
                $("#tooltip").remove();
                var y = item.datapoint[1].toFixed(2);
                showTooltip(item.pageX, item.pageY,
                            item.series.label + " at " + 
			    utcTimeStr(item.datapoint[0]) + 
			    " = " + y);
            }
        } else {
            $("#tooltip").remove();
            previousPoint = null;            
        }
      });
    } 
  }

  plotAccordingToChoices();
});
</script>
