#!/bin/bash

# Copyright 2011 The greplin-nagios-utils Authors.
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#     http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.

GANGLIA_URL="http://localhost/ganglia/nagios/check_host_regex.php"

# Build the rest of the arguments into the arg string for the URL.
CHECK_ARGS=''
if [ "$#" -gt "0" ]
then
  CHECK_ARGS=$1
  shift
  for ARG in "$@"
  do
    CHECK_ARGS=${CHECK_ARGS}"&"${ARG}
  done
else
   echo "Sample invocation $0 hreg=web|apache checks=load_one,more,1:load_five,more,2 ignore_unknowns=0"
   echo "   Set ignore_unknowns=1 if you want to ignore hosts that don't posses a particular metric."
   echo "   This is useful if you want to use a catchall regex e.g. .* however some hosts lack a metric"
   exit 1 
fi

RESULT=`curl -s -g "${GANGLIA_URL}?${CHECK_ARGS}"`
EXIT_CODE=`echo $RESULT | cut -f1 -d'!'`
REST=`echo $RESULT | cut -f2 -d'!'`
for x in $EXIT_CODE; do
  case $x in
  OK)
    echo $REST
    exit 0;;
  WARNING)
    echo $REST
    exit 1;;
  CRITICAL)
    echo $REST
    exit 2;;
  *)
    echo $REST
    exit 3;;
  esac
done
