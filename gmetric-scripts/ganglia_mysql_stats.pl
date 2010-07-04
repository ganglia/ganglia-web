#!/usr/bin/perl

###########################################################################
# Author: Vladimir Vuksan http://vuksan.com/linux/
# Last Changed: $Date: 2009-09-06 20:51:39 -0400 (Ned, 06 Ruj 2009) $
# License: GNU Public License (http://www.gnu.org/copyleft/gpl.html)
# Collects mySQL server metrics
# Inspired by Ben Hartshorne's mySQL gmetric script http://ben.hartshorne.net/ganglia/
###########################################################################

# NEED TO MODIFY FOLLOWING
# Adjust this variables appropriately. Feel free to add any options to gmetric_command
# necessary for running gmetric in your environment to gmetric_options e.g. -c /etc/gmond.conf
$gmetric_exec = "/usr/bin/gmetric";
$gmetric_options = "-c /etc/gmond.conf";

# You only need to grant usage privilege to the user getting the stats e.g.
#### grant USAGE on *.* to 'ganglia'@'localhost' identified by 'xxxxx';
$stats_command = "/usr/bin/mysqladmin -u ganglia --password=xxxxx extended-status";

# YOU COULD MODIFY FOLLOWING
# To find out a list of all metrics please do mysqladmin extended-status
# MySQL keeps two types of metrics. Counters e.g. ones that keep increasing
# and absolute metrics ie. number of connections right now. For counters 
# we need to calculate rate ie. delta between timeA and timeB divided by time.
# If you need other metrics add them to either of the two hashes and specify
# the units e.g. bytes, connections, etc.
# Explanation what these metrics means can be found at
# http://dev.mysql.com/doc/refman/5.0/en/server-status-variables.html
%counter_metrics = (
	"Bytes_received" => "bytes",
	"Bytes_sent" => "bytes",
	"Com_delete" => "operations",
	"Com_insert" => "operations",
	"Com_replace" => "operations", 
	"Com_select" => "operations", 
	"Com_update" => "operations",
	"Key_reads" => "operations",
	"Qcache_hits" => "hits",
	"Questions" => "queries",
	"Connections" => "connections",
	"Threads_created" => "threads",
        "Slow_queries" => "queries"
);

%absolute_metrics = ( 
        "Threads_connected" => "threads",
	"Threads_running" => "threads" 
);

# DON"T TOUCH BELOW UNLESS YOU KNOW WHAT YOU ARE DOING
if ( ! -x $gmetric_exec ) {
	die("Gmetric binary is not executable. Exiting...");
}

$gmetric_command = $gmetric_exec . " " . $gmetric_options;
$debug = 0;

# Where to store the last stats file
$tmp_dir_base="/tmp/mysqld_stats";
$tmp_stats_file=$tmp_dir_base . "/" . "mysqld_stats";

# If the tmp directory doesn't exit create it
if ( ! -d $tmp_dir_base ) {
	system("mkdir -p $tmp_dir_base");
}

###############################################################################
# We need to store a baseline with statistics. If it's not there let's dump 
# it into a file. Don't do anything else
###############################################################################
if ( ! -f $tmp_stats_file ) {
	print "Creating baseline. No output this cycle\n";
	system("$stats_command > $tmp_stats_file");
} else {

	######################################################
	# Let's read in the file from the last poll
	open(OLDSTATUS, "< $tmp_stats_file");
	
	while(<OLDSTATUS>)
	{
		if (/\s+(\S+)\s+\S+\s+(\S+)/) {
			$old_stats{$1}=${2};
		}	
	}
	
	# Get the time stamp when the stats file was last modified
	$old_time = (stat $tmp_stats_file)[9];
	close(OLDSTATUS);

	#####################################################
	# Get the new stats
	#####################################################
	system("$stats_command > $tmp_stats_file");
	open(NEWSTATUS, "< $tmp_stats_file");
	$new_time = time(); 
	
	while(<NEWSTATUS>)
	{
		if (/\s+(\S+)\s+\S+\s+(\S+)/) {
			$new_stats{$1}=${2};
		}
	}
	close(NEWSTATUS);

	# Time difference between this poll and the last poll
	my $time_difference = $new_time - $old_time;
	if ( $time_difference < 1 ) {
		die("Time difference can't be less than 1");
	}
	
	#################################################################################
	# Calculate deltas for counter metrics and send them to ganglia
	#################################################################################	
	while ( my ($metric, $units) = each(%counter_metrics) ) {
		my $rate = ($new_stats{$metric} - $old_stats{$metric}) / $time_difference;

		if ( $rate < 0 ) {
			print "Something is fishy. Rate for " . $metric . " shouldn't be negative. Perhaps counters were reset. Doing nothing";
		} else {
			print "$metric = $rate / sec\n";
			if ( $debug == 0 ) {
				system($gmetric_command . " -u '$units/sec' -tfloat -n mysqld_" . $metric . " -v " . $rate);
			}
			
		}
		
	}
	
	#################################################################################
	# Just send absolute metrics. No need to calculate delta
	#################################################################################
	while ( my ($metric, $units) = each(%absolute_metrics) ) {
		print "$metric = $new_stats{$metric}\n";
			if ( $debug == 0 ) {
				system($gmetric_command . " -u $units -tuint16 -n mysqld_" . $metric . " -v " . $new_stats{$metric});
			}
	}

}