<?php
require './vendor/autoload.php';
require 'curl.php';
use phpspider\core\phpspider;
use phpspider\core\requests;
use phpspider\core\selector;


echo phpinfo();
exit;

// $url = "https://www.hackhome.com/XiaZai/SoftView_734613.html";
$url = "http://www.k73.com/down/android/471786.html";
$html = requests::get($url);
$selector = '/html/head/title';
$live = selector::select($html, $selector);
// $p = selector::select($contentHtml, $selector);
// $p = strip_html_tags( ['div class="textdownload"','\/div', 'p align="center"', '\/p', 'a', '\/a', 'object'], $p );
// $k = stripos( $p, "<h3>人气游戏推荐</h3>" );
// if( $k === false ){
// 	$k = stripos( $p, "<h3>推荐下载</h3>" );
// }
// if( $k === false ){
// 	$k = stripos( $p, "<h3>推荐手游下载</h3>" );
// }
// if( $k === false ){
// 	$k = stripos( $p, "<h3>人气推荐</h3>" );
// }
// if( $k === false ){
// 	$k = stripos( $p, "<h3>人气手游推荐</h3>" );
// }
// if( $k > 0 ){
// 	$p = substr( $p, 0, $k );
// }
var_dump( $live );


function strip_html_tags($tags,$str){
    $html=array();
    foreach ($tags as $tag) {
        $html[]='/<'.$tag.'.*?>[\s|\S]*?<\/'.$tag.'>/';
        $html[]='/<'.$tag.'.*?>/';
    }
    $data=preg_replace($html,'',$str);
    return $data;
}