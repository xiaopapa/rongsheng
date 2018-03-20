<?php
/**
 * Copyright (c) boyaa.com
 * Developer - 安凌志
 * Last modify - 2014.05.15
 * Info - 文件操作静态类
 */

class file{
    public static function size($f){
        if(!is_file($f)) return '0KB';
        $size = filesize($f);
        $v = 1024*1024*1024*1024;
        if($size >= $v) return number_format($size/$v, 2) .'TB';
        $v = 1024*1024*1024;
        if($size >= $v) return number_format($size/$v, 2) .'GB';
        $v = 1024*1024;
        if($size >= $v) return number_format($size/$v, 2) .'MB';
        $v = 1024;
        if($size >= $v) return number_format($size/$v, 2) .'KB';
        return $size .'B';
    }

    //将$html里的baes64的图片转存并替换为链接
    public static function base64img($html, $code = true){
        if($code) $html = stripslashes($html);
        $e = preg_match_all('#<img src="data:image/([^;]*);base64,([^"]*)" \/>#isU', $html, $arr);
        if($e && $arr && $arr[2]){
            $dir = 'base64img/';
            foreach($arr[2] as $i => $b64){
                $img = base64_decode($b64);
                $type = $arr[1][$i];
                $src = date('Ym') .'/'. substr(md5($img), 0, 8) .'.'. $type;
                self::put($dir.$src, $img);
                $html = str_replace($arr[0][$i], '<img src="'. SYS_URL .'data/'. $dir . $src .'" />', $html);
            }
        }
        if($code) $html = addslashes($html);
        return $html;
    }

    //写文件
    public static function put($file, $s, $path = DATA_ROOT, $append = NULL){
        $file = strpos($file, 'wwwroot/') !== false ? $file : $path.$file;
        return file::puts($file, $s, $append);
    }
    //写入文件(完整路径)
    public static function puts($file, $s, $append = NULL){
        $path = dirname($file);
        if(!is_dir($path)) mkdir($path, 0775, true);
        return file_put_contents($file, $s, $append);
    }

    //读文件
    public static function get($f, $bom = false){
        if(is_file($f)){
            $s = file_get_contents($f);
            if($s && $bom && ord(substr($s,0,1)) == 239 && ord(substr($s,1,1)) == 187 && ord(substr($s,2,1)) == 191){
                $s = substr($s, 3);
            }
        }else{
            $s = '';
        }
        return $s;
    }

    //备份
    public static function bak($file, $path = DATA_ROOT){
        if(strpos($file,$path) === 0) $file = str_replace($path, '', $file);
        if(!is_file($path.$file)) return false;
        $arr = pathinfo($file);
        $ext = '.'.$arr['extension'];
        $new = str_replace($ext, '_'.fun::date('dHis').'_'.o::$admin['id'].$ext, $file);
        return file::copy($path.$file, DATA_ROOT .'bak/'. fun::date('Ym') .'/'. o::$sid .'/'. $new);
    }

    //复制文件
    public static function copy($from, $to, $code = 0775){
        $dir = dirname($to);
        if(!is_dir($dir)) mkdir($dir, $code, true);
        return copy($from, $to);
    }

    //原目录，复制到的目录
    public static function recurse_copy($src,$dst) {
        if($dir = opendir($src)){
            !is_dir($dst) && mkdir($dst, 0775, true);//无文件夹时新建
            while(false !== ( $file = readdir($dir)) ) {
                if (( $file != '.' ) && ( $file != '..' )) {
                    if ( is_dir($src . '/' . $file) ) {
                        file::recurse_copy($src . '/' . $file, $dst . '/' . $file);
                    }else {
                        copy($src . '/' . $file, $dst . '/' . $file);
                    }
                }
            }
        }
        closedir($dir);
    }

    //删除目录及目录下的所有文件
    public static function delAll($dirName){
        if(is_dir($dirName) && ($handle = opendir($dirName))){
            while(false!==($file = readdir($handle))){
                if($file != "." && $file != ".." ){
                    if(is_dir($dirName.'/'.$file)){
                        file::delDirAndFile($dirName.'/'.$file );
                    }else{
                        unlink($dirName.'/'.$file);
                    }
                }
            }
            closedir( $handle );
            rmdir( $dirName );
        }
    }

    //附件操作
    public static function attach($path, $add = false){
        if(!$path) return array();
        if($add){
            $temp = UPLOAD_ROOT . cms::get('upload_temp_val');
            file::recurse_copy($temp, UPLOAD_ROOT.$path);//转移到新的目录
            file::delAll($temp);//删除临时目录
        }else{
            $dir = UPLOAD_ROOT . $path;
            return file::getAll($dir, false, $dir);
        }
    }

    /**
     * 列出当前文件夹和文件
     * array(
     *	'path' => array(
     *		array("目录名","最后修改时间"),
     *		array("目录名","最后修改时间"),
     *	),
     *	'file' => array(
     *		array("文件名","最后修改时间"),
     *		array("文件名","最后修改时间"),
     *	),
     * )
     */
    public static function getList($defdir = '.', $full = false){
        $temp = array(
            "path" => array(),
            "file" => array()
        );
        if(is_dir($defdir)){
            $fh = opendir($defdir);
            while(($file = readdir($fh)) !== false){
                if(strcmp($file,'.') == 0 || strcmp($file,'..') == 0 || strcmp($file,'.svn') == 0) continue;
                $fs = $defdir.$file;
                $ret = $full ? $fs : $file;
                $time = date("Y-m-d H:i:s",filemtime($fs));
                if(is_dir($fs)){
                    $temp["path"][] = array($ret,$time);
                }else{
                    $temp["file"][] = array($ret,$time);
                }
            }
            closedir($fh);
        }
        return $temp;
    }

    //列出所有子文件，用来批量处理文件用
    public static function getAll($defdir = '.', $child = true, $del = ''){
        $temp = array();
        if(is_dir($defdir)){
            $fh = opendir($defdir);
            while(($file = readdir($fh)) !== false){
                if(strcmp($file,'.') == 0 || strcmp($file,'..') == 0) continue;
                $fs = $defdir."/".$file;
                $fs = str_replace("//","/",$fs);
                if(is_dir($fs)){
                    $child && $temp = array_merge($temp, file::getAll($fs,$child,$del));
                }else{
                    if($del) $fs = str_replace($del, '', $fs);
                    $temp[] = $fs;
                }
            }
            closedir($fh);
        }
        return $temp;
    }

    //列出文件及时间，后两参数都是函数递归需要的，对实际调用没多大意义
    public static function getTime($defdir, $child = true, $del = '', $temp = array()){
        $del = $del ? $del : $defdir;
        if(is_dir($defdir)){
            $fh = opendir($defdir);
            while(($file = readdir($fh)) !== false){
                if(strcmp($file,'.') == 0 || strcmp($file,'..') == 0) continue;
                $fs = $defdir."/".$file;
                $fs = str_replace("//","/",$fs);
                if(is_dir($fs)){
                    $child && ($temp = file::getTime($fs, $child, $del, $temp));
                }else{
                    $temp[str_replace($del, '', $fs)] = filemtime($fs);
                }
            }
            closedir($fh);
        }
        return $temp;
    }
}