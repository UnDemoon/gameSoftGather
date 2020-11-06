<?php

function writelog($loginfo){
    $file='log/runlog_'.date('y-m-d').'.log';
    if(!is_file($file)){
        file_put_contents($file,'',FILE_APPEND);//如果文件不存在，则创建一个新文件。
    }
    $contents=$loginfo."\r\n";
    file_put_contents($file, $contents,FILE_APPEND);
}

?>