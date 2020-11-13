cd /data/wwwroot/datacollect/app/11773_new/
count=`ps -ef |grep 11773_new.php |grep -v "grep" |wc -l`
if [ 0 == $count ];then
   /www/server/php/72/bin/php /data/wwwroot/datacollect/app/11773_new/11773_new.php
fi