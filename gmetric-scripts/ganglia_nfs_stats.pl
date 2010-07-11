#!/usr/bin/perl

###########################################################################
# Author: Vladimir Vuksan http://vuksan.com/linux/
# Last Changed: $Date: 2009-09-06 20:51:39 -0400 (Ned, 06 Ruj 2009) $
# License: GNU Public License (http://www.gnu.org/copyleft/gpl.html)
# Collects NFS v3 metrics.
# By default it collects NFSd (server) metrics. If you supply
# the -c argument it will collect NFS client metrics
# Currently it will collect and send to Ganglia following metrics
# 3 -> getattr, 4 -> setattr, 5 -> lookup, 6 -> access, 8-> read
# 9 -> write, 10 -> create, 14 -> remove
# If you would like some of the other stats e.g. mkdir append them 
# below to @which_metrics
###########################################################################
$gmetric_command = "/usr/bin/gmetric";

if ( ! -x $gmetric_command ) {
	die("Gmetric command is not executable. Exiting...");
}

# Check out the nfs_stat has below for the list of all metrics
# and their appropriate index
@which_metrics = split(/ /, "3 4 5 6 8 9 10 14");

# Where to store the last stats file
$tmp_dir_base="/tmp/nfs_stats";

# Look whether -c argument was supplied
if ( $#ARGV == 0 && $ARGV[0] eq "-c" ) {
	print "Collecting NFSv3 client stats\n";
	$proc_file="/proc/net/rpc/nfs";
	$tmp_stats_file=$tmp_dir_base . "/" . "nfs_client_stats";
	$metric_prefix = "nfscl_v3_";
} else {
	print "Collecting NFSdv3 (server) stats\n";
	$proc_file="/proc/net/rpc/nfsd";
	$tmp_stats_file=$tmp_dir_base . "/" . "nfs_server_stats";
	$metric_prefix = "nfsd_v3_";
}

###########################################################################
# This is the order of metrics in /proc/net/rpc/nfsd
###########################################################################
%nfs_stat = (
	3 => "getattr",
	4 => "setattr",
	5 => "lookup",
	6 => "access",
	7 => "readlink",
	8 => "read",
	9 => "write",
	10 => "create",
	11 => "mkdir",
	12 => "symlink",
	13 => "mknod",
	14 => "remove",
	15 => "rmdir",
	16 => "rename",
	17 => "link",
	18 => "readdir",
	19 => "readdirplus",
	20 => "fsstat",
	21 => "fsinfo",
	22 => "pathconf",
	23 => "commit"
);

# If the tmp directory doesn't exit create it
if ( ! -d $tmp_dir_base ) {
	system("mkdir -p $tmp_dir_base");
}

###############################################################################
# We need to store a baseline with statistics. If it's not there let's dump 
# it into the file. Don't do anything else
###############################################################################
if ( ! -f $tmp_stats_file ) {
	print "Creating baseline. No output this cycle\n";
	system("cat $proc_file > $tmp_stats_file");
} else {

	# Let's read in the file from the last poll
	open(OLDNFSDSTATUS, "< $tmp_stats_file");
	
	while(<OLDNFSDSTATUS>)
	{
		my($line) = $_;
		chomp($line);
		if ( /^proc3/ ) {
			@old_stats = split(/ /,$line);
			last;
		}
	}
	
	# Get the time stamp when the stats file was last modified
	$old_time = (stat $tmp_stats_file)[9];
	close(OLDNFSDSTATUS);

	open(NFSDSTATUS, "< $proc_file");
	
	$new_time = time(); 
	
	while(<NFSDSTATUS>)
	{
		my($line) = $_;
		chomp($line);
		if ( /^proc3/ ) {
			@new_stats = split(/ /,$line);
			system("echo '$line' >  $tmp_stats_file");
			last;
		}
	}
	
	close(NFSDSTATUS);

	# Time difference between this poll and the last poll
	my $time_difference = $new_time - $old_time;
	if ( $time_difference < 1 ) {
		die("Time difference can't be less than 1");
	}

	# Calculate deltas and send them to ganglia
	for ( $i = 0 ; $i <= $#which_metrics; $i++ ) {
		my $metric = $which_metrics[$i];
		my $delta = $new_stats[$metric] - $old_stats[$metric];
		my $rate = int($delta / $time_difference);
		if ( $rate < 0 ) {
			print "Something is fishy. Rate for " . $metric . " shouldn't be negative. Perhaps counters were reset. Doing nothing";
		} else {
			print "$nfs_stat{$metric} = $rate / sec\n";
			system($gmetric_command . " -tuint16 -u 'calls/sec' -n " . $metric_prefix . $nfs_stat{$metric} . " -v " . $rate);
		}
	}

}