<?php

function sentData( $data ){

    // $url = "http://api.fbfzj.com/core/soft.php?do=add";
    $url = 'http://collectapi.moyndev.com/core/soft.php?do=add';
    $data = array( "appid" => 'wx9d59dc8f27558720', 'data'=>json_encode($data) );
    $appkey = "f3e1ff5686c1db8eb8e5ccec9f6817a3";
    ksort( $data );
    // var_dump( $data );
    $param = '';
    foreach ($data as $k => $v) {
        $param .= $k."=".$v."&";
    }
    $data['sign'] = md5( rtrim( $param, "&" ).$appkey );
    $header = array();
    // $header[] = 'Content-type: application/json';
    // $header[] = 'Authorization: Bearer '.$token;
    return https( $url, $data, $header );
}

function sentNews( $data ){
    //$url = "http://api.fbfzj.com/core/information.php?do=add";
    $url = 'http://collectapi.moyndev.com/core/information.php?do=add';
    $data = array( "appid" => 'wx9d59dc8f27558720', 'data'=>json_encode($data) );
    $appkey = "f3e1ff5686c1db8eb8e5ccec9f6817a3";
    ksort( $data );
    // var_dump( $data );
    $param = '';
    foreach ($data as $k => $v) {
        $param .= $k."=".$v."&";
    }
    $data['sign'] = md5( rtrim( $param, "&" ).$appkey );
    $header = array();
    // $header[] = 'Content-type: application/json';
    // $header[] = 'Authorization: Bearer '.$token;
    return https( $url, $data, $header );
}


function sentNavi( $data ){
    //$url = "http://api.fbfzj.com/core/soft.php?do=navi";
    $url = 'http://collectapi.moyndev.com/core/soft.php?do=navi';
    $data = array( "appid" => 'wx9d59dc8f27558720', 'data'=>json_encode($data) );
    $appkey = "f3e1ff5686c1db8eb8e5ccec9f6817a3";
    ksort( $data );
    // var_dump( $data );
    $param = '';
    foreach ($data as $k => $v) {
        $param .= $k."=".$v."&";
    }
    $data['sign'] = md5( rtrim( $param, "&" ).$appkey );
    $header = array();
    // $header[] = 'Content-type: application/json';
    // $header[] = 'Authorization: Bearer '.$token;
    return https( $url, $data, $header );
}

function https( $url, $post_data=[], $header="" ){
    //初始化
    $curl = curl_init();
    //设置抓取的url
    curl_setopt($curl, CURLOPT_URL, $url);
    //设置头文件的信息作为数据流输出
    curl_setopt($curl, CURLOPT_HEADER, 0);
    //设置获取的信息以文件流的形式返回，而不是直接输出。
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    if( $header ){
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    }
    if( $post_data ){
        //设置post方式提交
        curl_setopt($curl, CURLOPT_POST, 1);
        // curl_setopt($curl, CURLOPT_CUSTOMREQUEST,"PUT"); //设置请求方式
        // $data = json_encode($post_data);
        //设置post数据
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
    }
    //执行命令
    $data = curl_exec($curl);
    //关闭URL请求
    curl_close($curl);
    //显示获得的数据
    return $data;
}


