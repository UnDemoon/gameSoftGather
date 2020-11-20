cd /data/wwwroot/datacollect/app/doyo/
count=`ps -ef |grep doyo.php |grep -v "grep" |wc -l`
if [ 0 == $count ];then
   /www/server/php/72/bin/php /data/wwwroot/datacollect/app/doyo/doyo.php
fi