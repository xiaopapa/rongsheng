<?php
/**
 * Copyright (c) boyaa.com
 * Developer - 安凌志
 * Last modify - 2015.03.11
 * Info - 输入配置文件类
 */

class CI_OP{
    public $zip = false;
    //输入PHP define格式文件
    public function php_define($file, $data, $desc = ''){
        if(!is_array($data)) return $file;
        $temp = $this->phpbegin($desc);
        foreach($data as $k => $v){
            $k = addslashes($k);
            $v = $v && is_string($v) ? addslashes($v) : $v;
            $temp .= "define('". $k ."', '". $v ."');\r\n";
        }
        $temp = trim($temp);
        file::put($file, $temp);
        return $temp;
    }

    //输出到JS
    public function js($file, $name, $data){
        if(!is_array($data)) cms::callback('JS输出数据格式错误！');
        $name = str_replace('=', '', $name);
        file::put($file, $name.'='.cms::json($data).';');
    }

    //输出PHP return格式文件
    public function php($file, $data, $desc = '', $name = '', $cknum = true){
        if(!is_array($data)) return $file;
        if($this->zip && $data){
            $this->zip = false;
            $data = $this->zipArr($data);
        }
        $this->_cknum = $cknum;
        if(!$name) $name = 'return ';
        $data = array($name => $data);
        return $this->phpArr($file, $data, $desc);
    }
    public function phpArr($f, $arr, $desc = ''){
        if($desc) $desc = ",". $desc;
        $s = $this->phpbegin($desc);
        foreach ($arr as $name => $rs){
            $s .= $name . $this->arrToStr($rs) .";\r\n";
        }
        $s = trim($s, "\r\n");
        file::put($f, $s);
        return $s;
    }

    /**
     * 数组字符化输出，输入等效于return var_export($arr,true);
     * $zip为true表示紧密输出，false表示格式化输出
     */
    public function arrToStr($arr, $zip = false){
        $t = $zip ? "" : "\r\n";
        $s = $this->arrToStrs($arr, $t, $zip);
        $s = str_replace(",\r\n)","\r\n)",$s);
        $s = str_replace("\\\"","\"",$s);
        return str_replace(",)", ")", $s);
    }
    private function arrToStrs($arr,$t,$zip){
        if(!is_array($arr)) return '""';
        $delkey = '';
        $s = 'array(';
        $ts = $tbr = $t;
        if(!$zip){
            $t .= "\t";
            $tbr .= "\t";
        }
        if(isset($arr['delkey'])){//表示此数组不需要键名
            unset($arr['delkey']);
            $delkey = 1;
            $addstring = $arr['addstring'] ? $arr['addstring'] : '';
            unset($arr['addstring']);
            $ts = '';
        }
        if(isset($arr['delbr'])){//表示此数组不需要换行
            unset($arr['delbr']);
            $tbr = '';
        }
        $addprefix = (isset($arr['addprefix']) && $arr['addprefix']) ? $arr['addprefix'] : '';
        unset($arr['addprefix']);
        $notcknum = isset($arr['cknum']) &&  $arr['cknum'] === false;//使用cknum:false表示所在数组不进行数字判断
        if($notcknum) unset($arr['cknum']);
        foreach($arr as $k => $v){
            if(is_object($v)){
                $v = (array)$v;
                $v = $this->arrToStrs($v,$t,$zip);
            }elseif(is_array($v)){
                $v = $this->arrToStrs($v,$t,$zip);
            }elseif(is_numeric($v) && $this->_cknum && !$notcknum){
                if(strpos($v,'.') === false && strpos($v,'0') === 0 && strlen($v) > 1){//0开头的多位非小数要加引号
                    $v = "'". $v ."'";
                }
            }else{
                $v = (string)$v;
                if(strpos($v,'@') === 0 && preg_replace('#[^@_a-zA-Z0-9]#isU', '', $v) === $v){
                    $v = str_replace('@', '', $v);//常量，去掉标记后原样输出
                }else{
                    $v = "'". addslashes($v) ."'";
                }
            }

            if(!is_numeric($k)) $k = "'". rtrim($k) ."'";
            if($delkey){
                $s .= $addstring.$v.",";
            }else{
                $s .= $tbr.$addprefix.$k."=>".$v.",";
            }
        }
        $s = trim($s,',');
        $s .= ($tbr === '' ? '' : $ts) . $addprefix .")";
        return $s;
    }

    //除了第一层外，其它都合并成一行，同时自动删除有序数组的key
    public $keepBr = array();//要留保换行的key
    public function zipArr($arr = '', $delbr = 0, $key = ''){
        if($arr === ''){
            $this->zip = true;
            return $this;
        }
        if(is_object($arr)) $arr = (array)$arr;
        if(!is_array($arr)) return $arr;
        unset($arr['delkey']);
        if(array_values($arr) === $arr) $arr['delkey'] = 1;
        foreach ($arr as $k => $rs){
            if(is_array($rs)){
                $arr[$k] = $this->zipArr($rs, 1, $k);
                if(!in_array($k, $this->keepBr, true)){
                    $arr[$k]['delbr'] = 1;
                }
            }
        }
        if($delbr && !in_array($key,$this->keepBr)) $arr['delbr'] = 1;//$delbr用来控制第一层不用删除换行
        return $arr;
    }

    //备注
    private function phpbegin($desc = ''){
        if(!$desc){
            $desc = (array)debug_backtrace();
            foreach($desc as $rs){
                if($rs['class'] === 'CI_OP' && ($rs['function'] === 'php' || $rs['function'] === 'php_define')){
                    $desc = $rs['file'] .':'. $rs['line'];
                    break;
                }
            }
        }
        return "<?php\r\n//cms,". gmdate('Y-m-d H:i:s',time()+3600*8) .",". CS::$admin['id'] . $desc . "\r\n";
    }
}