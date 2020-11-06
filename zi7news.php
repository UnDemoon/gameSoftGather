<?php
require './vendor/autoload.php';
require 'curl.php';
use phpspider\core\phpspider;
use phpspider\core\requests;
use phpspider\core\selector;

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
if( $argv[1] == 'clear' ){
    $redis->del("zi7_info");                 //清空
    // $set = $redis->smembers('zi7_info');     //查看全部
    // var_dump( $set );exit;
}
ini_set('max_execution_time', 1800);     //限制最多执行半小时
$proxy = '';//include '../../proxy.php';
fakeQuest('http://www.zi7.com', $proxy);
getData( $redis, 10, 20);

/**
 * zi7
 * @param  string  $redis  redis对象
 * @param  integer  $limit  累计多少条 推送一次
 * @param  integer $kill  本次执行拉取多少条  0为无限制  拉到页面无法访问为止
 * @return [type]          [description]
 */
function getData( $redis, $limit=0, $kill=100 ){
    //zi7
    $base_url = "http://www.zi7.com";
    $url = $base_url."/azzx/";
    $html = requests::get($url);
    //页面列表
    $selector = '//*[@class="newsList mb30"]/li[position()<=6]/a[1]/@href';
    $infos = selector::select($html, $selector);
    if( is_array( $infos ) ){
        $n = count( $infos );
    }elseif ( empty( $infos ) ) {
        var_dump('没有消息列表,请确认页面');
        return;
    }else{
        $infos = [$infos];
    }
    $data = [];
    foreach ($infos as $g) {
        $sleep = rand(2000000,8000000);
        var_dump( $g."执行开始" );
        preg_match('/\d+/',$g, $site);
        if( is_numeric( $site[0] ) ){
            $numeric = $site[0];
        }else{
            var_dump( $g."页面参数异常" );
            usleep($sleep);
            continue;
        }
        if( $redis->sismember( "zi7_info", $numeric ) ){
            var_dump($numeric.'站点已经采集,跳过');
            usleep($sleep);
            continue;
        }
        $html = requests::get($base_url.$g);
        $selector = '/html/head/title';
        $title = selector::select($html, $selector);
        if( stripos($title, '404') !== false ){
            var_dump( $g."页面404,跳过" );
            usleep($sleep);
            continue;
        }
        $token = saveInfo( $html );
        if( $token ){
            //源地址
            $token['links'] = $base_url.$g;
            $data = [$token];
            $res = sentNews( $data );
            $respone = json_decode($res, true);
            if( $respone['code'] === 0 ){
                var_dump( $g."数据发送完毕" );
                $redis->sadd('zi7_info', $numeric);
            }else{
                var_dump( $res );
            }
        }else{
            var_dump( $g."无下载地址或者数据获取失败" );
        }
        usleep($sleep);
    }
} 

function saveInfo( $contentHtml ){
    $token = array();
    //标题
    $selector = '//*[@class="artTitle elli1"]';
    $token['title'] = selector::select($contentHtml, $selector);
    //介绍
    $selector = '//*[@class="artContent"]';
    $content = strip_html_tags( 'p class="dvideo"', 'p', selector::select($contentHtml, $selector) ); 
    $content = htmlspecialchars_decode($content);
    $content = preg_replace("/<a[^>]*>(.*?)<\/a>/is", "$1", $content);
    $token['content'] = trim( $content );
    //logo
    //使用content的第一张图
    $token['content'] = str_replace( '/>', ' />', $token['content'] );
    $token['content'] = str_replace( ['ZI7下载站','zi7下载站'], '', $token['content'] );
    preg_match_all( '/<img.*?src=[\"|\']?(.*?)[\"|\']?\s.*?>/i', $token['content'], $matches );
    if( $matches[1] ){
        $token['logo'] = 'http:'.$matches[1][0];
        foreach ($matches[1] as $img) {
            if( stripos( $img, 'http' ) !== 0 ){
                $token['content'] = str_replace( $img, 'http:'.$img, $token['content'] );
            }
            
        }
    }else{
        $token['logo'] = '';
    }
    //类型
    $selector = '//*[@class="w1200 loch mt20 mb10"]/span[3]/a';
    $token['category'] = selector::select($contentHtml, $selector);
    //导航
    $selector = '//*[@class="w1200 loch mt20 mb10"]/span[2]/a';
    $token['navigation'] = selector::select($contentHtml, $selector);
    // //更新时间
    $selector = '//*[@class="artinfo"]/span[1]';
    $token['updateTime'] = selector::select($contentHtml, $selector);
    //来源
    $token['source'] = "zi7";
    //平台名称
    $token['platform'] = 'zi7';
    //seo关键字
    $selector = '/html/head/meta[2]/@content';
    $token['seoKey'] = mb_substr( selector::select($contentHtml, $selector), 0, 250);
    //seo标题
    $selector = '/html/head/title';
    $token['seoName'] = str_replace( 'zi7手游网', '', selector::select($contentHtml, $selector) ) ;
    //seo描述
    $selector = '/html/head/meta[3]/@content';
    $token['seoDescription'] = str_replace( 'zi7手游网', '', mb_substr(selector::select($contentHtml, $selector), 0, 250) );
    return $token;
}


function fakeQuest( $base_url, $proxy='' ){
    requests::set_useragent(array(
        "Mozilla/4.0 (compatible; MSIE 6.0; ) Opera/UCWEB7.0.2.37/28/",
        "Opera/9.80 (Android 3.2.1; Linux; Opera Tablet/ADR-1109081720; U; ja) Presto/2.8.149 Version/11.10",
        "Mozilla/5.0 (Android; Linux armv7l; rv:9.0) Gecko/20111216 Firefox/9.0 Fennec/9.0"
    ));

    requests::set_referer($base_url);

    // $ips = array(
    //     "192.168.0.2",
    //     "192.168.0.3",
    //     "192.168.0.4"
    // );
    // requests::set_client_ip($ips);
    if( $proxy ){
        requests::set_proxy($proxy);
    }
}

function strip_html_tags($start,$end,$str){
    $html=array();
    $html[]='/<'.$start.'.*?>[\s|\S]*?<\/'.$end.'>/';
    $html[]='/<'.$start.'.*?>/';
    $data = preg_replace( $html, '', $str );
    return $data;
}
