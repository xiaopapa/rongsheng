<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Bmax_Controller extends CI_Controller {//继承超级类，留作扩展用

    public function __construct()
    {
        parent::__construct();
    }

    //必要参数检查
    //每个参数用逗号分隔，默认检查参数是否为空，为空报错
    //如果参数为 id=2形式，那么检查的参数值必须与这个值相等
    //如果参数为 id|1_2_3形式，那么检查的参数值必须为1,2,3之中的一个
    //id! 形式，id值可以为0，但是不能不传
    protected function _check($field){
        if(!is_array($field)){
            $field = explode(',',$field);
        }
        $val = array();
        foreach($field as $row){
            $t = 0;
            $flag = true;
            if(strpos($row,'=')){//值相等
                $t = 1;
            }elseif(strpos($row,'|')){//枚举型值
                $t = 2;
            }
            if($t === 1){//检查值是否相等
                $et = explode('=',$row);
                $c = fun::get($et[0],false);
                //获取到的参数
                if($c === false || $c != $et['1']){
                    $flag = false;
                }else{
                    $val[$et[0]] = $c;
                }
            }elseif($t === 2){//检查值是否在给定的值中
                $et = explode('|',$row);
                $c = fun::get($et[0],false);
                $arr = explode('_',$et[1]);
                //获取到的参数
                if($c === false || !in_array($c,$arr)){
                    $flag = false;
                }else{
                    $val[$et[0]] = $c;
                }
            }else{
                if(strpos($row,'!')){//可以为0
                    $key = rtrim($row,'!');
                    $tmp = fun::get($key,false);
                    if($tmp === false){
                        $flag = false;
                    }else{
                        $val[$key] = $tmp;
                    }
                }else{
                    $tmp = fun::get($row,false);
                    if(!$tmp){
                        $flag = false;
                    }else{
                        $val[$row] = $tmp;
                    }
                }
            }
            if($flag === false) fun::codeBack('参数错误',100);
        }
        return $val;
    }


    //
    /**
     * 权限检测,看一下当前控制器,当前用户是否能访问
     * @param string $method
     * @return bool
     * @date
     */
    protected function _checkPer($method = ''){
        $dir = CURRENTDIR;
        $class = CURRENTCLASS;
        $method = CURRENTMETHOD;
        $str = $dir . $class . '/' . $method;
        $perFile = CACHE_DIR . 'per.php';
        $portFile = CACHE_DIR . 'port.php';
        $per = include($perFile);
        $port = include($portFile);
        $con_id = isset($port[$str]) ? $port[$str]['controller'] : 0;
        if(!$con_id) return true;//模块控制ID为0,直接返回true
        $auth = $per[$this->_uid];
        if(!$auth) fun::codeBack("你没有‘$str’的权限!");
        $auth = json_decode($auth['all_per'],true);
        if(isset($auth[0]) && $auth[0][0] == '*') return true;//全局权限
        $reFile = CACHE_DIR . '/menu/reflect.php';
        $re = include($reFile);
        $inFile = CACHE_DIR . '/menu/menu.php';
        $menu = include($inFile);
        //查找id
        $id = array();
        foreach($auth as $key => $row){
            foreach($row as $r){
                $id[] = $re[$key][$r];
            }
        }
        $all_con_id = array();
        if($id){
            foreach($id as $row){
                $tmp = $menu[$row]['child_id'];
                if($tmp){
                    $_tmp = explode(',',$tmp);
                    foreach($_tmp as $d){
                        $all_con_id[] = $d;
                    }
                }
                //把自己装上
                $all_con_id[] = $menu[$row]['id'];
            }
        }
        if(in_array($con_id,$all_con_id)){//有权限
            return true;
        }else{
            fun::codeBack("你没有‘$str’的权限!");;
        }

    }
}
