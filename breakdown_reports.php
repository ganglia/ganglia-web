<script>
function createBreakdown() {
    if ($('#hreg').val() == "" || $('#metric_chooser').val() == "") {
      alert("Host regular expression and metric name can't be blank");
      return false;
    }

    var params = $("#breakdown_form").serialize();
    $("#reports_results").html('<img src="img/spinner.gif">');
    $.ajax({url: 'breakdown_report_results.php', 
	    cache: false,
	    data: params, 
	    success: function(data) {
      $("#reports_results").html(data);
	}});
    return false;
}
  
$(function() {
   
  var availablemetrics = [
<?php

  require_once('./eval_conf.php');
  require_once('./functions.php');

  $available_metrics = array();
  retrieve_metrics_cache("metric_list");

  # If metric_list hash exists we pulled it out of cache. Otherwise
  # it was just fetched from gmetad so we need to massage the output
  if ( ! isset($index_array['metric_list']) ) {
    $index_array['metric_list'] = array_keys($index_array["metrics"]);
  }
  
  foreach ($index_array['metric_list'] as $key => $value) {
    $available_metrics[] = "\"$value\"";
  }

  print join(",", $available_metrics);
  unset($available_metrics);
?>];

  $( "#metric_chooser" ).autocomplete({
      source: availablemetrics,
      change: function(event, ui) {
	$.cookie("ganglia-breakdown-report-metric" + window.name,
	         $("#metric_chooser").val());
      }
  });

});
</script>
<form id="breakdown_form">
Host regex: <input size=50 id="hreg" name="hreg">
Metric <input size=50 id="metric_chooser" name="metric">
<button onclick="createBreakdown(); return false" id="create_button">Create Report</button>
</form>
<div id="reports_results">
</div>
<script>
$(function() {
    $("#create_button")
      .button();
    });
</script>
