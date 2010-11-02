<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<HTML>
<HEAD>
<TITLE>Ganglia:: {page_title}</TITLE>
<META http-equiv="Content-type" content="text/html; charset=utf-8">
<META http-equiv="refresh" content="{refresh}">
<SCRIPT TYPE="text/javascript" SRC="js/jquery-1.4.3.min.js"></script>
<script type="text/javascript" src="js/jquery-ui-1.8.5.custom.min.js"></script>
<script type="text/javascript" src="js/jquery.liveSearch.js"></script>
<link type="text/css" href="css/smoothness/jquery-ui-1.8.5.custom.css" rel="stylesheet" />
<link type="text/css" href="css/jquery.liveSearch.css" rel="stylesheet" />
<LINK rel="stylesheet" href="./styles.css" type="text/css">
<style>
    #table_top_chooser {
      font-size: 12px
    }
</style>
<script>
 $(function(){

   var availablemetrics = [
      {available_metrics}
   ];


	$("#tabs").tabs();
	$( "#range_menu" ).buttonset();
	$( "#sort_menu" ).buttonset();
	jQuery('#metric-search input[name="q"]').liveSearch({url: 'search.php?q='});
	$(function() {
		$( "#datepicker-cs" ).datepicker({
			showOn: "button",
			buttonImage: "img/calendar.gif",
			buttonImageOnly: true
		});
	});
	$(function() {
		$( "#datepicker-ce" ).datepicker({
			showOn: "button",
			buttonImage: "img/calendar.gif",
			buttonImageOnly: true
		});
	});

	$( "#metrics-picker" ).autocomplete({
	    source: availablemetrics
	});

	{is-metrics-picker-disabled}

    });

    function ganglia_submit(clearonly) {
      document.getElementById("datepicker-cs").value = "";
      document.getElementById("datepicker-ce").value = "";
      if (! clearonly)
	document.ganglia_form.submit();
    }
</script>
{custom_time_head}
</HEAD>
<BODY BGCOLOR="#FFFFFF">
<style type="text/css">
    body{ font: 75% "Trebuchet MS", sans-serif; margin: 5px;}
</style>
<div id="tabs">
<ul>
  <li><a href="#tabs-main">Main</a></li>
  <li><a href="#tabs-search" onclick="getSearchContent();">Search</a></li>
  <li><a href="#tabs-dashboards" onclick="getDashboardsContent();">Dashboards</a></li>
</ul>

<div id="tabs-main">
<FORM ACTION="{page}" METHOD="GET" NAME="ganglia_form">
  <TABLE id="table_top_chooser" WIDTH="100%" CELLPADDING="4" CELLSPACING="0" BORDER=0>
  <TR BGCOLOR="#DDDDDD">
     <TD BGCOLOR="#DDDDDD">
     <FONT SIZE="+1">
     <B>{page_title} for {date}</B>
     </FONT>
     </TD>
     <TD BGCOLOR="#DDDDDD" ALIGN="RIGHT">
     <INPUT TYPE="SUBMIT" VALUE="Get Fresh Data">
     </TD>
  </TR>
  <TR>
     <TD COLSPAN="1">
    <div id="range_menu">{range_menu}{custom_time}</div>
     </TD>
     <TD>
      <B>{alt_view}</B>
     </TD>
  </TR>
  <TR>
  <TD COLSPAN="2">
  <div id="sort_menu">
   <B>Metric</B>&nbsp;&nbsp; <input name="m" type=text id="metrics-picker" /><input type="submit" value="Go">&nbsp;&nbsp;
     {sort_menu}
  </div>
  </TD>
  </TR>

  </TABLE>


<FONT SIZE="+1">
{node_menu}
</FONT>
<HR SIZE="1" NOSHADE>
