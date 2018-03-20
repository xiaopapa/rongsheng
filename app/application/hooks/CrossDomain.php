<?php
defined('BASEPATH') OR exit('No direct script access allowed');
//跨域处理类
class CrossDomain {
    public function init(){
        //先加载本环境的配置允许跨域url
        $url_arr = config_item('cross_url');
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        if($url_arr && in_array($origin, $url_arr)){//发送跨域允许头
            header('Access-Control-Allow-Origin:'.$origin);
            header("Access-Control-Allow-Headers : Origin, X-Requested-With, Content-Type, Accept");
            header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
            header("Access-Control-Allow-Credentials: true");
        }
    }
}