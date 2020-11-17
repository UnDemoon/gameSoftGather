cd /data/wwwroot/datacollect/app/289/
count=`ps -ef |grep 289.php |grep -v "grep" |wc -l`
if [ 0 == $count ];then
   /www/server/php/72/bin/php /data/wwwroot/datacollect/app/289/289.php
fi