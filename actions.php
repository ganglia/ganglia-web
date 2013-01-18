<?php

include_once("./eval_conf.php");
include_once("./functions.php");

if ( isset($_GET['action']) && $_GET['action'] == "show_views" ) {
  //////////////////////////////////////////////////////////////////////////////////////////////////////
  // Show available views
  //////////////////////////////////////////////////////////////////////////////////////////////////////
  $available_views = get_available_views();
  ?>

  <table>
  <?php
  if ( isset($_GET['aggregate']) ) {
  ?>
     <tr><th>Host regular expression</th><td><?php print htmlspecialchars( join (",", $_GET['hreg']) ); ?></td></tr>
     <tr><th>Metric regular expression</th><td><?php print htmlspecialchars( join (",", $_GET['mreg']) ); ?></td></tr>
  <?php
    } else {
  ?>
     <tr><th>Hostname</th><td><?php print htmlspecialchars($_GET['host_name']); ?></td></tr>
     <tr><th>Metric/Report</th><td><?php print htmlspecialchars($_GET['metric_name']); ?></td></tr>
  <?php
  }
  ?>

  </table>
  <p>
  <form id="add_metric_to_view_form">
    Add graph to view: <br />
    <?php 
    // Get all the aggregate form variables and put them in the hidden fields
    if ( isset($_GET['aggregate']) ) {
	foreach ( $_GET as $key => $value ) {
	  if ( is_array($value) ) {
	    foreach ( $value as $index => $value2 ) {
	      print '<input type="hidden" name="' . htmlspecialchars($key) .'[]" value="' . htmlspecialchars($value2) . '" />';
	    }
	  } else {
	    print '<input type="hidden" name="' . htmlspecialchars($key) .'" value="' . htmlspecialchars($value) . '" />';
	  }
	}
    } else {
      // If hostname is not set we assume we are dealing with aggregate graphs
      print "<input type=\"hidden\" name=\"host_name\" value=\"".htmlspecialchars($_GET['host_name'])."\" />";
      $metric_name=$_GET['metric_name'];
      print "<input type=\"hidden\" name=\"metric_name\" value=\"".htmlspecialchars($_GET['metric_name'])."\" />";
      print "<input type=\"hidden\" name=\"type\" value=\"{$_GET['type']}\">";
      if (isset($_GET['vl']) && ($_GET['vl'] !== ''))
	  print "<input type=\"hidden\" name=\"vertical_label\" value=\"" . htmlentities(stripslashes($_GET['vl'])) . "\" />";
      if (isset($_GET['ti']) && ($_GET['ti'] !== ''))
	  print "<input type=\"hidden\" name=\"title\" value=\"" . htmlentities(stripslashes($_GET['ti'])) . "\" />";
      
      print "<table><tr><th rowspan=2>Optional thresholds to display</th><td>Warning</td><td><input size=6 name=\"warning\"></td>
	</tr><td>Critical</td><td><input size=6 name=\"critical\"></td></tr></table>";
    }
    ?>
    <br />
    <center>
    <select onChange="addItemToView()" name="view_name">
    <option value="none">Please choose a view to add to</option>
    <?php
    foreach ( $available_views as $view_id => $view ) {
      print "<option value=\"" . $view['view_name'] . "\">" . $view['view_name'] . "</option>";
    } 

  ?>
    </select>
    </center>
  </form>
<?php

} // end of if ( isset($_GET['show_views']) {
?>
