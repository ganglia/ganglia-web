<?php

/*
Author: Vladimir Vuksan
Script for acquiring common metrics for Ganglia. Assumes PMTA web interface is
running on port 8080
*/

$url = "http://127.0.0.1:8080/status?format=xml";

function send_to_ganglia ( $metric, $value, $type, $unit ) {
	$gmetric_cmd = "/usr/bin/gmetric -d 120 ";
	system($gmetric_cmd . " -n " . $metric . " -v " . $value . " -t " . $type . " -u " . $unit);
}


$stats_array =  simplexml_load_file($url);

$msgs_out = $stats_array->data->status->traffic->lastMin->out->msg;
$msgs_in = $stats_array->data->status->traffic->lastMin->in->msg;
$traffic_out = $stats_array->data->status->traffic->lastMin->out->kb * 1000;
$traffic_in = $stats_array->data->status->traffic->lastMin->in->kb * 1000;


print "msgs_out: " . $msgs_out . "\n";
print "msgs_in: " . $msgs_in . "\n";

send_to_ganglia("pmta_msgs_out", $msgs_out, "uint32", "msgs/min");
send_to_ganglia("pmta_msgs_in", $msgs_in, "uint32", "msgs/min");
send_to_ganglia("pmta_traffic_out", $traffic_out, "double", "Bytes/min");
send_to_ganglia("pmta_traffic_in", $traffic_in, "double", "Bytes/min");

$conn_in = $stats_array->data->status->conn->smtpIn->cur;
$conn_out = $stats_array->data->status->conn->smtpOut->cur;

print "Connections in: " . $conn_in. "\n";
print "Connections out: " . $conn_out. "\n";

send_to_ganglia("pmta_conn_out", $conn_out, "uint32", "conn");
send_to_ganglia("pmta_conn_in", $conn_in, "uint32", "conn");

$queue_smtp_rcp = $stats_array->data->status->queue->smtp->rcp;
$queue_smtp_dom = $stats_array->data->status->queue->smtp->dom;
$queue_smtp_bytes = $stats_array->data->status->queue->smtp->kb * 1000;

print "Queue SMTP recipients: " . $queue_smtp_rcp . "\n";
print "Queue SMTP domains: " . $queue_smtp_dom . "\n";
print "Queue SMTP kB: " . $queue_smtp_bytes . "\n";

send_to_ganglia("pmta_queue_rcpt", $queue_smtp_rcp, "uint32", "rcpts");
send_to_ganglia("pmta_queue_dom", $queue_smtp_dom, "uint32", "domains");
send_to_ganglia("pmta_queue_size", $queue_smtp_bytes, "uint32", "Bytes");


?>
