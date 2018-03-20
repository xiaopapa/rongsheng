<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class COMM{
    //获取参数
    public static function get($s, $def = ''){
        $s = isset($_POST[$s]) ? $_POST[$s] : (isset($_GET[$s]) ? $_GET[$s] : '');
        $s = isset($s) && $s !== '' ? $s : $def;
        return $s;
    }
}