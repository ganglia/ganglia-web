<!-- Begin header.tpl -->
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title>Ganglia:: {$page_title}</title>
<meta http-equiv="Content-type" content="text/html; charset=utf-8">
<link type="text/css" href="css/smoothness/jquery-ui-1.10.2.custom.min.css" rel="stylesheet" />
<link type="text/css" href="css/jquery.liveSearch.css" rel="stylesheet" />
<link type="text/css" href="css/jquery.multiselect.css" rel="stylesheet" />
<link type="text/css" href="css/jquery.flot.events.css" rel="stylesheet" />
<link type="text/css" href="css/fullcalendar.css" rel="stylesheet" />
<link type="text/css" href="css/qtip.min.css" rel="stylesheet" />
<link type="text/css" href="css/chosen.min.css" rel="stylesheet" />
<link type="text/css" href="./styles.css" rel="stylesheet" />
<script type="text/javascript" src="js/jquery-1.9.1.min.js"></script>
<script>$.uiBackCompat = false;</script>
<script type="text/javascript" src="js/jquery-ui.min.js"></script>
<script type="text/javascript" src="js/jquery.livesearch.min.js"></script>
<script type="text/javascript" src="js/ganglia.js"></script>
<script type="text/javascript" src="js/jquery.gangZoom.js"></script>
<script type="text/javascript" src="js/jquery.cookie.js"></script>
<script type="text/javascript" src="js/jquery-ui-timepicker-addon.js"></script>
<script type="text/javascript" src="js/jquery.ba-bbq.min.js"></script>
<script type="text/javascript" src="js/combobox.js"></script>
<script type="text/javascript" src="js/jquery.scrollTo-1.4.3.1-min.js"></script>
<script type="text/javascript" src="js/jquery.buttonsetv.js"></script>
<script type="text/javascript" src="js/fullcalendar.js"></script>
<script type="text/javascript" src="js/jquery.qtip.min.js"></script>
<script type="text/javascript" src="js/jquery.jstree.js"></script>
<script type="text/javascript" src="js/chosen.jquery.min.js"></script>
<script type="text/javascript">
    var server_utc_offset={$server_utc_offset};
    var g_refresh_timer = setTimeout("refresh()", {$refresh} * 1000);

    function refreshHeader() {
      $.get('header.php?date_only=1', function(datetime) {
        var title = $("#page_title").text();
        var l = title.lastIndexOf(" at ");
        if (l != -1)
          title = title.substring(0, l);
        title += " at " + datetime;
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
      var range_menu = $("#range_menu");
      if (range_menu[0])
        range_menu.buttonset();

      var custom_range_menu = $("#custom_range_menu");
      if (custom_range_menu[0])
        custom_range_menu.buttonset();

      var sort_menu = $("#sort_menu");
      if (sort_menu[0])
        sort_menu.buttonset();

      g_overlay_events = ($("#overlay_events").val() == "true");

      g_tabIndex = new Object();
      g_tabName = [];
      var tabName = ["m", "s", "v", "agg", "ch", "ev", "rot", "mob"];
      var j = 0;
      for (var i in tabName) {
        if (tabName[i] == "ev" && !g_overlay_events)
          continue;
        g_tabIndex[tabName[i]] = j++;
        g_tabName.push(tabName[i]);
      }

      // Follow tab's URL instead of loading its content via ajax
      var tabs = $("#tabs");
      if (tabs[0]) {
        tabs.tabs();
        // Restore previously selected tab
        var selected_tab = $("#selected_tab").val();
        //alert("selected_tab = " + selected_tab);
        if (typeof g_tabIndex[selected_tab] != 'undefined') {
          try {
            //alert("Selecting tab: " + selected_tab);
            tabs.tabs('option', 'active', g_tabIndex[selected_tab]);
            if (selected_tab == "rot")
              autoRotationChooser();
          } catch (err) {
            try {
              alert("Error(ganglia.js): Unable to select tab: " + 
                    selected_tab + ". " + err.getDescription());
            } catch (err) {
              // If we can't even show the error, fail silently.
            }
          }
        }
        tabs.tabs({
          beforeActivate: 
          function(event, ui) {
            var tabIndex = ui.newTab.index();
            $("#selected_tab").val(g_tabName[tabIndex]);
            if (g_tabName[tabIndex] != "mob")
              $.cookie("ganglia-selected-tab-" + window.name, tabIndex);
            if (tabIndex == g_tabIndex["m"] ||
              tabIndex == g_tabIndex["v"] ||
              tabIndex == g_tabIndex["ch"])
              ganglia_form.submit();
          }
        });
      }
    });

  $(function () {
    $("#metrics-picker").val("{$metric_name}");
    $(".header_btn").button();

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
    initTimeShift();
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
  <div id="tabs-menu", {if $hide_header} style="visibility: hidden; display: none;" {/if}>
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
      <li><a href="#tabs-livedashboard" onclick="liveDashboardChooser();">Live Dashboard</a></li>
      <li><a href="#tabs-mobile" onclick="window.location.href='mobile.php';">Mobile</a></li>
    </ul>
  </div>

<div id="tabs-main">
<form action="{$page}" method="GET" name="ganglia_form">
  <div style="padding:5px;background-color:#dddddd">
     <big style="float:left;"><b id="page_title">{$page_title} at {$date}</b></big><input style="float:right;" class="header_btn" type="submit" value="Get Fresh Data"/><div style="clear:both"></div>
  </div>
  <div style="padding:5px 5px 0 5px;">
    <div style="float:left;" id="range_menu" class="nobr">{$range_menu}</div>
    <div style="float:left;" id="custom_range_menu">{$custom_time}</div>
    <div style="float:right;">{$additional_buttons}&nbsp;&nbsp;{$alt_view}</div>
    <div style="clear:both;"></div>
  </div>
  {if $context != "cluster" && $context != "cluster-summary"}
  <input type="hidden" name="m" id="metrics-picker">
  {/if}
  {if $context == "meta"}
  <div style="padding:5px 5px 0 5px;">
    {$sort_menu}
  </div>
  {/if}
  {if $node_menu != ""}
  <div id="node_menu" style="padding:5px 5px 0 5px;">
    {$node_menu}&nbsp;&nbsp;{$additional_filter_options}
  </div>
  {/if}

<input type="hidden" name="tab" id="selected_tab" value="{$selected_tab}">
<input type="hidden" id="vn" name="vn" value="{$view_name}">
{if $hide_header}
<input type="hidden" id="hide-hf" name="hide-hf" value="true">
{else}
<input type="hidden" id="hide-hf" name="hide-hf" value="false">
{/if}
{if $overlay_events}
<input type="hidden" id="overlay_events" value="true">
{else}
<input type="hidden" id="overlay_events" value="false">
{/if}
<hr size="1" noshade>
<!-- End header.tpl -->
