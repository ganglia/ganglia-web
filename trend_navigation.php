<?php
$months_ahead = array(1,2,3,6,9,12,18,24);
$months_back = array(12,9,6,3,2,1);
if ( !isset($_REQUEST['trendrange']) )
  $_REQUEST['trendrange'] = 6;
if ( !isset($_REQUEST['trendhistory']) )
  $_REQUEST['trendhistory'] = 6;

?>
<center>
<div id="trend_range_menu">
<form id="trend_range_form">
Use data from last 
<?php
foreach ( $months_back as $index => $month ) {
  if (  $_REQUEST['trendhistory'] == $month )
    $checked = 'checked="checked"';
  else
    $checked = "";
?>
   <input OnChange='drawTrendGraph("<?php print $_SERVER['QUERY_STRING'] ?>" + "&" + $("#trend_range_form").serialize()); return false;' type="radio" id="trendhistory-<?php print $month; ?>" name="trendhistory" value="<?php print $month; ?>" <?php print $checked; ?>/>
   <label for="trendhistory-<?php print $month; ?>"><?php print $month; ?></label>
<?php
}
?>
months&nbsp;|&nbsp;Extend trend line 
<?php

if ( !isset($_REQUEST['trendrange']) )
  $_REQUEST['trendrange'] = 6;

foreach ( $months_ahead as $index => $month ) {

  if (  $_REQUEST['trendrange'] == $month )
    $checked = 'checked="checked"';
  else
    $checked = "";

?>
   <input OnChange='drawTrendGraph("<?php print $_SERVER['QUERY_STRING'] ?>" + "&" + $("#trend_range_form").serialize()); return false;' type="radio" id="range-<?php print $month; ?>" name="trendrange" value="<?php print $month; ?>" <?php print $checked; ?>/>
   <label for="range-<?php print $month; ?>"><?php print $month; ?></label>
<?php
}
?>
 months ahead</form></div>
</center>
<script type="text/javascript">
  $(function () {
    $("#trend_range_menu").buttonset();
  });
</script>