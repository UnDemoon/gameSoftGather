<?php
// ini_set("display_errors","On");
// error_reporting(E_ALL); 
require './vendor/autoload.php';
require 'curl.php';
// require 'mytools.php';
use phpspider\core\phpspider;
use phpspider\core\requests;
use phpspider\core\selector;


$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
if( $argv[1] == 'clear' ){
    $redis->del("1688wan");                 //清空
    $redis->del('1688wan_navi');     //查看全部
    // $set = $redis->smembers('1688wan');     //查看全部
    // var_dump( $set );exit;
}

$base_url = "https://www.1688wan.com";
fakeQuest($base_url);
var_dump( '开始执行时间:'.date('Y-m-d H:i:s') );
getCategory( $base_url, $redis, 3 );
$gameMax = getGame( $base_url, $redis, 3 );
$softMax = getSoft( $base_url, $redis, 3 );
$newsMax = getNews( $base_url, $redis );
$max = $gameMax > $softMax ? $softMax : $softMax;
getMax( $base_url, $redis, 20, $max );

/**
 * 获取导航栏目等选项
 * @param  string  $redis  redis对象
 * @param  string  $base_url  基础地址
 * @return [type]          [description]
 */
function getCategory( $base_url, $redis, $second ){
    //1688wan
    $expire = $redis->get('1688wan_navi');
    if( !empty( $expire ) && time() < $expire ){
        var_dump('导航栏目跳过');
        return;
    }
    $url = $base_url;
    $html = requests::get($url);
    //页面列表  
    $selector = '//*[@class="menu"]/div/ul/li/a';
    $navi = selector::select($html, $selector);
    $selector = '//*[@class="menu"]/div/ul/li/a/@href';
    $urls = selector::select($html, $selector);
    if( is_array( $navi ) ){
        $n = count( $navi );
    }elseif ( empty( $navi ) ) {
        if( $second <= 0 ){
            var_dump($second.'次没有导航,停止获取');
            return;
        }else{
            $second--;
            var_dump('导航列表获取失败,再来');
            sleep(4);
            getCategory( $base_url, $redis, $second );
            return;
        }
    }else{
        $navi = [$navi];
    }
    $navigation = [];
    foreach ($navi as $v) {
        $navigation [] = [
            'name' => $v,
            'source' => '1688wan',
            'url' => '',
            'cate' => [],
        ];
    }

    #   游戏
    $navigation[2]['url'] = "https://www.1688wan.com/game/type1_1.html";
    #   软件
    $navigation[3]['url'] = "https://www.1688wan.com/app/list_1.html";
    #   咨询
    $navigation[4]['url'] = "https://www.1688wan.com/news/";

    //二级目录
        $html = requests::get($navigation[2]['url']);
        $selector = '//*[@class="sear-bar f-bg"]/div/dd[position()>1]/a';
        $child_name = selector::select($html, $selector);
        $selector = '//*[@class="sear-bar f-bg"]/div/dd[position()>1]/a/@href';
        $child_url = selector::select($html, $selector);
        if( is_string($child_url) ){
            $child_url = [$child_url];
            $child_name = [$child_name];
        }elseif ( empty( $child_url ) ) {
            $child_url = [];
            $child_name = [];
        }
        $category = [];
        foreach( $child_url as $ck=>$cu ){
                $category[] = [
                    'source'    =>  '1688wan',
                    'name'      =>  $child_name[$ck],
                    'url'       =>  $base_url.$cu 
                ];
        }
        $navigation[2]['cate'] = $category;
        #   网游、单机就是找游戏
        $navigation[0]['cate'] = $category;
        $navigation[1]['cate'] = $category;

        $html = requests::get($navigation[3]['url']);
        $selector = '//*[@class="sear-bar f-bg"]/div/dd[position()>1]/a';
        $child_name = selector::select($html, $selector);
        $selector = '//*[@class="sear-bar f-bg"]/div/dd[position()>1]/a/@href';
        $child_url = selector::select($html, $selector);
        if( is_string($child_url) ){
            $child_url = [$child_url];
            $child_name = [$child_name];
        }elseif ( empty( $child_url ) ) {
            $child_url = [];
            $child_name = [];
        }
        $category = [];
        foreach( $child_url as $ck=>$cu ){
                $category[] = [
                    'source'    =>  '1688wan',
                    'name'      =>  $child_name[$ck],
                    'url'       =>  $base_url.$cu 
                ];
        }
        $navigation[3]['cate'] = $category;


        $html = requests::get($navigation[4]['url']);
        $selector = '//*[@class="side-class"]/li[position()>1]/a';
        $child_name = selector::select($html, $selector);
        $selector = '//*[@class="side-class"]/li[position()>1]/a/@href';
        $child_url = selector::select($html, $selector);
        if( is_string($child_url) ){
            $child_url = [$child_url];
            $child_name = [$child_name];
        }elseif ( empty( $child_url ) ) {
            $child_url = [];
            $child_name = [];
        }
        $category = [];
        foreach( $child_url as $ck=>$cu ){
                $category[] = [
                    'source'    =>  '1688wan',
                    'name'      =>  $child_name[$ck],
                    'url'       =>  $base_url.$cu 
                ];
        }
        $navigation[4]['cate'] = $category;

    $res = sentNavi( $navigation );
    $respone = json_decode($res, true);
    if( $respone['code'] === 0 ){
        var_dump( '导航采集完成' );
        $redis->set('1688wan_navi', time()+86400*30);
    }else{
        var_dump( $res );
    }
    return;
} 


/**
 * 安卓游戏
 * @param  string  $redis  redis对象
 * @param  string  $base_url  基础地址
 * @param  integer $second 失败重试次数次
 * @param  integer $kill  本次执行拉取多少条  0为无限制  拉到页面无法访问为止
 * @return [type]          [description]
 */
function getGame( $base_url, $redis, $second=3 ,$page_url="/game/type1_1.html"){
    //1688wan
    $url = $base_url.$page_url;
    $html = requests::get($url);
    //  下一页url
    $selector = '//*[@class="page-next"]/a/@href';
    $next_pag = selector::select($html, $selector);
    //页面列表  
    $selector = '//ul[contains(@class,"game-sear")]/li/a/@href';
    $games = selector::select($html, $selector);
    if( is_array( $games ) ){
        $n = count( $games );
    }elseif ( empty( $games ) ) {
        if( $second <= 0 ){
            var_dump($second.'次没有游戏列表,停止获取');
            return;
        }else{
            $second--;
            var_dump('游戏列表获取失败,再来');
            sleep(4);
            getGame( $base_url, $redis, $second, $page_url);
            return;
        }
    }else{
        $n = 1;
        $games = [$games];
    }
    $max = 0;
    foreach ($games as $g) {
        $sp = rand(4000000,10000000);
        var_dump( $g."执行开始" );
        preg_match('/\d+/',$g, $site);
        if( is_numeric( $site[0] ) ){
            if( $max < $site[0] ){
                $max = $site[0];
            }
            $numeric = $site[0];
        }else{
            var_dump( $g."页面参数异常" );
            usleep($sp);
            continue;
        }
        if( $redis->sismember( "1688wan", $numeric ) ){
            var_dump($numeric.'站点已经采集,跳过');
            usleep($sp);
            continue;
        }
        $html = requests::get($base_url.$g);
        for ($r=0; $r < 2; $r++) { 
            if( empty($html) ){
                var_dump($numeric.'站点数据为空,再来');
                usleep($sp);
                $html = requests::get($base_url.$g);
            }else{
                break;
            }
        }

        $selector = '/html/head/title';
        $title = selector::select($html, $selector);
        if( stripos($title, '404') !== false ){
            var_dump( $g."页面404,跳过" );
            continue;
        }
        $token = saveInfo( $html, $numeric );
        if( $token ){
            //源地址
            $token['links'] = $base_url.$g;
            $data = [$token];
            $res = sentData( $data );
            $respone = json_decode($res, true);
            if( $respone['code'] === 0 ){
                var_dump( $g.'采集完成' );
                $redis->sadd('1688wan', $numeric);
            }else{
                var_dump( $res );
            }
        }else{
            var_dump( $g."数据获取失败" );
        }
        usleep($sp);
    }

    var_dump( date('Y-m-d H:i:s')." 游戏列表提交完成" );
    if ($next_pag) {
       $max = getGame( $base_url, $redis, $second, $next_pag);
    }
    return $max;
    
} 

//网游列表
function getSoft( $base_url, $redis, $second=3 ,$page_url="/app/list_1.html"){
    //1688wan
    $url = $base_url.$page_url;
    $html = requests::get($url);
    //  下一页url
    $selector = '//*[@class="page-next"]/a/@href';
    $next_pag = selector::select($html, $selector);
    //页面列表  
    $selector = '//ul[contains(@class,"game-sear")]/li/a/@href';
    $softs = selector::select($html, $selector);
    if( is_array( $softs ) ){
        $n = count( $softs );
    }elseif ( empty( $softs ) ) {
        if( $second <= 0 ){
            var_dump($second.'次没有游戏列表,停止获取');
            return;
        }else{
            $second--;
            var_dump('游戏列表获取失败,再来');
            sleep(4);
            getSoft( $base_url, $redis, $second,  $page_url);
            return;
        }
    }else{
        $n = 1;
        $softs = [$softs];
    }
    $max = 0;
    foreach ($softs as $g) {
        $sp = rand(4000000,10000000);
        var_dump( $g."执行开始" );
        preg_match('/\d+/',$g, $site);
        if( is_numeric( $site[0] ) ){
            if( $max < $site[0] ){
                $max = $site[0];
            }
            $numeric = $site[0];
        }else{
            var_dump( $g."页面参数异常" );
            usleep($sp);
            continue;
        }
        if( $redis->sismember( "1688wan", $numeric ) ){
            var_dump($numeric.'站点已经采集,跳过');
            usleep($sp);
            continue;
        }
        $html = requests::get($base_url.$g);
        for ($r=0; $r < 2; $r++) { 
            if( empty($html) ){
                var_dump($numeric.'站点数据为空,再来');
                usleep($sp);
                $html = requests::get($base_url.$g);
            }else{
                break;
            }
        }

        $selector = '/html/head/title';
        $title = selector::select($html, $selector);
        if( stripos($title, '404') !== false ){
            var_dump( $g."页面404,跳过" );
            continue;
        }
        $token = saveInfo( $html, $numeric );
        if( $token ){
            //源地址
            $token['links'] = $base_url.$g;
            $data = [$token];
            $res = sentData( $data );
            $respone = json_decode($res, true);
            if( $respone['code'] === 0 ){
                var_dump( $g.'采集完成' );
                $redis->sadd('1688wan', $numeric);
            }else{
                var_dump( $res );
            }
        }else{
            var_dump( $g."数据获取失败" );
        }
        usleep($sp);
    }

    var_dump( date('Y-m-d H:i:s')." 软件列表提交完成" );
    if ($next_pag) {
       $max = getSoft( $base_url, $redis, $second, $next_pag);
    }
    return $max;
}


function getMax( $base_url, $redis, $kill, $max ){
    if( $max > 0 ){
        var_dump( "扩展查询开始" );
        $selector = '/html/head/title';
        $data = array();
        $cut = $kill;
        while ( true ) {
            $sp = rand(500000,2000000);
            $max++;
            $g = $base_url."/game/danji/".$max.".html";
            if( $redis->sismember( "1688wan", $max ) ){
                var_dump($max.'站点已经采集,跳过');
                usleep($sp);
                continue;
            }
            $html = requests::get($g);
            $title = selector::select($html, $selector);
            if( stripos($title, '404') !== false ){
                $cut--;
                if( $cut <= 0 ){
                    var_dump( '404超过'.$kill."次,结束采集" );
                    return;  
                }
                var_dump( $max.'无信息,跳过'.$cut.'次' );
                usleep($sp);
                continue;
            }
            $token = saveInfo( $html, $max );

            if( $token ){
                $token['links'] = $g;
                $data = [$token];
                $res = sentData( $data );
                var_dump( $max."采集完成" );
                $redis->sadd('1688wan', $max);
            }else{
                var_dump( $g."无下载地址或者数据获取失败" );
            }
            usleep($sp);
        }
    }
}


function saveInfo( $contentHtml, $id='' ){
    // writelog($contentHtml);
    $token = array();
    //标题
    $selector = '//*[@class="game_ibox"]/h1/text()';
    $title = selector::select($contentHtml, $selector);
    $token['title'] = substr($title, 0, strpos($title, 'v'));
    //版本号
    $token['version'] = substr($title, strpos($title, 'v'), strlen($title));
    //logo
    $selector = '//*/div[@class="logo_box"]/img';
    $token['logo'] = selector::select($contentHtml, $selector);
    //大小
    $selector = '//*[@class="game_ibox"]/ul/li[4]/em';
    $token['size'] = str_replace( '大小：', '', selector::select($contentHtml, $selector) );
    //类型
    $selector = '//*[@class="game_ibox"]/ul/li[1]/em/a';
    $token['softType'] = str_replace( '类型：', '', selector::select($contentHtml, $selector) );
    
    //大类型
    $selector = '//*[@class="nav-position mt10 mb10"]/a[2]';
    $genre = selector::select($contentHtml, $selector);
    if ($genre == '游戏') {
         $genre = "找游戏/".$token['softType'];
         $token['genre'] = 1;
    }elseif ($genre == '安卓软件') {
        $genre = "软件/".$token['softType'];
        $token['genre'] = 3;
    }
    $token['navigation'] = $genre;
    // if( $genre == '安卓游戏' ){
    //     $token['genre'] = 1;
    // }else{
    //     $token['genre'] = 3;
    // }
    //更新时间
    $selector = '//*[@class="game_ibox"]/ul/li[2]/em';
    $token['updateTime'] = trim( selector::select($contentHtml, $selector) );
    if( time() - strtotime( $token['updateTime'] ) > 604800 ){
        return false;
    }
    //软件授权
    $token['authorize'] = '';
    //官网地址
    $token['website'] = '';
    //好评(ajax的)
    $token['praise'] = 0;
    //差评(ajax的)
    $token['poor'] = 0;
    //来源
    $token['source'] = "1688wan";
    //平台名称
    $token['platform'] = '1688玩';
    //语言
    $token['language'] = '';
    //seo关键字
    $selector = '/html/head/meta[2]/@content';
    $token['seoKey'] = mb_substr( selector::select($contentHtml, $selector), 0, 250);
    //seo标题
    $selector = '/html/head/title';
    $token['seoName'] = str_replace( '1688wan手游网', '', selector::select($contentHtml, $selector) ) ;
    //seo描述
    $selector = '/html/head/meta[3]/@content';
    $token['seoDescription'] = str_replace( '1688wan手游网', '', mb_substr(selector::select($contentHtml, $selector), 0, 250) );
    $sheild = ['彩票','体彩','福彩','福利彩票','体育彩票','竞彩','vpn','网络加速','加速器','科学上网','翻墙','梯子'];
    foreach ($sheild as $v) {
        if( strstr($token['seoKey'], $v) ){
            var_dump( $id.'和谐app,舍弃' );
            return false;
        }
        if( strstr($token['seoName'], $v) ){
            var_dump( $id.'和谐app,舍弃' );
            return false;
        }
        if( strstr($token['seoDescription'], $v) ){
            var_dump( $id.'和谐app,舍弃' );
            return false;
        }
    }
    //图片
    $selector = '//*[@id="textcon"]/p/img';
    $pics = selector::select($contentHtml, $selector);
    if( is_array( $pics ) ){
        $doll = '';
        foreach( $pics as &$ps ){
            $doll .= $ps.',';
        }
        $token['imgs'] = rtrim( $doll, ',' );
    }elseif ( is_string( $pics ) ) {
        $token['imgs'] = $pics;
    }else{
        $token['imgs'] = '';
    }
    //标签
    $token['tags'] = '';
    
    //介绍
    $selector = '//*[@id="textcon"]';
    $introduction = trim( strip_html_tags( 'h1', 'h1', selector::select($contentHtml, $selector) ) ); 
    $introduction = str_replace( '/>', ' />', $introduction );
    // $introduction = str_replace( ['ZI7下载站','1688wan下载站'], '', $introduction );
    preg_match_all( '/<img.*?src=[\"|\']?(.*?)[\"|\']?\s.*?>/i', $introduction, $matches );
    if( $matches[1] ){
        foreach ($matches[1] as $img) {
            if( stripos( $img, 'http' ) !== 0 ){
                $introduction = str_replace( $img, 'http:'.$img, $introduction );
            }
        }
    }
    $introduction = preg_replace("/<a[^>]*>(.*?)<\/a>/is", "$1", $introduction);
    $token['introduction'] = $introduction;
    //应用平台
    $token['application'] = 'android';
    $token['downloads'] = '';
    // print_r($token);
    // exit();
    return $token;
}


function getNews( $base_url, $redis, $limit=0, $kill=100, $page_url="/webnews/300_1.html"){
    $url = $base_url.$page_url;
    $html = requests::get($url);
    //  下一页url
    $selector = '//*[@class="page-next"]/a/@href';
    $next_pag = selector::select($html, $selector);
    //页面列表
    $selector = '//*[@class="l-content l"]/ul/li/div/div/a[1]/@href';
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
        if( $redis->sismember( "1688wan_news", $numeric ) ){
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
        $token = saveNews( $html );
        if( $token ){
            //源地址
            $token['links'] = $base_url.$g;
            $data = [$token];
            $res = sentNews( $data );
            $respone = json_decode($res, true);
            if( $respone['code'] === 0 ){
                var_dump( $g."数据发送完毕" );
                $redis->sadd('1688wan_news', $numeric);
            }else{
                var_dump( $res );
            }
        }else{
            var_dump( $g."无下载地址或者数据获取失败" );
        }
        usleep($sleep);
    }
}

function saveNews( $contentHtml ){
    $token = array();
    //标题
    $selector = '//*[@class="news-tit-con dis-tb mb20"]/div/h1';
    $token['title'] = selector::select($contentHtml, $selector);
    //介绍
    $selector = '//*[@id="wz-content"]';
    $content = strip_html_tags( 'p class="dvideo"', 'p', selector::select($contentHtml, $selector) ); 
    $content = htmlspecialchars_decode($content);
    $content = preg_replace("/<a[^>]*>(.*?)<\/a>/is", "$1", $content);
    $token['content'] = trim( $content );
    //logo
    //使用content的第一张图
    $token['content'] = str_replace( '/>', ' />', $token['content'] );
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
    $selector = '//*[@class="nav-position mb10"]/a[3]';
    $token['category'] = selector::select($contentHtml, $selector);
    //导航
    // $selector = '//*[@class="nav-position mb10"]/a[2]';
    // $token['navigation'] = selector::select($contentHtml, $selector);
    $token['navigation'] = "看资讯/".$token['category']; 
    // //更新时间
    $selector = '//*[@class="art-info mt10 mb10"]/text()';
    $temp = selector::select($contentHtml, $selector);
    if (is_array($temp)) {
        $temp = $temp[0];
    }
    $temp = str_replace( '编辑：', '', str_replace( '时间：', '', $temp ));
    $token['updateTime'] = trim($temp);
    //来源
    $token['source'] = "1688wan";
    //平台名称
    $token['platform'] = '1688玩';
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
        // "Mozilla/4.0 (compatible; MSIE 6.0; ) Opera/UCWEB7.0.2.37/28/",
        // "Opera/9.80 (Android 3.2.1; Linux; Opera Tablet/ADR-1109081720; U; ja) Presto/2.8.149 Version/11.10",
        // "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.88 Safari/537.36"
        "Mozilla/5.0 (compatible; Baiduspider/2.0;+http://www.baidu.com/search/spider.html）"
    ));

    requests::set_referer($base_url);

    // requests::set_header("accept-encoding", "gzip, deflate, br");
    requests::set_header("cache-control", "no-cache");
    requests::set_header("accept", "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9");
    if( $proxy ){
        requests::set_proxySockt();
        requests::set_proxy('socks5://121.41.131.223:6002');
    }
}

function strip_html_tags($start,$end,$str){
    $html=array();
    $html[]='/<'.$start.'.*?>[\s|\S]*?<\/'.$end.'>/';
    $html[]='/<'.$start.'.*?>/';
    $data = preg_replace( $html, '', $str );
    return $data;
}
