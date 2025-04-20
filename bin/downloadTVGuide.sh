#!/bin/bash

set -e
cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1

PHP=/usr/bin/php
PHP_SCRIPT=./downloadTVGuide.php
TARGET_DIR=/home/gb/bin/xmltv

# Local Digital Broadcasts, Montréal
$PHP ${PHP_SCRIPT} > ${TARGET_DIR}/xmltv-A_00132-temp.xml 2> /dev/null
if [ "$(stat -c %s ${TARGET_DIR}/xmltv-A_00132-temp.xml)" -gt 500000 ]; then
    mv ${TARGET_DIR}/xmltv-A_00132-temp.xml ${TARGET_DIR}/xmltv-A_00132.xml
    sed -i'' -e 's/CBFTDT/CBFT-DT/' ${TARGET_DIR}/xmltv-A_00132.xml
    sed -i'' -e 's/CBMTDT/CBMT-DT/' ${TARGET_DIR}/xmltv-A_00132.xml
    sed -i'' -e 's/CFTMDT/CFTM-HD/' ${TARGET_DIR}/xmltv-A_00132.xml
    sed -i'' -e 's/CFCFDT/CFCF/' ${TARGET_DIR}/xmltv-A_00132.xml
    sed -i'' -e 's/CKMIDT1/CKMI-HD/' ${TARGET_DIR}/xmltv-A_00132.xml
    sed -i'' -e 's/CIVMDT/CIVM-HD/' ${TARGET_DIR}/xmltv-A_00132.xml
    sed -i'' -e 's/CFJPDT/CFJP-DT/' ${TARGET_DIR}/xmltv-A_00132.xml
    sed -i'' -e 's/CJNTDT/CNJT/' ${TARGET_DIR}/xmltv-A_00132.xml
else
    echo "Resulting .xmltv file is too small:"
    ls -l ${TARGET_DIR}/xmltv-A_00132-temp.xml
fi
