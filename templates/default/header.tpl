<!-- Begin header.tpl -->
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title>Ganglia:: {$page_title}</title>
<meta http-equiv="Content-type" content="text/html; charset=utf-8">
<meta http-equiv="refresh" content="{$refresh}">
<script type="text/javascript"  src="js/jquery-1.6.2.min.js"></script>
<script type="text/javascript"  src="js/jquery-ui-1.8.14.custom.min.js"></script>
<script type="text/javascript"  src="js/jquery.liveSearch.js"></script>
<script type="text/javascript"  src="js/ganglia.js"></script>
<script type="text/javascript"  src="js/jquery.gangZoom.js"></script>
<script type="text/javascript"  src="js/jquery.cookie.js"></script>
<link type="text/css" href="css/smoothness/jquery-ui-1.8.14.custom.css" rel="stylesheet" />
<link type="text/css" href="css/jquery.liveSearch.css" rel="stylesheet" />
<link type="text/css" href="./styles.css" rel="stylesheet" />
<script type="text/javascript">
    var server_utc_offset={$server_utc_offset};
    var availablemetrics = [ {$available_metrics} ];
    $(function(){
        $( "#metrics-picker" ).autocomplete({
          source: availablemetrics
        });

        {$is_metrics_picker_disabled} 

	$(".submit_button").button();
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
        paddingBottom: 27
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
        var local_offset = new Date().getTimezoneOffset() * 60;
        var delta = -server_utc_offset - local_offset;
        var date = new Date((Math.floor(startTime) + delta) * 1000);
        $("#datepicker-cs").val(rrdDateTimeString(date));
        date = new Date((Math.floor(endTime) + delta) * 1000);
        $("#datepicker-ce").val(rrdDateTimeString(date));
    }
  });


</script>
{$custom_time_head}
</head>
<body style="background-color: #ffffff;">
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
  <li><a href="aggregate_graphs.php">Aggregate Graphs</a></li>
{if $overlay_events}
  <li><a href="events.php">Events</a></li>
{/if}
  <li><a href="#tabs-autorotation" onclick="autoRotationChooser();">Automatic Rotation</a></li>
  <li><a href="#mobile" onclick="location.href='mobile.php';">Mobile</a></li>
</ul>

<div id="tabs-main">
<form action="{$page}" method="GET" name="ganglia_form">
  <table id="table_top_chooser" width="100%" cellpadding="4" cellspacing="0" border="0">
  <tr bgcolor="#DDDDDD">
     <td bgcolor="#DDDDDD">
     <big><b>{$page_title} for {$date}</b></big>
     </td>
     <td bgcolor="#DDDDDD" align="right">
     <input class="submit_button" type="submit" value="Get Fresh Data" />
     </td>
  </tr>
  <tr>
     <td colspan="1">
    <div id="range_menu">{$range_menu}{$custom_time}</div>
     </td>
     <td>
      <b>{$alt_view}</b>
     </td>
  </tr>
  <tr>
  <td colspan="2">
  <div id="sort_menu">
   <b>Metric</b>&nbsp;&nbsp; <input name="m" onclick="$('#metrics-picker').val('');" type=text id="metrics-picker" /><input type="submit" value="Go">&nbsp;&nbsp;
     {$sort_menu}
  </div>
  </td>
  </tr>

  <tr><td colspan="2">{$node_menu}&nbsp;&nbsp;{$additional_filter_options}</td>
</tr>

  </TABLE>

<HR SIZE="1" NOSHADE>
<!-- End header.tpl -->
