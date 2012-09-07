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

GANGLIA_URL="http://localhost/ganglia2/nagios/check_metric.php"

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
   echo "Sample invocation $0 host=localhost.localdomain metric_name=load_one operator=more critical_value=1"
   exit 3
fi

COMMAND="curl -s ${GANGLIA_URL}?${CHECK_ARGS}"
RESULT=`$COMMAND`
RETURN_VALUE=$?
# if curl fails we should fail
if [ $RETURN_VALUE -ne 0 ]; then
   echo "error fetching metric with command: ${COMMAND}"
   echo "result: $RESULT"
   echo "curl return_value: $RETURN_VALUE"
   exit 3
fi

EXIT_CODE=`echo $RESULT | cut -f1 -d'|'`

IFS='|'
for x in $EXIT_CODE; do
  case $x in
  OK)
    echo $RESULT
    exit 0;;
  WARNING)
    echo $RESULT
    exit 1;;
  CRITICAL)
    echo $RESULT
    exit 2;;
  *)
    echo $RESULT
    exit 3;;
  esac
done

# If we got no results that is an error in and of itself
echo "UNKNOWN: no result from ganglia"
exit 3
