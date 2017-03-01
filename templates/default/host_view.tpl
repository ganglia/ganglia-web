<!-- Begin host_view.tpl -->
<script type="text/javascript">
var SEPARATOR = "_|_";
var ALL_GROUPS = "ALLGROUPS";
var NO_GROUPS = "NOGROUPS";
// Map metric group id to name
var g_mgMap = new Object();

function clearStoredMetricGroups() {
  var stored_groups = $('input[name="metric_group"]');
  stored_groups.val(NO_GROUPS);
}

function selectAllMetricGroups() {
  var stored_groups = $('input[name="metric_group"]');
  stored_groups.val(ALL_GROUPS);
}

function addMetricGroup(mgName) {
  var stored_groups = $('input[name="metric_group"]');

  var open_groups = stored_groups.val();
  if (open_groups == ALL_GROUPS)
    return; // no exceptions

  var groups = open_groups.split(SEPARATOR);
  switch (groups[0]) {
    case ALL_GROUPS:
      // Remove from except list
      for (var i = 1; i < groups.length; i++) {
        if (groups[i] == mgName) {
          groups.splice(i, 1);
          break;
        }
      }
      open_groups = groups.join(SEPARATOR);
    break;
    case NO_GROUPS:
      // Add to list if not already there
      var inList = false;
      for (var i = 1; i < groups.length; i++) {
         if (groups[i] == mgName) {
           inList = true;
           break;
         }
      }
      if (!inList) {
        open_groups += SEPARATOR;
        open_groups += mgName;
      }
    break;
    default:
      alert("Unrecognized group option - " + groups[0]);
  }
  stored_groups.val(open_groups);
}

function removeMetricGroup(mgName) {
  var stored_groups = $('input[name="metric_group"]');

  var open_groups = stored_groups.val();
  if (open_groups == NO_GROUPS)
    return; // no exceptions

  var groups = open_groups.split(SEPARATOR);
  switch (groups[0]) {
    case ALL_GROUPS:
      var inList = false;
      for (var i = 1; i < groups.length; i++) {
        if (groups[i] == mgName) {
          inList = true;
          break;
        }
      }
      if (!inList) {
        open_groups += SEPARATOR;
        open_groups += mgName;
      }
    break;
    case NO_GROUPS:
      for (var i = 1; i < groups.length; i++) {
        if (groups[i] == mgName) {
          groups.splice(i, 1);
          break;
        }
      }
      open_groups = groups.join(SEPARATOR);
    break;
    default:
      alert("Unrecognized group option - " + groups[0]);
  }
  stored_groups.val(open_groups);
}

function toggleMetricGroup(mgId, mgDiv) {
  var mgName = g_mgMap[mgId];
  if (mgDiv.is(":visible")) {
    // metric group is being closed
    removeMetricGroup(mgName);
    mgDiv.html("");
    mgDiv.hide();
  } else {
    addMetricGroup(mgName);
    var url = 'metric_group_view.php?{$baseGraphArgs}&metric_group=' + mgName;
    url += "&event=";
    url += $("#show_all_events").prop("checked") ? "show" : "hide";
    url += "&ts=";
    url += $("#timeshift_overlay").prop("checked") ? "1" : "0";

    $.get(url,
          function(data) {
            mgDiv.html(data);
	    mgInitEventBtns(mgDiv);
	    mgInitTimeshiftBtns(mgDiv);
	    mgInitCustomTimeRangeDragSelect(mgDiv);
            mgDiv.show();
          });
  }
}

function jumpToMetricGroup(mgId) {
  //alert("jumping to " + mgId);
  $.scrollTo($('#' + mgId));
}

function refreshHostView() {
  $.get('host_overview.php?h={$hostname}&c={$cluster}', function(data) {
    $('#host_overview_div').html(data);
  });

  $("#optional_graphs img").each(function (index) {
    var src = $(this).attr("src");
    if ((src.indexOf("graph.php") == 0 ||
         src.indexOf("./graph.php") == 0) &&
        $(this).visible(true, true)) {
      var d = new Date();
      $(this).attr("src", jQuery.param.querystring(src, "&_=" + d.getTime()));
    }
  });

  $("#metrics img").each(function (index) {
    var src = $(this).attr("src");
    if ((src.indexOf("graph.php") == 0  ||
        src.indexOf("./graph.php") == 0) &&
        $(this).visible(true, true)) {
      var d = new Date();
      $(this).attr("src", jQuery.param.querystring(src, "&_=" + d.getTime()));
    }
  });
}

function mgInitCustomTimeRangeDragSelect(mgDiv) {
  initCustomTimeRangeDragSelect(mgDiv);
}

function mgInitEventBtns(mgDiv) {
  var checked = $("#show_all_events").prop("checked");
  mgDiv.find("[id^=" + SHOW_EVENTS_BASE_ID + "]").each(function() {
    $(this).checkboxradio({ icon: false });
    $(this).prop('checked', checked).checkboxradio('refresh');
  });
}

function mgInitTimeshiftBtns(mgDiv) {
  var checked = $("#timeshift_overlay").prop("checked");
  mgDiv.find("[id^=" + TIME_SHIFT_BASE_ID + "]").each(function() {
    $(this).checkboxradio({ icon: false });
    $(this).prop('checked', checked).checkboxradio('refresh');
  });
}

$(function() {
  var stored_groups = $('input[name="metric_group"]');
  stored_groups.val("{$g_open_metric_groups}");

  $("#edit_optional_graphs").dialog({ autoOpen: false, minWidth: 550,
    beforeClose: function(event, ui) { location.reload(true); } });

  $("#close_edit_optional_graphs_link").button();

  $("#edit_optional_graphs_button").button();
  $("#edit_optional_graphs_button").click(function(event) {
    $("#edit_optional_graphs").dialog('open');
    $('#edit_optional_graphs_content').html('<img src="img/spinner.gif" />');
    $.get('edit_optional_graphs.php', "hostname={$hostname}", function(data) {
      $('#edit_optional_graphs_content').html(data);
    })
    return false;
  });

  $("#save_optional_graphs_button").button();
  $("#save_optional_graphs_button").click(function(event) {
    $.get('edit_optional_graphs.php', $("#edit_optional_reports_form").serialize(), function(data) {
      $('#edit_optional_graphs_content').html(data);
      $("#save_optional_graphs_button").hide();
    });
    return false;
  });

  $("#expand_all_metric_groups").button();
  $("#expand_all_metric_groups").click(function(event) {
    selectAllMetricGroups();
    document.ganglia_form.submit();
    return false;
  });

  $("#collapse_all_metric_groups").button();
  $("#collapse_all_metric_groups").click(function(event) {
    clearStoredMetricGroups();
    document.ganglia_form.submit();
    return false;
  });

  $("#host_overview").button();
  $('#host_overview').click(function() {
    var options = { to: { width: 200, height: 60 } };
    $("#host_overview_div").toggle("blind", options, 500);
    return false;
  });

  $('.metric-group').each(function() {
    $(this).button();
    $(this).click(function() {
      var id = $(this).attr('id');
      toggleMetricGroup(id, $("#"+id+"_div"));
      return false;
    });
  });
});
</script>

<style type="text/css">
  .toggler { width: 500px; height: 200px; }
  #effect { width: 240px; height: 135px; padding: 0.4em; position: relative; }
  #effect h3 { margin: 0; padding: 0.4em; text-align: center; }
</style>

<div>
<button id="host_overview" class="button">Host Overview</button>
</div>

<div style="display: none;" id="host_overview_div">
{include('host_overview.tpl')}
</div>

<div id="edit_optional_graphs">
  <div style="text-align:center">
    <button id="save_optional_graphs_button">Save</button>
  </div>
  <div id="edit_optional_graphs_content" style="padding: .4em 1em .4em 10px;">Empty</div>
</div>

<div id="optional_graphs" style="padding-top:5px;">
{$optional_reports}
<div style='clear: left'></div>
{if $may_edit_cluster}
<div style="text-align:center"><button id="edit_optional_graphs_button">Edit Optional Graphs</button></div>
{/if}
</div>

<div id="sort_column_dropdowns" style="padding-top:5px;">
<table border="0" width="100%">
<tr>
  <td style="text-align:center;background-color:rgb(238,238,238);">
  {$host} <strong>graphs</strong> ({$host_metrics_count})
  last <strong>{$range}</strong>
  sorted <strong>{$sort}</strong>
{if isset($columns_dropdown)}
  <font>
    Columns&nbsp;&nbsp;{$metric_cols_menu}
    Size&nbsp;&nbsp;{$size_menu}
  </font>
{/if}
  </td>
</tr>
</table>

</div>

<div id=metrics style="padding-top:5px">
<center>
<div style="padding-bottom:5px;">
<button id="expand_all_metric_groups">Expand All Metric Groups</button>
<button id="collapse_all_metric_groups">Collapse All Metric Groups</button>
<input title="Time Shift Overlay - overlays previous period on all graphs" type="checkbox" id="timeshift_overlay" onclick="showTimeshiftOverlay(this.checked)"/><label for="timeshift_overlay">Timeshift Overlay</label>
<select id="jump_to_metric_group" class="ui-corner-all" onchange="jumpToMetricGroup(this.options[this.selectedIndex].value);">
<option disabled="disabled" selected="selected">Jump To Metric Group...</option>
{foreach $g_metrics_group_data group g_metrics}
{$mgId = "mg_"; $mgId .= regex_replace($group, '/[^a-zA-Z0-9_]/', '_')}
<option value="{$mgId}">{$group}</option>
{/foreach}
</select>
</div>
<table>
<tr>
 <td>

{foreach $g_metrics_group_data group g_metrics}
{$mgId = "mg_"; $mgId .= regex_replace($group, '/[^a-zA-Z0-9_]/', '_')}
<table border="0" width="100%">
<tr>
  <td class="metric">
  <button id="{$mgId}" class="metric-group" title="Toggle {$group} metrics group on/off">{$group} metrics ({$g_metrics.group_metric_count})</button>
<script type="text/javascript">$(function() {
g_mgMap["{$mgId}"] = "{$group}";
})</script>
  </td>
</tr>
</table>

{if $g_metrics.visible}
<div id="{$mgId}_div">
{else}
<div id="{$mgId}_div" class="ui-helper-hidden">
{/if}
{if $g_metrics.visible}
{include('metric_group_view.tpl')}
{/if}
</div>
{/foreach}
 </td>
</tr>
</table>
</center>
</div>
<input type="hidden" name="metric_group" value="">
<!-- End host_view.tpl -->
