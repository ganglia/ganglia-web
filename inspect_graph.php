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
<script type="text/javascript" src="js/create-flot-graphs.js"></script>

<div id="placeholder" style="width:800px;height:500px;"></div>

<div id="choices" style="margin-top:5px;">Show:</div>
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
  // hard-code color indices to prevent them from shifting as
  // choices are turned on/off
  var i = 0;
  $.each(datasets, function(key, val) {
    val.color = i;
    ++i;
  });
  
  // insert checkboxes 
  var choiceContainer = $("#choices");
  $.each(datasets, function(key, val) {
    choiceContainer.append('<input type="checkbox" name="' + key +
                           '" checked="checked" id="id' + key + '">' +
                           '<label for="id' + key + '">'
                            + val.label + '</label>');
  });
  choiceContainer.find("input").click(plotAccordingToChoices);

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
  
  function plotAccordingToChoices() {
    var data = [];

    choiceContainer.find("input:checked").each(function () {
      var key = $(this).attr("name");
      if (key && datasets[key])
          data.push(datasets[key]);
    });

    $("#placeholder").height($("#popup-dialog").height() - $("#choices").height() - 5);
    $("#placeholder").width($("#popup-dialog").width() - 20);

    $.plot($("#placeholder"), data, {
      crosshair: { mode: "x" },
      xaxis: { mode: "time"},
      lines: { show: true },
      points: { show: false },
      grid: { hoverable: true, autoHighlight: true } 
    });

    if (data.length > 0) {
      $("#placeholder").bind("plothover", function (event, pos, item) {
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

      $("#placeholder").bind("plotclick", function (event, pos, item) {
        if (item) {
          $("#clickdata").text("You clicked point " + 
			       item.dataIndex + " in " + 
			       item.series.label + ".");
	  plot.highlight(item.series, item.datapoint);
        }
      });
    } 
  }

  plotAccordingToChoices();
});
</script>
