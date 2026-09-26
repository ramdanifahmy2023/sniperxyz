#!/bin/sh
set -e

cd "/Volumes/DATA KERJA /Project Dev/sniper/xyzsniper"
/Users/fahra/.local/bin/xyzsniper-bootstrap-db.sh
exec /Users/fahra/.local/bin/php -S 127.0.0.1:8000 -t public router.php
