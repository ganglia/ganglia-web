<!-- Begin header.tpl -->
<!doctype html>
<html>
  <head>
    <title>Ganglia:: {$page_title}</title>
    <meta http-equiv="Content-type" content="text/html; charset=utf-8">
    {include('scripts.tpl')}
    <script type="text/javascript">
     var time_ranges = {$time_ranges};
     var server_timezone='{$server_timezone}';
     var g_refreshInterval = {$refresh};
     var g_refresh_timer = setTimeout("refresh()", g_refreshInterval * 1000);
     var tz = jstz.determine();

     function refreshHeader() {
       $.get('header.php?date_only=1', function(datetime) {
         var pageTitle = $("#page_title");
         var title = pageTitle.text();
         var l = title.lastIndexOf(" at ");
         if (l != -1)
           title = title.substring(0, l);
         title += " at " + datetime;
         pageTitle.text(title);
       });
     }

     function refresh() {
       var selected_tab = $("#selected_tab").val();
       if (selected_tab == "agg") {
         refreshAggregateGraph();
         g_refresh_timer = setTimeout("refresh()", g_refreshInterval * 1000);
       } else if (selected_tab == "s") {
         // Dont refresh the serach tab
       } else if (selected_tab == "v") {
         refreshHeader();
         if ($.isFunction(window.refreshView)) {
           refreshView();
           g_refresh_timer = setTimeout("refresh()", g_refreshInterval * 1000);
         } else if ($.isFunction(window.refreshDecomposeGraph)) {
           refreshDecomposeGraph();
           g_refresh_timer = setTimeout("refresh()", g_refreshInterval * 1000);
         } else
         ganglia_form.submit();
       } else if (selected_tab == "ev") {
         refreshOverlayEvent();
         g_refresh_timer = setTimeout("refresh()", g_refreshInterval * 1000);
       } else if (selected_tab == "m") {
         if ($.isFunction(window.refreshClusterView)) {
           refreshHeader();
           refreshClusterView();
           g_refresh_timer = setTimeout("refresh()", g_refreshInterval * 1000);
         } else if ($.isFunction(window.refreshHostView)) {
           refreshHeader();
           refreshHostView();
           g_refresh_timer = setTimeout("refresh()", g_refreshInterval * 1000);
         } else
         ganglia_form.submit();
       } else
       ganglia_form.submit();
     }

     // start and end are momemnts
     function setStartAndEnd(start, end) {
       // Generate RRD friendly date/time strings
       $("#datepicker-cs").val(start.format('MM/D/YYYY HH:mm'));
       $("#datepicker-ce").val(end.format('MM/D/YYYY HH:mm'));
     }

     function getCurrentRange() {
       var range = null;
       var range_menu = $("#range_menu");
       if (range_menu[0])
         range = $("#range_menu :radio:checked + label").text();
       return range;
     }

     // Return the current time range as a pair of moments
     function getCurrentTimeRange() {
       var cs = $("#datepicker-cs").val();
       var ce = $("#datepicker-ce").val();
       var start = 0;
       var end = 0;
       if (getCurrentRange() == "custom" && cs && ce) {
         if ($("#tz").val() == "") {
           start = moment.tz(cs, server_timezone);
           end = moment.tz(ce, server_timezone);
         } else {
           start = moment(cs);
           end = moment(ce);
         }
       } else {
         end = ($("#tz").val() == "") ? moment().tz(server_timezone) : moment();
         var range = getCurrentRange();
         start = moment(end).subtract(time_ranges["rng_" + range], "s");
       }
       return {
         start: start,
         end: end};
     }

     /* selStart and selEnd are fractions of the current time range */
     gangZoomDone = function done(selStart, selEnd) {
       var currentRange = getCurrentTimeRange();
       var span = currentRange.end.unix() - currentRange.start.unix();
       setStartAndEnd(
         moment(currentRange.start).add(Math.floor(selStart * span), "s"),
         moment(currentRange.start).add(Math.floor(selEnd * span), "s"));
       document.forms['ganglia_form'].submit();
     }

     gangZoomCancel = function(selStart, selEnd) {
     }

     g_gangZoomDefaults = {
       done: gangZoomDone,
       cancel: gangZoomCancel
     }

     function initCustomTimeRangeDragSelect(context) {
       context.find(".host_small_zoomable").gangZoom($.extend({
         paddingLeft: 67,
         paddingRight: 30,
         paddingTop: 38,
         paddingBottom: 25
       }, g_gangZoomDefaults));

       context.find(".host_medium_zoomable").gangZoom($.extend({
         paddingLeft: 67,
         paddingRight: 30,
         paddingTop: 38,
         paddingBottom: 40
       }, g_gangZoomDefaults));

       context.find(".host_default_zoomable").gangZoom($.extend({
         paddingLeft: 66,
         paddingRight: 30,
         paddingTop: 37,
         paddingBottom: 50
       }, g_gangZoomDefaults));

       context.find(".host_large_zoomable").gangZoom($.extend({
         paddingLeft: 66,
         paddingRight: 29,
         paddingTop: 37,
         paddingBottom: 56
       }, g_gangZoomDefaults));

       context.find(".cluster_zoomable").gangZoom($.extend({
         paddingLeft: 67,
         paddingRight: 30,
         paddingTop: 37,
         paddingBottom: 50
       }, g_gangZoomDefaults));
     }

     $(function() {
       var range_menu = $("#range_menu");
       if (range_menu[0]) {
         range_menu.children(":radio").each(function() {
           $(this).checkboxradio( { icon: false } );
         });
         range_menu.controlgroup();
       }

       var custom_range_menu = $("#custom_range_menu");
       if (custom_range_menu[0])
         custom_range_menu.controlgroup();

       var sort_menu = $("#sort_menu");
       if (sort_menu[0]) {
         sort_menu.children(":radio").each(function() {
           $(this).checkboxradio( { icon: false } );
         });
         sort_menu.controlgroup();
       }

       g_overlay_events = ($("#overlay_events").val() == "true");

       g_tabIndex = new Object();
       g_tabName = [];
       var tabName = ["m", "s", "v", "agg", "ch", "ev", "rep", "rot", "lvd", "cub", "mob"];
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
           beforeActivate: function(event, ui) {
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

       initCustomTimeRangeDragSelect($(document.documentElement));

       var tzPicker = $("#timezone-picker");
       if (tzPicker.length) {
         tzPicker.chosen({ max_selected_options:1,
                           disable_search:true}).
	          on('change', function(evt, params) {
		    if (params.selected == 'browser') {
		      $("#tz").val(tz.name());
		    } else {
		      $("#tz").val("");
		    }
		    ganglia_form.submit();
	          });
         tzPicker.val("{$timezone_option}").trigger('chosen:updated');
       }

       var dateTimePickerOptions = {
         showOn: "button",
         constrainInput: false,
         buttonImage: "img/calendar.gif",
         buttonImageOnly: true,
         showButtonPanel: ("{$timezone_option}" == 'browser')
       };

       var datepicker_cs = $("#datepicker-cs");
       if (datepicker_cs[0])
         datepicker_cs.datetimepicker(dateTimePickerOptions);

       var datepicker_ce = $("#datepicker-ce");
       if (datepicker_ce[0])
         datepicker_ce.datetimepicker(dateTimePickerOptions);

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
      <div id="tabs-menu" {if $hide_header} style="visibility: hidden; display: none;" {/if}>
        <ul>
          <li><a href="#tabs-main">Main</a></li>
          <li><a href="#tabs-search">Search</a></li>
          <li><a href="#tabs-main">Views</a></li>
          <li><a href="aggregate_graphs.php">Aggregate Graphs</a></li>
          <li><a href="#tabs-main">Compare Hosts</a></li>
          {if $overlay_events}
            <li><a href="events.php">Events</a></li>
          {/if}
          <li><a href="breakdown_reports.php">Reports</a></li>
          <li><a href="#tabs-autorotation"
                 onclick="autoRotationChooser();">Automatic Rotation</a></li>
          <li><a href="#tabs-livedashboard"
                 onclick="liveDashboardChooser();">Live Dashboard</a></li>
          {if $cubism}
            <li><a href="cubism_form.php">Cubism</a></li>
          {/if}
          <li><a href="#tabs-mobile"
                 onclick="window.location.href='mobile.php';">Mobile</a></li>
        </ul>
      </div>

      <div id="tabs-main">
        <form action="{$page}" method="GET" name="ganglia_form">
          <div style="padding:5px;background-color:#dddddd;display:flex;align-items:center;">
            <big style="float:left;">
              <b id="page_title">{$page_title} at {$date}</b>
            </big>
            <input style="margin-left:auto;"
                   class="header_btn"
                   type="submit"
                   value="Get Fresh Data"/>
            <div style="clear:both"></div>
          </div>
          <div>
            <div style="float:left;padding:5px 0 0 5px;">{$range_menu}</div>
            {if $context == "meta" || $context == "cluster" || $context == "cluster-summary" || $context == "host" || $context == "views" || $context == "decompose_graph" || $context == "compare_hosts"}
              {assign "Feb 27 2007 00:00, 2/27/2007, 27.2.2007, now -1 week, -2 days, start + 1 hour, etc." examples}
              <div style="float:left;padding:5px 0 0 5px;"
                   id="custom_range_menu">
                <span class="nobr">or&nbsp;from&nbsp;
                  <input type="text"
                         title="{$examples}"
                         name="cs"
                         id="datepicker-cs"
                         size="17"
                         {if isset($cs)} value="{$cs}"{/if}>
                  &nbsp;to&nbsp;
                  <input type="text"
                         title="{$examples}"
                         name="ce"
                         id="datepicker-ce"
                         size="17"
                         {if isset($ce)} value="{$ce}"{/if}>
                  &nbsp;
                  <input type="submit" value="Go">
                  <input type="button" value="Clear" onclick="ganglia_submit(1)">
                </span>
              </div>
              <div style="float:left;padding:5px 0 0 5px;" id="timezone">Timezone:&nbsp;
                <select id="timezone-picker" style="width:100px;">
                  <option value="browser">Browser</option>
                  <option value="server">Server</option>
                </select>
              </div>
            {/if}
            <div style="float:right;padding:5px 0 0 5px;">
              {if $context == "views" || $context == "decompose_graph" || $context == "host"}
                <input title="Hide/Show Events"
                       type="checkbox"
                       id="show_all_events"
                       onclick="showAllEvents(this.checked)"/>
                <label for="show_all_events">Hide/Show Events</label>
              {/if}
              &nbsp;&nbsp;{$alt_view}
            </div>
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
          <div style="clear:both;"></div>
          {if $node_menu != ""}
            <div id="node_menu" style="padding:5px 5px 0 5px;">
              {$node_menu}&nbsp;&nbsp;{$additional_filter_options}
            </div>
          {/if}

          <input type="hidden"
                 name="tab"
                 id="selected_tab"
                 value="{$selected_tab}">
          <input type="hidden" id="vn" name="vn" value="{$view_name}">
          <input type="hidden" id="tz" name="tz" value="{$timezone_value}">
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
