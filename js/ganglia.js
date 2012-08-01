$(function(){
  // Ensure that the window has a unique name
  if ((window.name == null) || window.name == "") {
    var d = new Date();
    window.name = d.getTime();
  }

  var range_menu = $("#range_menu");
  if (range_menu[0])
    range_menu.buttonset();
  var custom_range_menu = $("#custom_range_menu");
  if (custom_range_menu[0])
    custom_range_menu.buttonset();
  var sort_menu = $("#sort_menu");
  if (sort_menu[0])
    sort_menu.buttonset();

  var metric_search_input = jQuery('#metric-search input[name="q"]');
  if (metric_search_input[0])
    metric_search_input.liveSearch({url: 'search.php?q=', typeDelay: 500});

  var search_field_q = $("#search-field-q");
  if (search_field_q[0]) {
    search_field_q.keypress(function(e) {
      if (13 == (e.keyCode ? e.keyCode : e.which)) {
        return false;
      }
    });
    search_field_q.keyup(function() {
      $.cookie("ganglia-search-field-q" + window.name, $(this).val());
    });

    var search_value = $.cookie("ganglia-search-field-q" + window.name);
    if (search_value != null && search_value.length > 0)
      search_field_q.val(search_value);
  }

  var datepicker_cs = $( "#datepicker-cs" );
  if (datepicker_cs[0])
    datepicker_cs.datetimepicker({
	  showOn: "button",
	  constrainInput: false,
	  buttonImage: "img/calendar.gif",
	  buttonImageOnly: true
    });

  $( "#datepicker-cs").datetimepicker();
  var datepicker_ce = $( "#datepicker-ce" );
  if (datepicker_ce[0])
    datepicker_ce.datetimepicker({
	  showOn: "button",
	  constrainInput: false,
	  buttonImage: "img/calendar.gif",
	  buttonImageOnly: true
    });

  var create_new_view_dialog = $( "#create-new-view-dialog" );
  if (create_new_view_dialog[0])
    create_new_view_dialog.dialog({
      autoOpen: false,
      height: 200,
      width: 350,
      modal: true,
      close: function() {
        $("#create-new-view-layer").toggle();
        $("#create-new-view-confirmation-layer").html("");
        $.get('views_view.php?views_menu=1',
              function(data) {
	        $("#views_menu").html(data);
              });
      }
    });

  initMetricActionsDialog();	
  });

function selectTab(tab_index) {
  $("#tabs").tabs("select", tab_index);
}

function viewId(view_name) {
  return "v_" + view_name.replace(/[^a-zA-Z0-9_]/g, "_");
}

function selectView(view_name) {
  $.cookie('ganglia-selected-view-' + window.name, view_name);
  $("#vn").val(view_name);
  ganglia_form.submit();
}

function createView() {
  $("#create-new-view-confirmation-layer").html('<img src="img/spinner.gif">');
  $.get('views_view.php', $("#create_view_form").serialize() , function(data) {
    $("#create-new-view-layer").toggle();
    $("#create-new-view-confirmation-layer").html(data);
  });
  return false;
}

function addItemToView() {
  $.get('views_view.php', 
        $("#add_metric_to_view_form").serialize() + "&add_to_view=1", 
        function(data) {$("#metric-actions-dialog-content").html(data);});
  return false;  
}

function initMetricActionsDialog() {
  var metric_actions_dialog = $("#metric-actions-dialog");
  if (metric_actions_dialog[0]) 
    metric_actions_dialog.dialog({
      autoOpen: false,
      height: 250,
      width: 450,
      modal: true
    });
}

function metricActions(host_name,metric_name,type,graphargs) {
    $( "#metric-actions-dialog" ).dialog( "open" );
    $("#metric-actions-dialog-content").html('<img src="img/spinner.gif">');
    $.get('actions.php',
          "action=show_views&host_name=" + host_name + "&metric_name=" + metric_name + "&type=" + type + graphargs, 
          function(data) {$("#metric-actions-dialog-content").html(data);});
    return false;
}

function metricActionsAggregateGraph(args) {
  $("#metric-actions-dialog").dialog("open");
  $("#metric-actions-dialog-content").html('<img src="img/spinner.gif" />');
  $.get('actions.php', 
        "action=show_views" + args + "&aggregate=1", 
        function(data) {$("#metric-actions-dialog-content").html(data);});
    return false;
}


function autoRotationChooser() {
  $("#tabs-autorotation-chooser").html('<img src="img/spinner.gif">');
  $.get('autorotation.php', 
        "", 
        function(data) {$("#tabs-autorotation-chooser").html(data);});
}

function liveDashboardChooser() {
  $("#tabs-livedashboard-chooser").html('<img src="img/spinner.gif">');
  $.get('tasseo.php', 
        "", 
        function(data) {$("#tabs-livedashboard-chooser").html(data);});
}

function updateViewTimeRange() {
  alert("Not implemented yet");
}

function ganglia_submit(clearonly) {
  document.getElementById("datepicker-cs").value = "";
  document.getElementById("datepicker-ce").value = "";
  if (! clearonly)
    document.ganglia_form.submit();
}

/* ----------------------------------------------------------------------------
 Enlarges a graph using Flot
-----------------------------------------------------------------------------*/
function inspectGraph(graphArgs) {
  $("#popup-dialog").dialog('open');
  $("#popup-dialog").bind("dialogbeforeclose", 
                                  function(event, ui) {
                                    $("#enlargeTooltip").remove();});
//  $('#popup-dialog-content').html('<img src="graph.php?' + graphArgs + '" />');
  $.get('inspect_graph.php',
        "flot=1&" + graphArgs, 
        function(data) {$('#popup-dialog-content').html(data);})
}


/* ----------------------------------------------------------------------------
  Draw a trend line on a graph.
-----------------------------------------------------------------------------*/
function drawTrendGraph(url) {
  $("#popup-dialog").dialog('open');
  $("#popup-dialog").bind("dialogbeforeclose", 
                                  function(event, ui) {
                                    $("#enlargeTooltip").remove();});
  $.get('trend_navigation.php',
        url,
        function(data) {$('#popup-dialog-navigation').html(data);})

  $("#popup-dialog-content").html('<img src="' + url + '" />');

}

var SHOW_EVENTS_BASE_ID = "show_events_";
var SHOW_EVENTS_BASE_ID_LEN = SHOW_EVENTS_BASE_ID.length;
var GRAPH_BASE_ID = "graph_img_";

function initShowEvent() {
  $("[id^=" + SHOW_EVENTS_BASE_ID + "]").each(function() {
    $(this).button();
    $(this).attr("checked", 'checked');
    $(this).button('refresh');
  });

  if ($("#show_all_events").length > 0) {
    $("#show_all_events").button();
    $("#show_all_events").attr("checked", 'checked');
    $("#show_all_events").button('refresh');
  }
}

var TIME_SHIFT_BASE_ID = "time_shift_";
var TIME_SHIFT_BASE_ID_LEN = TIME_SHIFT_BASE_ID.length;

function initTimeShift() {
  $("[id^=" + TIME_SHIFT_BASE_ID + "]").each(function() {
    $(this).button();
    $(this).removeAttr("checked");
    $(this).button('refresh');
  });
    
  if ($("#timeshift_overlay").length > 0) {
    $("#timeshift_overlay").button();
    $("#timeshift_overlay").removeAttr("checked");
    $("#timeshift_overlay").button('refresh');
  }
}

function showTimeshiftOverlay(show) {
  $("[id^=" + TIME_SHIFT_BASE_ID + "]").each(function() {
      if (show)
        $(this).attr("checked", 'checked');
      else
        $(this).removeAttr("checked");
      $(this).button('refresh');
      var graphId = GRAPH_BASE_ID + 
	$(this).attr('id').slice(TIME_SHIFT_BASE_ID_LEN);
      showTimeShift(graphId, show);
    });
}

function showAllEvents(show) {
  $("[id^=" + SHOW_EVENTS_BASE_ID + "]").each(function() {
      if (show)
        $(this).attr("checked", 'checked');
      else
        $(this).removeAttr("checked");
      $(this).button('refresh');
      var graphId = GRAPH_BASE_ID + 
	$(this).attr('id').slice(SHOW_EVENTS_BASE_ID_LEN);
      showEvents(graphId, show);
    });
}

function showEvents(graphId, show) {
    var graph = $("#" + graphId);
    var src = graph.attr("src");
    if ((src.indexOf("graph.php") != 0) &&
        (src.indexOf("./graph.php") != 0))
      return;
    var paramStr = "&event=";
    paramStr += show ? "show" : "hide"
    var d = new Date();
    paramStr += "&_=" + d.getTime();
    src = jQuery.param.querystring(src, paramStr);
    graph.attr("src", src);
  }

function showTimeShift(graphId, show) {
    var graph = $("#" + graphId);
    var src = graph.attr("src");
    if ((src.indexOf("graph.php") != 0) &&
        (src.indexOf("./graph.php") != 0))
      return;
    var paramStr = show ? "&ts=1" : "&ts=0";
    var d = new Date();
    paramStr += "&_=" + d.getTime();
    src = jQuery.param.querystring(src, paramStr);
    graph.attr("src", src);
  }
