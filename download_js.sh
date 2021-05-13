#!/bin/bash
CONFFILE="/srv/www/htdocs/ganglia-web/conf_default.php"
SAVEDIR='/srv/www/htdocs/ganglia-web'
PHPCONFDIR='/etc/apache2/conf.d/'
PHPCONFNAME='conf.php'
TARBALL='/tmp/ajax_ganglia.tar.gz'
RESCRIPT='/tmp/download_js.sh'
SUFFIX='ajax_libs'
TMPDIR='/tmp'

function get_conf() {
declare -f -F conf_stor > /dev/null
if [ $? -eq 0 ] ; then
	conf_stor
else
	cat ${CONFFILE}
fi
}

function usage() {
cat <<EOF
	$0 parses ${CONFFILE}
	for external java script libraries and downloads or recreate the script
	so that it can download them on another computer.
	Options are:
	-h:	prints this help
	-d:	download the libraries to 
		$SAVEDIR 
		and modify/write
		${PHPCONFDIR}/${PHPCONFNAME}
		so that downloaded libraries are preferred
	-t:	download the libraries and store them in 
		$TARBALL
	-r:	recreate this script by parsing 
		$CONFFILE 
		and store the download locations of the java script libraries
		direclty in the recreated script, which
		will stored in 
		$RESCRIPT
EOF
}
tarball=0
recreate=0
download_libs=0
usage_set=1

while getopts "h?trd" opt; do 
	case "$opt" in
	h|\?)
		usage_set=1
		;;
	t)
		usage_set=0
		tarball=1
		recreate=0
		;;
	r)
		usage_set=0
		recreate=1
		;;
	d)
		usage_set=0
		download_libs=1
		recreate=0
		;;
	esac
done

if [ $usage_set -eq 1 ] ; then
	usage
	exit 0
fi

if [ $recreate -eq 1 ] ; then
	declare -f -F conf_stor > /dev/null
	if [ $? -eq 0 ] ; then
		echo "This script was recreated so could not recreate it."
		exit 1
	fi
	cat << EOF > $RESCRIPT
#!/bin/bash
# this file was recreated on $(uname -n) at $(date)
function conf_stor() {
cat <<ENDCONF
EOF
	grep 'https://cdnjs'  $CONFFILE | sed 's@\$@\\\$@' >> $RESCRIPT
	cat << EOF >> $RESCRIPT

ENDCONF
}
EOF
	cat $0 | sed -e 's@#!/bin/bash@@' \
	-e 's@usage_set=1@usage_set=0@' \
	-e 's@tarball=0@tarball=1@' >> $RESCRIPT
	echo "wrote $RESCRIPT"
	exit 0
fi


which curl &> /dev/null || echo "need curl to download ajax libraries" 
which curl &> /dev/null || exit 1

if [ $tarball -eq 1 ] ; then
	test -d ${TMPDIR}/${SUFFIX} || mkdir -vp ${TMPDIR}/${SUFFIX}
	PHPCONFFILELOC=${TMPDIR}/${SUFFIX}/${PHPCONFNAME}
	cd ${TMPDIR}
else
	test -d ${SAVEDIR}/${SUFFIX} || mkdir -vp ${SAVEDIR}/${SUFFIX}
	PHPCONFFILELOC=${PHPCONFDIR}/${PHPCONFNAME}
	cd ${SAVEDIR}
fi

for lib_line in $(get_conf | grep -e '^$conf.* = \"https://cdnjs' | tr ' ' '@') ; do 
        conf_line=$(echo $lib_line | cut -f 1 -d '@')
        url=$(echo $lib_line | cut -f 3 -d '@' |tr -d '"' | tr -d ';')
	file_name=$(basename $url)
	echo -n "getting ${file_name}: " 
	curl -# $url -o ${SUFFIX}/$file_name
	echo "$conf_line = \"${SAVEDIR}/${SUFFIX}/${file_name}\";" >> ${PHPCONFFILELOC}
done
if [ $tarball -eq 1 ] ; then
	tar czf ${TARBALL} ${SUFFIX}
	echo "created ${TARBALL} , which also contains ${PHPCONFNAME}"
fi
cd - > /dev/null

