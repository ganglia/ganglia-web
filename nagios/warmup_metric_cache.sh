#!/bin/sh

#################################################################
# This script will warmup the cache periodically (specified
# by SLEEP_TIME). This is useful if you have lots of Nagios
# checks 
#################################################################
SLEEP_TIME=11
WEB_USER="www-data"

SCRIPT_HOME=${0%/*}

# Go into an infinite loop
while [ 1 ]; do

  echo "Metric cache - "`date`
  sudo -u $WEB_USER php $SCRIPT_HOME/warmup_metric_cache.php
  sleep $SLEEP_TIME

done
