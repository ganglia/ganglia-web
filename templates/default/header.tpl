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
<script>
 $(function(){
	$("#tabs").tabs();
	jQuery('#metric-search input[name="q"]').liveSearch({url: 'search.php?q='});
    });
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
    </ul>

<div id="tabs-main">

<FORM ACTION="{page}" METHOD="GET" NAME="ganglia_form">
<TABLE WIDTH="100%">
<TR>
  <TD ROWSPAN="2" WIDTH="150">
  <A HREF="http://ganglia.sourceforge.net/"><IMG SRC="{images}/logo.jpg" HEIGHT="91" WIDTH="150" ALT="Ganglia" BORDER="0"></A>
  </TD>
  <TD VALIGN="TOP">

  <TABLE WIDTH="100%" CELLPADDING="8" CELLSPACING="0" BORDER=0>
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
     {range_menu}
     {custom_time}<br>
     {metric_menu}
     {sort_menu}
     </TD>
     <TD>
      <B>{alt_view}</B>
     </TD>
  </TR>
  </TABLE>

  </TD>
</TR>
</TABLE>

<FONT SIZE="+1">
{node_menu}
</FONT>
<HR SIZE="1" NOSHADE>
