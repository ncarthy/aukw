#!/bin/bash
#
# db_clone.sh
#
# !! This will completely destroy and replace the development database !!
#
# This script assumes you have root access to the local mysql server
#
# Script Actions:
# 1. Download a backup copy of the dailytakings database from the AllHost server
# 2. Store a copy of the exisiting database's routines
# 3. Overwrite the existing member dB on this vm with the new data
# 4. Re-add the routines that were stored in step 2.
# 5. Echo to user if all went ok
#
# Relies on PHP script login.php
#
# Must provide database passwords on lines 33&36 before using
#
# Needs write access to OUTPUT_DIR and /tmp

CURL="/usr/bin/curl -s"
COOKIE="/root/aukw/cookies.txt"
SITE_URI="https://web0.ahcloud.co.uk:2083"
LOGIN_SCRIPT="/root/aukw/login.php"
DB_REMOTE=aukworgu_dailytakings
DB_LOCAL=aukworgu_dailytakings
OUTPUT_DIR=/var/spool/aukw_backup/
ROUTINES=routines

MYSQL=/usr/bin/mysql
ROOT_USER=root
ROOT_PWORD=<<<PLEASE_PROVIDE>>>

DB_USER=aukworgu_shop
DB_PWORD=<<<PLEASE_PROVIDE>>>
TMP_FILE=$(mktemp /tmp/AUKW.XXXXXXXXX)
SKIP=NO
APPLY=YES

for i in "$@"
do
case $i in
    -s*|--skip*)
    SKIP=YES
    shift # past argument=value
    ;;
   -v*|--debug*)
    set -x
    shift
    ;;
    --default)
    DEFAULT=YES
    shift # past argument with no value
    ;;
    *)
          # unknown option
    ;;
esac
done

echo "Skip downloading from AllHost = ${SKIP}, Apply routines.sql to new DB = ${APPLY}"

TOKEN=$(php ${LOGIN_SCRIPT})

if [[ $SKIP = "NO" ]]
then
	# Download database file
	echo "Downloading daily takings DB SQL file"
	echo "from " ${SITE_URI}${TOKEN}/getsqlbackup/${DB_REMOTE}.sql.gz
	echo "to " ${OUTPUT_DIR}${DB_LOCAL}
	${CURL} -o ${OUTPUT_DIR}${DB_LOCAL}.sql.gz -b ${COOKIE} ${SITE_URI}${TOKEN}/getsqlbackup/${DB_REMOTE}.sql.gz

	if [ ! -f ${OUTPUT_DIR}${DB_LOCAL}.sql.gz  ]
	then
	    echo There was a problem downloading the SQL file for daily takings DB
	    return 1
	else
	    echo SQL file is downloaded for DailyTakings dB
	fi
else
	echo "Not downloading SQL file"
fi

# Save Routines
# Shared hosting provider does not include routines in MySQL backup.
mysqldump -u ${DB_USER} --password=${DB_PWORD} -n -d -t --routines ${DB_LOCAL} > ${OUTPUT_DIR}${ROUTINES}.sql
# Add extra line at line 8 to remove existing trigger
sed -i '8i DROP TRIGGER IF EXISTS `tr_aft_del_allocation`;' ${OUTPUT_DIR}${ROUTINES}.sql


if [ ! -f ${OUTPUT_DIR}${ROUTINES}.sql  ]
then
    echo There was a problem dumping the routines from the database. Use the .bak file?
    return 1
else
    echo Routines and Triggers dumped from old database.
fi


# MySQL database creation
echo "Creating database (${DB_LOCAL})"
echo "DROP TRIGGER IF EXISTS tr_aft_del_allocation;" > ${TMP_FILE}
echo "DROP DATABASE IF EXISTS ${DB_LOCAL};" > ${TMP_FILE}
echo "CREATE DATABASE ${DB_LOCAL};" >> ${TMP_FILE}
echo "GRANT USAGE ON *.* TO '"${DB_USER}"'@'%' IDENTIFIED BY '"${DB_PWORD}"';" >> ${TMP_FILE}
echo "GRANT USAGE ON *.* TO 'aukworgu'@'localhost' IDENTIFIED BY '"${DB_PWORD}"';" >> ${TMP_FILE}
echo "GRANT ALL ON ${DB_LOCAL}.* TO '${DB_USER}'@'%' IDENTIFIED BY '${DB_PWORD}';" >> ${TMP_FILE}
echo "GRANT SELECT ON mysql.proc TO '${DB_USER}'@'%' IDENTIFIED BY '${DB_PWORD}';" >> ${TMP_FILE}
echo "FLUSH PRIVILEGES;" >> ${TMP_FILE}
mysql -u ${ROOT_USER} --password=${ROOT_PWORD} -D mysql < ${TMP_FILE}
[ -n ${TMP_FILE} ] && rm -rf ${TMP_FILE}

echo "Unzipping downloaded file"
echo "Removing first line to avoid 'sandbox' bug"
# Further information at https://mariadb.org/mariadb-dump-file-compatibility-change/
gunzip -c ${OUTPUT_DIR}${DB_LOCAL}.sql.gz | tail -n +2 > ${OUTPUT_DIR}${DB_LOCAL}.sql

echo "Populating local database"
mysql -u ${ROOT_USER} --password=${ROOT_PWORD} -D ${DB_LOCAL} < ${OUTPUT_DIR}${DB_LOCAL}.sql
# replace old files
# '-f' option overwrites any existing file
gzip -f ${OUTPUT_DIR}${DB_LOCAL}.sql

# Replace DEFINER with correct user name, sometimes the username was cut-off at the underscore
# '-i' mean replace in-place
# '-e' means use regex
# \b means start of word (word boundary)
sed -i -e "s/\bDEFINER[^ ]*/DEFINER='${DB_USER}'@'%'/" ${OUTPUT_DIR}${ROUTINES}.sql

echo "Adding back routines and triggers"
mysql -u ${ROOT_USER} --password=${ROOT_PWORD} -D ${DB_LOCAL} < ${OUTPUT_DIR}${ROUTINES}.sql


