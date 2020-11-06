cd /www/wwwroot/datacollect/app/zi7/
count=`ps -ef |grep zi7.php |grep -v "grep" |wc -l`
if [ 0 == $count ];then
   /www/server/php/72/bin/php /www/wwwroot/datacollect/app/zi7/zi7.php
fi
countx=`ps -ef |grep zi7news.php |grep -v "grep" |wc -l`
if [ 0 == $countx ];then
    /www/server/php/72/bin/php /www/wwwroot/datacollect/app/zi7/zi7news.php
fi
