<?php


####################################################################################
# Let's find out what 
####################################################################################
$conf_dir = "./conf";

$hostname = "centos1";


$default_reports = array("included_reports" => array(), "blacklisted_reports" => array());
if ( is_file($conf_dir . "/default.json") ) {
  $default_reports = array_merge($default_reports,json_decode(file_get_contents($conf_dir . "/default.json"), TRUE));
}

$host_file = $conf_dir . "/host_" . $hostname . ".json";
$override_reports = array("included_reports" => array(), "blacklisted_reports" => array());
if ( is_file($host_file) ) {
  $override_reports = array_merge($override_reports, json_decode(file_get_contents($host_file), TRUE));
}

# Merge arrays
$reports["included_reports"] = array_merge( $default_reports["included_reports"] , $override_reports["included_reports"]);
$reports["blacklisted_reports"] =  array_merge($default_reports["blacklisted_reports"] , $override_reports["blacklisted_reports"]);

# Remove duplicates
$reports["included_reports"] = array_unique($reports["included_reports"]);
$reports["blacklisted_reports"] = array_unique($reports["blacklisted_reports"]);

print_r($reports);

?>
