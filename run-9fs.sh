cd /data/wwwroot/datacollect/app/9fs/
count=`ps -ef |grep 9fs.php |grep -v "grep" |wc -l`
if [ 0 == $count ];then
   /www/server/php/72/bin/php /data/wwwroot/datacollect/app/9fs/9fs.php
fi