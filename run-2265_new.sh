cd /data/wwwroot/datacollect/app/2265/
count=`ps -ef |grep 2265.php |grep -v "grep" |wc -l`
if [ 0 == $count ];then
   /www/server/php/72/bin/php /data/wwwroot/datacollect/app/2265/2265.php
fi