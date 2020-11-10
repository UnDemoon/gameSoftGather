cd /data/wwwroot/datacollect/app/1688wan_new/
count=`ps -ef |grep 1688wan_new.php |grep -v "grep" |wc -l`
if [ 0 == $count ];then
   /www/server/php/72/bin/php /data/wwwroot/datacollect/app/1688wan_new/1688wan_new.php
fi