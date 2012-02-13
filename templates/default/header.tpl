<!-- Begin header.tpl -->
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title>Ganglia:: {$page_title}</title>
<meta http-equiv="Content-type" content="text/html; charset=utf-8">
<link type="text/css" href="css/smoothness/jquery-ui-1.8.14.custom.min.css" rel="stylesheet" />
<link type="text/css" href="css/jquery.liveSearch.css" rel="stylesheet" />
<link type="text/css" href="./styles.css" rel="stylesheet" />
<script type="text/javascript" src="js/jquery-1.7.1.min.js"></script>
<script type="text/javascript" src="js/jquery-ui-1.8.14.custom.min.js"></script>
<script type="text/javascript" src="js/jquery.liveSearch.js"></script>
<script type="text/javascript" src="js/ganglia.js"></script>
<script type="text/javascript" src="js/jquery.gangZoom.js"></script>
<script type="text/javascript" src="js/jquery.cookie.js"></script>
<script type="text/javascript" src="js/jquery-ui-timepicker-addon.js"></script>
<script type="text/javascript" src="js/jquery.ba-bbq.min.js"></script>
<script type="text/javascript" src="js/combobox.js"></script>
<script type="text/javascript">
    var server_utc_offset={$server_utc_offset};

    var g_refresh_timer = setTimeout("refresh()", {$refresh} * 1000);

    function refreshHeader() {
      $.get('header.php?date_only=1', function(data) {
        var title = $("#page_title").text();
        var l = title.lastIndexOf(" for ");
        title = title.substring(0, l);
        title += " for " + data;
        $("#page_title").text(title);
        });
    }

    function refresh() {
      var selected_tab = $("#selected_tab").val();
      if (selected_tab == "agg") {
        refreshAggregateGraph();
        g_refresh_timer = setTimeout("refresh()", {$refresh} * 1000);
      } else if (selected_tab == "v") {
        refreshHeader();
        if ($.isFunction(window.refreshView)) {
          refreshView();
          g_refresh_timer = setTimeout("refresh()", {$refresh} * 1000);
        } else if ($.isFunction(window.refreshDecomposeGraph)) {
          refreshDecomposeGraph();
          g_refresh_timer = setTimeout("refresh()", {$refresh} * 1000);
        } else
          ganglia_form.submit();
      } else if (selected_tab == "ev") {
        refreshOverlayEvent();
        g_refresh_timer = setTimeout("refresh()", {$refresh} * 1000);
      } else if (selected_tab == "m") {
        if ($.isFunction(window.refreshClusterView)) {
          refreshHeader();
          refreshClusterView();
          g_refresh_timer = setTimeout("refresh()", {$refresh} * 1000);
        } else if ($.isFunction(window.refreshHostView)) {
          refreshHeader();
          refreshHostView();
          g_refresh_timer = setTimeout("refresh()", {$refresh} * 1000);
        } else
          ganglia_form.submit();
      } else
        ganglia_form.submit();
    }

    $(function() {
      $("#metrics-picker").combobox();

      {$is_metrics_picker_disabled}

      $(".submit_button").button();
      $(".header_btn").button();
    });

  $(function () {

    done = function done(startTime, endTime) {
            setStartAndEnd(startTime, endTime);
            document.forms['ganglia_form'].submit();
    }

    cancel = function (startTime, endTime) {
            setStartAndEnd(startTime, endTime);
    }

    defaults = {
        startTime: {$start_timestamp},
        endTime: {$end_timestamp},
        done: done,
        cancel: cancel
    }

    $(".host_small_zoomable").gangZoom($.extend({
        paddingLeft: 67,
        paddingRight: 30,
        paddingTop: 38,
        paddingBottom: 25
    }, defaults));

    $(".host_medium_zoomable").gangZoom($.extend({
        paddingLeft: 67,
        paddingRight: 30,
        paddingTop: 38,
        paddingBottom: 40
    }, defaults));

    $(".host_default_zoomable").gangZoom($.extend({
        paddingLeft: 66,
        paddingRight: 30,
        paddingTop: 37,
        paddingBottom: 50
    }, defaults));

    $(".host_large_zoomable").gangZoom($.extend({
        paddingLeft: 66,
        paddingRight: 29,
        paddingTop: 37,
        paddingBottom: 56
    }, defaults));

    $(".cluster_zoomable").gangZoom($.extend({
        paddingLeft: 67,
        paddingRight: 30,
        paddingTop: 37,
        paddingBottom: 50
    }, defaults));

    function rrdDateTimeString(date) {
      return (date.getMonth() + 1) + "/" + date.getDate() + "/" + date.getFullYear() + " " + date.getHours() + ":" + date.getMinutes();
    }

    function setStartAndEnd(startTime, endTime) {
        // we're getting local start/end times.

        // getTimezoneOffset returns negative values east of UTC,
        // which is the opposite of PHP. we want negative values to the west.
        var local_offset = new Date().getTimezoneOffset() * 60 * -1;
        var delta = local_offset - server_utc_offset;
        var date = new Date((Math.floor(startTime) - delta) * 1000);
        $("#datepicker-cs").val(rrdDateTimeString(date));
        date = new Date((Math.floor(endTime) - delta) * 1000);
        $("#datepicker-ce").val(rrdDateTimeString(date));
    }

    initShowEvent();
  });


</script>
{$custom_time_head}
</head>
<body style="background-color: #ffffff;" onunload="g_refresh_timer=null">
{if isset($user_header)}
{include(file="user_header.tpl")}
{/if}

{if $auth_system_enabled}
<div style="float:right">
  {if $username}
    Currently logged in as: {$username} | <a href="logout.php">Logout</a>
  {else}
    You are not currently logged in. | <a href="login.php">Login</a>
  {/if}
</div>
<br style="clear:both"/>
{/if}

<div id="tabs">
<ul>
  <li><a href="#tabs-main">Main</a></li>
  <li><a href="#tabs-search">Search</a></li>
  <li><a href="#tabs-main">Views</a></li>
  <li><a href="aggregate_graphs.php">Aggregate Graphs</a></li>
  <li><a href="#tabs-main">Compare Hosts</a></li>
{if $overlay_events}
  <li><a href="events.php">Events</a></li>
{/if}
  <li><a href="#tabs-autorotation" onclick="autoRotationChooser();">Automatic Rotation</a></li>
  <li><a href="#tabs-mobile" onclick="window.location.href='mobile.php';">Mobile</a></li>
</ul>

<div id="tabs-main">
<form action="{$page}" method="GET" name="ganglia_form">
  <table id="table_top_chooser" width="100%" cellpadding="4" cellspacing="0" border="0">
  <tr bgcolor="#DDDDDD">
     <td bgcolor="#DDDDDD">
     <big><b id="page_title">{$page_title} for {$date}</b></big>
     </td>
     <td bgcolor="#DDDDDD" align="right">
     <input class="submit_button" type="submit" value="Get Fresh Data" />
     </td>
  </tr>
  <tr>
     <td>
    <div id="range_menu">{$range_menu}{$custom_time}</div>
     </td>
     <td align="right" class="nobr">
      {$additional_buttons}&nbsp;&nbsp;{$alt_view}
     </td>
  </tr>
  <tr>
  <td colspan="2">
  <div id="sort_menu">
   <b>Metric</b>&nbsp;&nbsp; <select name="m" id="metrics-picker">{$available_metrics}</select>&nbsp;&nbsp;
     {$sort_menu}
  </div>
  </td>
  </tr>
{if $node_menu != ""}
  <tr><td colspan="2">{$node_menu}&nbsp;&nbsp;{$additional_filter_options}</td>
</tr>
{/if}
  </TABLE>
<input type="hidden" name="tab" id="selected_tab" value="{$selected_tab}">
<input type="hidden" id="vn" name="vn" value="{$view_name}">
{if $overlay_events}
<input type="hidden" id="overlay_events" value="true">
{else}
<input type="hidden" id="overlay_events" value="false">
{/if}
<HR SIZE="1" NOSHADE>
<!-- End header.tpl -->
