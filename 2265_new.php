<?php
require './vendor/autoload.php';
require 'curl.php';
// require 'mytools.php';
use phpspider\core\phpspider;
use phpspider\core\requests;
use phpspider\core\selector;

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
if( $argv[1] == 'clear' ){
    $redis->del("2265");                 //清空
    $redis->del('2265_navi');     //查看全部
    // $set = $redis->smembers('2265');     //查看全部
    // var_dump( $set );exit;
}
$newNav = [];
$base_url = "http://www.2265.com";
fakeQuest($base_url);
var_dump( '开始执行时间:'.date('Y-m-d H:i:s') );
getCategory( $base_url, $redis, 3 );
$gameMax = getGameSoft( $base_url, $redis, 3 );
$newsMax = getNews( $base_url, $redis );

/**
 * 获取导航栏目等选项
 * @param  string  $redis  redis对象
 * @param  string  $base_url  基础地址
 * @return [type]          [description]
 */
function getCategory( $base_url, $redis, $second ){
    //2265
    $expire = $redis->get('2265_navi');
    if( !empty( $expire ) && time() < $expire ){
        var_dump('导航栏目跳过');
        return;
    }
    $url = $base_url;
    $html = requests::get($url);
    //页面列表  
    $selector = '//*[@id="header"]/dd/p/a';
    $navi = selector::select($html, $selector);
    $selector = '//*[@id="header"]/dd/p/a/@href';
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
    foreach ($navi as $k => $v) {
        $navigation [] = [
            'name' => $v,
            'source' => '2265',
            'url' => '',
            'cate' => [],
        ];
    }

    #   安卓软件
    $navigation[1]['url'] = '/soft/r_90_1.html';
    #   安卓网游
    $navigation[2]['url'] = '/game/r_89_1.html';
    #   安卓单机
    $navigation[3]['url'] = '/game/r_288_1.html';

    $category = [];
    #   循环导航 查找二级菜单 循环顺序必须为正序0~N
    foreach ($navigation as $idx => $nav) {
        #   空地址跳过
        if (empty($nav['url'])) {
            continue;
        }
        $html = requests::get($base_url.$nav['url']);
        $selector = '//*[@class="clearfix"]/a[position()>1]/text()';
        $child_name = selector::select($html, $selector);
        $selector = '//*[@class="clearfix"]/a[position()>1]/@href';
        $child_url = selector::select($html, $selector);
        if( is_string($child_url) ){
            $child_url = [$child_url];
            $child_name = [$child_name];
        }elseif ( empty( $child_url ) ) {
            $child_url = [];
            $child_name = [];
        }
        foreach( $child_url as $ck=>$cu ){
                $category[] = [
                    'source'    =>  '2265',
                    'name'      =>  $child_name[$ck],
                    'url'       =>  $base_url.$cu 
                ];
        }
        $navigation[$idx]['cate'] = $category;
        $navigation[$idx]['url'] = $nav['url'];
    }

    $navigation[4]['cate'] = [
            [
                    'source'    =>  '2265',
                    'name'      =>  '新闻',
                    'url'       =>  'http://www.2265.com/article/s_302_1.html'
                ],[
                    'source'    =>  '2265',
                    'name'      =>  '攻略',
                    'url'       =>  'http://www.2265.com/article/s_303_1.html'
                ],[
                    'source'    =>  '2265',
                    'name'      =>  '教程',
                    'url'       =>  'http://www.2265.com/article/s_304_1.html'
                ],[
                    'source'    =>  '2265',
                    'name'      =>  '测评',
                    'url'       =>  'http://www.2265.com/article/s_305_1.html'
                ],
    ];


    $res = sentNavi( $navigation );
    #   缓存一下新闻栏目
    $redis->set('2265_news_nav', json_encode($navigation[4]['cate']));
    $respone = json_decode($res, true);
    if( $respone['code'] === 0 ){
        var_dump( '导航采集完成' );
        $redis->set('2265_navi', time()+86400*30);
    }else{
        var_dump( $res );
    }
    return;
}


/**
 * 获取游戏和软件列表
 */

function getGameSoft( $base_url, $redis, $second=3){
    #   url数组
    $urls = [
        '/soft/r_90_1.html',  // 安卓软件
        '/game/r_89_1.html',  // 安卓网游
        '/game/r_288_1.html', // 安卓单机
    ];
    foreach ($urls as $url) {
        getOnePage( $base_url, $redis, $second ,$url);
    }
}


/**
 * 获取单页
 * @param  string  $redis  redis对象
 * @param  string  $base_url  基础地址
 * @param  integer $second 失败重试次数次
 * @param  integer $kill  本次执行拉取多少条  0为无限制  拉到页面无法访问为止
 * @return [type]          [description]
 */
function getOnePage( $base_url, $redis, $second=3 ,$page_url=""){
    //2265
    $url = $base_url.$page_url;
    $html = requests::get($url);
    //  下一页url
    $selector = '//*[@class="tsp_next"]/@href';
    $next_pag = selector::select($html, $selector);

    //页面列表  
    $selector = '//*[@id="listCont"]/dd/p/a[1]/@href';
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
        $numeric = explode('/', $g)[2];   // /game/cyshcryx5/ 拆分取 cyshcryx5

        if( $redis->sismember( "2265", $numeric ) ){
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
                $redis->sadd('2265', $numeric);
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


function saveInfo( $contentHtml, $id='', $type='1' ){    # type 1游戏 2软件
    $token = array();
    //标题
    $selector = '//*[@id="dinfo"]/h1[1]/text()';
    $token['title'] = selector::select($contentHtml, $selector);
    //版本号
    $selector = '//*[@id="dinfo"]//i[@class="softver"]';
    $version = selector::select($contentHtml, $selector);
    $version = str_replace( '版本：', '', $version);
    $token['version'] = str_replace( ' 安卓版', '', $version);
    //logo
    $selector = '//*[@id="dico"]/img';
    $token['logo'] = selector::select($contentHtml, $selector);
    //大小
    $selector = '//*[@id="dinfo"]//*[@class="base"]/i[3]';
    $token['size'] = str_replace( '大小：', '', selector::select($contentHtml, $selector) );
    //类型
    $selector = '//*[@id="dinfo"]//*[@class="base"]/i[1]';
    $softType = str_replace( '类型：', '', selector::select($contentHtml, $selector) );
    $token['softType'] = explode(' ', $softType)[1];
    
    //大类型
    $selector = '//*[@id="fast-nav"]/a[2]';
    $genre = selector::select($contentHtml, $selector);
    if ($genre == '安卓软件') {
         $token['genre'] = 3;
    }elseif ($genre == '安卓单机') {
        $token['genre'] = 2;
    }else{
        $token['genre'] = 1;
    }
    $token['navigation'] = $genre."/".$token['softType'];
    //更新时间
    $selector = '//*[@id="dinfo"]//*[@class="base"]/i[4]';
    $token['updateTime'] = str_replace( '更新：', '', selector::select($contentHtml, $selector) );

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
    $token['source'] = "2265";
    //平台名称
    $token['platform'] = '2265安卓网';
    //语言
    $token['language'] = '';
    //seo关键字
    $selector = '//meta[@name="keywords"]/@content';
    $token['seoKey'] = selector::select($contentHtml, $selector);
    //seo标题
    $selector = '/html/head/title';
    $token['seoName'] = str_replace( '2265安卓网', '', selector::select($contentHtml, $selector) ) ;
    //seo描述
    $selector = '//meta[@name="description"]/@content';
    $token['seoDescription'] = str_replace( '2265安卓网', '', mb_substr(selector::select($contentHtml, $selector), 0, 250) );
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
    $selector = '//*[@id="focus"]//img';
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
    // $selector = '//*[@class="newifcens"]/ul/li[3]/div/a/text()';
    // $tags = selector::select($contentHtml, $selector);
    $token['tags'] = '';
    
    //介绍
    $selector = '//*[@id="soft-info"]';
    $introduction = trim( strip_html_tags( 'h1', 'h1', selector::select($contentHtml, $selector) ) ); 
    $introduction = str_replace( '/>', ' />', $introduction );
    // $introduction = str_replace( ['ZI7下载站','2265下载站'], '', $introduction );
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

    return $token;
}


#   获取新闻
function getNews( $base_url, $redis, $limit=0, $kill=100, $page_url=""){


     $newNav =    $redis->get('2265_news_nav');
     $newNav = $newNav ? json_decode($newNav, true) : [];
    foreach ($newNav as $cate) {
        $start_url = explode('/', $cate['url'])[3].'/'.explode('/', $cate['url'])[4];
        getOneNews( $base_url, $redis, $limit=0, $kill=100, '/'.$start_url);
    }
}

function getOneNews( $base_url, $redis, $limit=0, $kill=100, $page_url=""){
    if (empty($page_url)) {
        var_dump('起始地址异常');
        return;
    }
    $url = $base_url.$page_url;
    $html = requests::get($url);

    //  下一页url
    $selector = '//*[@class="tsp_next"]/@href';
    $next_pag = selector::select($html, $selector);
    
    //页面列表
    $selector = '//*[@id="wzl"]/ul/li/b/a[1]/@href';
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
        if( $redis->sismember( "2265_news", $numeric ) ){
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
                $redis->sadd('2265_news', $numeric);
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
    $selector = '//*[@id="ncont"]/dt/h1';
    $token['title'] = selector::select($contentHtml, $selector);
    //介绍
    $selector = '//*[@id="ncont"]/dd';
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
    $selector = '//*[@id="fnav"]/a[2]';
    $token['category'] = selector::select($contentHtml, $selector);
    //导航
    // $selector = '//*[@class="nav-position mb10"]/a[2]';
    // $token['navigation'] = selector::select($contentHtml, $selector);
    $token['navigation'] = "资讯/".$token['category']; 
    // //更新时间
    $selector = '//*[@id="ncont"]/dt/p/span[1]';
    $temp = selector::select($contentHtml, $selector);
    if (is_array($temp)) {
        $temp = $temp[0];
    }
    $temp = str_replace( '编辑：', '', str_replace( '时间：', '', $temp ));
    $token['updateTime'] = trim($temp);
    //来源
    $token['source'] = "2265";
    //平台名称
    $token['platform'] = '2265安卓网';
    //seo关键字
    $selector = '//meta[@name="keywords"]/@content';
    $token['seoKey'] = mb_substr( selector::select($contentHtml, $selector), 0, 250);
    //seo标题
    $selector = '/html/head/title';
    $token['seoName'] = str_replace( '2265安卓网', '', selector::select($contentHtml, $selector) ) ;
    //seo描述
    $selector = '//meta[@name="description"]/@content';
    $token['seoDescription'] = str_replace( '2265安卓网', '', mb_substr(selector::select($contentHtml, $selector), 0, 250) );

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
