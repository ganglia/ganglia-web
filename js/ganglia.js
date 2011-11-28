$(function(){
  // Ensure that the window has a unique name
  if ((window.name == null) || window.name == "") {
    var d = new Date();
    window.name = d.getTime();
  }

  g_overlay_events = ($("#overlay_events").val() == "true");
  g_tabName = ["m", "s", "v", "agg", "ch"];
  if (g_overlay_events)
    g_tabName.push("ev");
  g_tabName.push("rot");
  g_tabName.push("mob");

  // Follow tab's URL instead of loading its content via ajax
  var tabs = $("#tabs");
  if (tabs[0]) {
    tabs.tabs();
    // Restore previously selected tab
    var selected_tab = $.cookie("ganglia-selected-tab-" + window.name);
    if ((selected_tab != null) && (selected_tab.length > 0)) {
      try {
        var tab_index = parseInt(selected_tab, 10);
        if (!isNaN(tab_index) && (tab_index >= 0)) {
          //alert("ganglia-selected-tab: " + tab_index);
          tabs.tabs("select", tab_index);
          if (g_tabName[tab_index] == "rot")
            autoRotationChooser();
        }
      } catch (err) {
        try {
          alert("Error(ganglia.js): Unable to select tab: " + 
                tab_index + ". " + err.getDescription());
        } catch (err) {
          // If we can't even show the error, fail silently.
        }
      }
    }

    tabs.bind("tabsselect", function(event, ui) {
      $("#selected_tab").val(g_tabName[ui.index]);
      $.cookie("ganglia-selected-tab-" + window.name, ui.index);
      if (ui.index == 0 ||
          ui.index == 2 ||
          ui.index == 4)
        ganglia_form.submit();
    });
  }

  var range_menu = $("#range_menu");
  if (range_menu[0])
    range_menu.buttonset();
  var sort_menu = $("#sort_menu");
  if (sort_menu[0])
    sort_menu.buttonset();

  var metric_search_input = jQuery('#metric-search input[name="q"]');
  if (metric_search_input[0])
    metric_search_input.liveSearch({url: 'search.php?q=', typeDelay: 500});

  var search_field_q = $("#search-field-q");
  if (search_field_q[0]) {
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
                var vn = selectedView();
                if (vn != null)
                  highlightSelectedView(vn);
              });
      }
    });

  var metric_actions_dialog = $("#metric-actions-dialog");
  if (metric_actions_dialog[0]) 
    metric_actions_dialog.dialog({
      autoOpen: false,
      height: 250,
      width: 450,
      modal: true
    });
});

function selectTab(tab_index) {
  $("#tabs").tabs("select", tab_index);
}

function viewId(view_name) {
  return "v_" + view_name.replace(/[^a-zA-Z0-9_]/g, "_");
}

function highlightSelectedView(view_name) {
  if (view_name != null && view_name != '') {
    $("#navlist a").css('background-color', '#FFFFFF');	
    $("#" + viewId(view_name)).css('background-color', 'rgb(238,238,238)');
  }
}

function selectView(view_name) {
  highlightSelectedView(view_name);
  $.cookie('ganglia-selected-view-' + window.name, view_name);
  $("#vn").val(view_name);
  ganglia_form.submit();
}

function selectedView() {
  var vn = $.cookie('ganglia-selected-view-' + window.name);
  return (vn == null || vn == '') ? null : vn;
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
function metricActions(host_name,metric_name,type,graphargs) {
    $( "#metric-actions-dialog" ).dialog( "open" );
    $("#metric-actions-dialog-content").html('<img src="img/spinner.gif">');
    $.get('actions.php',
          "action=show_views&host_name=" + host_name + "&metric_name=" + metric_name + "&type=" + type + graphargs, 
          function(data) {$("#metric-actions-dialog-content").html(data);});
    return false;
}

function createAggregateGraph() {
  if ($('#hreg').val() == "" || $('#metric_chooser').val() == "") {
      alert("Host regular expression and metric name can't be blank");
      return false;
  }
  $("#aggregate_graph_display").html('<img src="img/spinner.gif">');
  $.get('graph_all_periods.php', 
        $("#aggregate_graph_form").serialize() + "&aggregate=1&embed=1", 
        function(data) {$("#aggregate_graph_display").html(data);});
  return false;
}

function metricActionsAggregateGraph(args) {
  $("#metric-actions-dialog").dialog("open");
  $("#metric-actions-dialog-content").html('<img src="img/spinner.gif">');
  $.get('actions.php', 
        "action=show_views" + args, 
        function(data) {$("#metric-actions-dialog-content").html(data);});
    return false;
}


function autoRotationChooser() {
  $("#tabs-autorotation-chooser").html('<img src="img/spinner.gif">');
  $.get('autorotation.php', 
        "", 
        function(data) {$("#tabs-autorotation-chooser").html(data);});
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
function enlargeGraph(graphArgs) {
  $("#enlarge-graph-dialog").dialog('open');
  $("#enlarge-graph-dialog").bind("dialogbeforeclose", 
                                  function(event, ui) {
                                    $("#enlargeTooltip").remove();});
//  $('#enlarge-graph-dialog-content').html('<img src="graph.php?' + graphArgs + '" />');
  $.get('inspect_graph.php',
        "flot=1&" + graphArgs, 
        function(data) {$('#enlarge-graph-dialog-content').html(data);})
}
