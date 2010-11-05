<?php

include_once("./conf.php");
include_once("./functions.php");

if ( isset($_GET['show_views']) ) {
  //////////////////////////////////////////////////////////////////////////////////////////////////////
  // Show available views
  //////////////////////////////////////////////////////////////////////////////////////////////////////
  $available_views = get_available_views();

  ?>

  Actions available<p>
  <table>
  <tr><th>Hostname</th><td><?php print $_GET['host_name']; ?></td></tr>
  <tr><th>Metric/Report</th><td><?php print $_GET['metric_name']; ?></td></tr>
  </table>
  <p>
  Add to view
  <form id="add_metric_to_view_form">
  <select onChange="" name=add_to_view>
  <option value='none'>Please choose one</option>
  <?php
  foreach ( $available_views as $view_id => $view ) {
    print "<option value='" . $view['name'] . "'>" . $view['name'] . "</option>";
  } 

  ?>

  </select>
  </form>

<?php

} // end of if ( isset($_GET['show_views']) {

?>