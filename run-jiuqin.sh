cd /data/wwwroot/datacollect/app/jiuqin/
count=`ps -ef |grep jiuqin.php |grep -v "grep" |wc -l`
if [ 0 == $count ];then
   /www/server/php/72/bin/php /data/wwwroot/datacollect/app/jiuqin/jiuqin.php
fi