<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Class CS   登录信息检查类
 *
 *  同构CI钩子，所有API请求都经过这个类来检查是否登录
 *  如果某些类访问不需要登录（例如 登录Login类，上传upload类)，
 *  在类属性添 $pLogin 加上这个类名即可跳
 *
 * @author harvey
 * @Date: ${DAY}
 * @license  http://www.boyaa.com/
 */
class CS extends CI_Controller{
    public static $_SESSION = array();//
    public static $admin = array();
    //这里设置需要跳过登录访问的类
    private $pLogin = array('Login');

    /**
     * 根据session来检查是否登录，跳过不需要登录的类
     * @date
     */
    public function init(){
        //session_name 自定义，来当做登录信息检查
        session_name(SESSION_NAME);
        session_start();
        define('PHPSESSID', session_id());
        if(defined('CURRENTCLASS') && in_array(CURRENTCLASS,$this->pLogin)){//登录跳过类检查
            session_commit();
            return;
        }

        //session保存在静态变量
        self::$_SESSION = $_SESSION;
        self::$admin['id'] = $_SESSION['uid'];
        session_commit();//结束session，解决session_start卡死的情况

        if(!isset($_SESSION['login']) || empty($_SESSION['login'])){//SESSION里面没有用户（新session或者信息丢失）
            //输出没登录信息,输出一个"notLogin"信息,code 码为 -1
            $url = "http://rs-vm.oa.com/Login.html";
//            $url = "http://www.rongsheng.com/Login.html";
            header("Location: ". $url);
            fun::codeBack('notLogin',0,'-1');
        }else{//刷新cookie时间
            fun::cookie(SESSION_NAME, '', NOW + 3600);
        }
    }

}