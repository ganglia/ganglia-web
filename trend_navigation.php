<center>Show trend months ahead 
<?php
$months_ahead = array(3,6,9,12,18,24);
foreach ( $months_ahead as $index => $month ) {
?>
   <a href='#' onClick='drawTrendGraph("<?php print $_REQUEST['url'] . "&trendrange=" . $month ?>"); return false;"
<?php

?>
</center>
