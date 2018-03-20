<?php
/**
 * Copyright (c) boyaa.com
 * Developer - 安凌志
 * Last modify - 2014.05.26
 * Info - 系统静态函数库
 */


//公共静态函数集
class fun{
    public static $cache =  array();
    public static function isMobile(){
        // 如果有HTTP_X_WAP_PROFILE则一定是移动设备
        if(isset($_SERVER['HTTP_X_WAP_PROFILE'])) return true;

        // 如果via信息含有wap则一定是移动设备,部分服务商会屏蔽该信息
        if(isset($_SERVER['HTTP_VIA'])) return stristr($_SERVER['HTTP_VIA'],'wap') ? true : false;

        // 脑残法，判断手机发送的客户端标志,兼容性有待提高
        if(isset($_SERVER['HTTP_USER_AGENT'])){
            $clientkeywords = array('nokia','sony','ericsson','mot','samsung','htc','sgh','lg','sharp','sie-','philips','panasonic','alcatel',
                'lenovo','iphone','ipod','blackberry','meizu','android','netfront','symbian','ucweb','windowsce','palm','operamini','operamobi',
                'openwave','nexusone','cldc','midp','wap','mobile');
            if(preg_match("/(". implode('|', $clientkeywords) .")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) return true;
        }
        return false;
    }

    //对PHP数组（类似MYSQL查出来的数据）进行排序，用法：cms::arr_sort($data, array('id','asc','num'), array('val','desc','num'));
    public static function arr_sort(){
        $p = func_get_args();
        $data = $p[0];
        if(!$data || !is_array($data)) return $data;
        unset($p[0]);
        if(!$p) return $data;
        $aTemp = array();
        foreach($data as $k => $rs){
            $aTemp['__key'][] = $k;
            foreach($p as $v){
                $aTemp[$v[0]][] = $rs[$v[0]];
            }
        }
        foreach($p as $v){
            $sc = $v[1] == 'desc' ? SORT_DESC : SORT_ASC;
            if($v[2] == 'num'){
                $tp = SORT_NUMERIC;
            }else if($v[2] == 'str'){
                $tp = SORT_STRING;
            }else{//保持原样
                $tp = SORT_REGULAR;
            }
            $par[] = &$aTemp[$v[0]];
            $par[] = $sc;
            $par[] = $tp;
        }
        $par[] = &$aTemp['__key'];
        call_user_func_array('array_multisort', $par);
        $ret = array();
        foreach($aTemp['__key'] as $i => $k){
            $ret[$k] = $data[$k];
        }
        return $ret;
    }

    //将数组键转为元素
    public static function index2key($arr, $key = 'id'){
        if(!$arr || !is_array($arr)) return $arr;
        $ret = array();
        foreach($arr as $k => $v){
            $v[$key] = $k;
            $ret[] = $v;
        }
        return $ret;
    }

    public static function formatStr($str, $arr){
        if(!$str || !$arr || !is_string($str) || !is_array($arr)) return $str;
        foreach($arr as $k => $v){
            $str = str_replace('#'.$k, $v, $str);
        }
        return $str;
    }

    //输出JS格式
    public static function echojs($js){
        header('Content-type:application/x-javascript;charset=utf-8');
        echo $js;
    }

    //cookie操作 特别说明：$domain最好不要指定值，为空表示hostonly，仅当前域名全等情况下有效
    public static function cookie($name, $value = 'NULL', $timeout = 86400, $path = '/', $domain = '', $secure = false, $httponly = true){
        if($value === 'NULL') return $_COOKIE[$name];//取值
        if($timeout > 0) $_COOKIE[$name] = $value;
        return setcookie($name, $value, $timeout, $path, $domain, false, true);
    }


    //取手写的系统级配置，在commlib/inc.php里配置的
    public static function sysConfig($k, $def = '_def'){
        return isset(o::$config[$k][SYS_NAME]) ? o::$config[$k][SYS_NAME] : o::$config[$k][$def];
    }

    //其它系统使用CMS预警功能
    public static function warning($typeid, $data){
        $par = http_build_query(array('typeid'=>$typeid,'data'=>$data));
        return fun::curl('http://cms.oa.com/api/?cmd=warning', $par);
    }

    //应用所有者配置表
    public static function applistCfg($appid = 0, $api = 0){
        if(!$api) $api = o::$api;
        $k = 'applistCfg_'. $api;
        if(!isset(o::$temp[$k])){
            $f = COM_CFG .'applist/'. $api .'.php';
            if(is_file($f)){
                $a = include($f);
            }else{
                $a = array();
            }
            o::$temp[$k] = $a;
        }
        return $appid ? o::$temp[$k][$appid] : o::$temp[$k];
    }

    //LOG客户端操作日志
    public static function doLog($appId, $keyName = '', $val = 0, $change = array(), $prefix = ''){
        if($appId === true){//app/log/log.php?cmd=add
            $db = o::db('log');
            $db->setValue('appId,sid,keyName,val,prefix,change');
            if($db->value['keyName'] == 'id') $db->value['keyName'] = '';
            $db->value['change'] = $db->value['change'] ? fun::doLogs($db->value['change']) : '';
            $db->value['sid'] = o::$sid;
            $db->value['uid'] = o::$admin['id'];
            $db->value['times'] = NOW;
            return $db->insert();
        }
        if(!$change || !is_array($change)) return false;//ajax.php?cmd=updateDb
        if($keyName == 'id') $keyName = '';
        $db = o::db('log');
        $db->value = array(
            'sid' => o::$sid,
            'uid' => o::$admin['id'],
            'times' => NOW,
            'appId' => $appId,
            'keyName' => $keyName,
            'val' => $val,
            'change' => fun::doLogs($change),
            'prefix' => $prefix,
        );
        return $db->insert();
    }
    private static function doLogs($change){//多行文字使用行来差异
        foreach ($change as $key => $val){
            if(strpos($val[0],"\n") > 0 && strpos($val[1],"\n") > 0){
                $a0 = explode("\n", $val[0]);
                $a1 = explode("\n", $val[1]);
                foreach ($a0 as $k => $v){
                    if($v == $a1[$k]){
                        unset($a0[$k]);
                        unset($a1[$k]);
                    }else{
                        break;
                    }
                }
                if($len = count($a0)){
                    $a0 = array_reverse($a0);
                    $a1 = array_reverse($a1);
                    foreach ($a0 as $k => $v){
                        if($v == $a1[$k]){
                            unset($a0[$k]);
                            unset($a1[$k]);
                        }else{
                            break;
                        }
                    }
                    $a0 = array_reverse($a0);
                    $a1 = array_reverse($a1);
                }
                $change[$key] = array(implode("\n",$a0), implode("\n",$a1));
            }
        }
        return addslashes(fun::json($change));
    }

    //所属多分类
    public static function cidstr($cid, $def = 0){
        if($cid === 'sql') return "(cid=". $def ." OR cid='all' OR cid LIKE '%,". $def .",%')";
        if(!$cid) return $def;
        if(strpos($cid,'all') !== false) return 'all';
        if($cid){
            $arr = array();
            $cidArr = explode(',', $cid);
            foreach ($cidArr as $cid){
                if(!$cid) continue;
                $arr[] = (int)$cid;
            }
            if(!$arr) fun::callback(false);
            $cid = count($arr) > 1 ? ','.implode(',',$arr).',' : $arr[0];
        }
        return $cid;
    }

    //挂载进程执行 fun::cli('mk','system');
    public static function cli(){
        $par = func_get_args();
        foreach($par as &$s){
            $s = '"'. addslashes($s) .'"';
        }
        $par = join(' ', $par);
        if(!$par) die('fun::cli($par) is error');
        $cli = array(
            'on' => LOCAL ? 'LOCAL' : 'CMS',
            'PHPSESSID' => session_id(),
            'sid' => o::$sid,
            'api' => o::$api,
        );
        if(defined('IN_LOGIN')) $cli['nologin'] = 1;
        if(is_array(o::$temp['cli'])) $cli = array_merge($cli, o::$temp['cli']);
        $cli = fun::serialize($cli);
        $f = PHP_CGI . API_ROOT .'cli.php "'. $cli .'" '. $par .' &';
        fun::log($f, 'cliCmd');
        var_dump($f);die;
        system($f);
        return $f;
    }
    //输出开发时的进程日志
    public static function cliLog(){
        if(!LOCAL) return;
        echo "【".fun::date()."】\n";
        $args = func_get_args();
        print_r($args);
    }

    //调试输出日志
    public static function ob_log(){
        $args = func_get_args();
        switch ($args[0]){
            case 'init':
                ob_start();
                var_dump($args);
                break;
            case 'out':
                $s = ob_get_clean()."\n";
                file_put_contents(DATA_ROOT. $args[1], $s, FILE_APPEND);
                echo $s;
                break;
            default:
                var_dump($args);
        }
    }

    //调用应用接口
    public static function app_url($app, $par, $path = '', $data = ''){
        $path = $path ? $path : $app;
        $url = SYS_URL.'app/'.$path.'/'.$app.'.php?cmd='.$par;
        if($data && is_string($data)){
            $url .= '&'. $data;
            $data = '';
        }
        return fun::curl($url, $data);
    }

    public static function oajm($val, $key, $add = false){
        $json = '<<[JSON]>>';
        if($add){
            if(!$val && $val != 0) return '';
            if(is_array($val) || is_object($val)) $val = $json . json_encode($val);
            $val = byoa_encode($val, $key);
        }else{
            $val = byoa_decode($val, $key);
            if($val && strpos($val, $json) === 0){
                $val = str_replace($json, '', $val);
                $val = (array)json_decode($val, true);
            }
        }
        return $val;
    }

    /**
     * 把对应的值压入数组.此处过滤掉null及空串以节约存储
     */
    public static function combine( $aKey, $aValue, $null=false ){
        foreach( (array) $aKey as $key => $value ){
            if($null === true){
                isset($aValue[$value]) && ($aValue[$value] == '') && ($aTemp[$key] = $aValue[$value]);
            }else{
                ($aValue[$value] !== null) && ($aValue[$value] !== '') && ($aTemp[$key] = $aValue[$value]);
            }
        }
        return (array) $aTemp;
    }

    /**
     * 反转数组
     */
    public static function uncombine( $aKey, $aValue, $null=false ){
        foreach( (array) $aKey as $key => $value ){
            if($null === true){
                isset($aValue[$value]) && ($aValue[$key] == '') && ($aTemp[$value] = $aValue[$key]);
            }else{
                ($aValue[$key] !== null) && ($aValue[$key] !== '') && ($aTemp[$value] = $aValue[$key]);
            }
        }
        return (array) $aTemp;
    }

    //设置模板地址（源需要检测），输出地址不需要
    public static function setTplUrl($url, $check = true, $arr = ''){
        $arr = $arr ? $arr : array('langfix', 'sid');
        foreach ($arr as $s){
            if(strpos($url,'['.$s.']') !== false){
                $f = str_replace('['.$s.']', o::$cfg[$s], $url);
                if(!$check){
                    $url2 = $f;
                    continue;
                }
                if(is_file(TPL_ROOT.$f)){
                    $url2 = $f;
                }else{
                    $url2 = str_replace('['.$s.']', 'default', $url);
                }
            }
        }
        if($check){
            if($url2 && file_exists(TPL_ROOT.$url2)) $url = $url2;
            if(!file_exists(TPL_ROOT.$url)) fun::callback('模板不存在：'.$url);
        }else{
            $url = $url2 ? $url2 : $url;
        }
        return $url;
    }

    //替换标签[langfix]/[sid]
    public static function replaceTag($s){
        if(!$s) return $s;
        $arr = array('langfix', 'sid');
        o::$cfg['sid'] = o::$sid;
        foreach ($arr as $k){
            if(strpos($s,'['.$k.']') !== false) return str_replace('['.$k.']', o::$cfg[$k], $s);
        }
        return $s;
    }

    //传输参数压缩与解压(cms、cmsapi保持一致)
    public static function zCode($s){
        if(!$s) return '';
        if(is_array($s)) $s = json_encode($s);
        $s = gzcompress($s, 9);
        return base64_encode($s);
    }
    public static function unCode($s, $arr = false){
        if(!$s) return false;
        $s = base64_decode($s);
        $s = gzuncompress($s);
        if($arr && $s) $s = json_decode($s, true);
        return $s;
    }

    //除了第一层外，其它都合并成一行，同时自动删除有序数组的key
    public static function zipArr($arr, $delbr = 0, $key = ''){
        if(is_object($arr)) $arr = (array)$arr;
        if(!is_array($arr)) return $arr;
        unset($arr['delkey']);
        if(array_values($arr) === $arr) $arr['delkey'] = 1;
        $_br = array('io','actio','actcfg','RedisActs','allprice','mttmatchnew');//要留保换行的key
        foreach ($arr as $k => $rs){
            if(is_array($rs)){
                $arr[$k] = self::zipArr($rs, 1, $k);
                if(!in_array($k, $_br, true)){
                    $arr[$k]['delbr'] = 1;
                }
            }
        }
        if($delbr && !in_array($key,$_br)) $arr['delbr'] = 1;//$delbr用来控制第一层不用删除换行
        return $arr;
    }

    //权限服务端应用 多个权限节点同时判断
    public static function allows($arr, $inArr = false){
        if(is_array($arr)){//或
            foreach($arr as $s){
                if(self::allow($s, true, $inArr)) return true;//任意条件满足即返回true
            }
            return false;
        }else{
            $arr = explode(',', $arr);//且
            foreach($arr as $s){
                if(!self::allow($s, true, $inArr)) return false;//任意条件不满足则返回false
            }
            return true;
        }
    }
    //权限服务端应用
    public static function allow($s, $ret = false, $inArr = false){
        $aAllow = (array)o::$admin['allow'];
        if(is_numeric($s) && in_array('sitelist.*',$aAllow)) return true;//CMS站点全局权限检测
        $temp = o::auth()->check($s, $aAllow, $inArr);
        if($temp){//有权限
            return true;
        }else{
            if($ret){
                return false;
            }else{
                fun::callback('notAllow'. $s);
            }
        }
    }

    //正整型
    public static function uint($num){
        return max(0, (int)$num);
    }

    //字符处理
    public static function addslashes($str){
        return addslashes(trim($str));
    }

    //正整型
    public static function cut($str, $len, $strip_tags = 0, $fix = '...'){
        if(!$str || !is_string($str)) return $str;
        if($strip_tags) $str = strip_tags($str);
        $length = strlen($str);
        if($length > $len){
            $str = mb_substr($str, 0, $len, 'UTF-8') . $fix;
        }
        return $str;
    }

    /**
     * 检查某个PHP文件是否有语法错误
     * @param String $filename 文件的绝对路径
     * @return Boolean
     */
    public static function checkSyntax( $filename ){
        if( !$contents = @file_get_contents( $filename ) ){
            return false;
        }
        return fun::checkSyntaxBlock($contents);
    }

    /**
     * 检查某一段PHP代码是否有语法错误
     * @param String $string 代码片段
     * @return Boolean
     */
    public static function checkSyntaxBlock( $string ){
        if( !$string ){
            return false;
        }
        return @eval( 'return true;' . $string ) ? true : false;
    }

    //AJAX页面启动，分配置事件，通过cmd参数实现
    public static function init($def = ''){
        if($cmds = fun::get('cmds')){//批量接口命令
            $ret = array();
            foreach($cmds as $cmd => $par){
                $def = htmlspecialchars(addslashes('_'.$cmd));
                if(function_exists($def)){
                    $ret[$cmd] = $def($par, true);//第一个参数为传入参数，第二个标记是批量接口
                }else{
                    $ret[$cmd] = '[Error cmd]'. $def;
                }
            }
            cms::callback($ret);
        }else{
            $cmd = fun::get('cmd');
            if(!$cmd && $def) $cmd = $def;
            $def = htmlspecialchars(addslashes('_'.$cmd));
            if(function_exists($def)){
                $def();
            }else{
                fun::callback('[init:'.$def.'] Error param!');
            }
        }
    }

    //文件缓存
    public static function cache($k, $val = '_NULL_', $append = NULL){
        $f = DATA_ROOT.'cache/'. $k;
        if($val === 'clear'){
            if(is_dir($f)){
                file::delAll($f);
            }else if(is_file($f)){
                unlink($f);
            }
            return true;
        }
        $j = '>J<';
        if($val === '_NULL_'){//取值
            if(is_file($f)){
                $val = file_get_contents($f);
                if($val && strpos($val,$j) === 0){
                    $val = str_replace($j, '', $val);
                    $val = json_decode($val, true);
                }
                return $val;
            }else{
                return false;
            }
        }else{
            if(is_array($val)) $val = $j.fun::json($val);
            return file::puts($f, $val, $append);
        }
    }

    //组装where参数
    public static function setWhere($w){
        if(!is_array($w) || empty($w)) return '';
        $s = array();
        foreach($w as $k => $t){
            $v = fun::get($k);
            if($v == '') continue;
            if(strpos($k,'|') !== false){//or查询
                $arr = explode('|', $k);
                $temp = array();
                foreach ($arr as $n){
                    $temp[] = fun::whereone($n, $v, $t);
                }
                $s[] = '('. join(' OR ', $temp) .')';
            }else{//单一and查询
                $s[] = fun::whereone($k, $v, $t);
            }
        }
        return join(' and ', $s);
    }

    //组装where条件
    private static function whereone($k, $v, $t = false){
        $sign = $_GET[$k];
        if(in_array($sign, array('>=','>','<','<=','<>','!='))){
            return "`$k`". $sign ."'$v'";
        }
        if($t){//支持模糊查找
            $w = "`$k` LIKE '%$v%'";
        }else{
            $w = "`$k`='$v'";
        }
        return $w;
    }

    //获取参数
    public static function get($s, $def = ''){
        $s = isset($_POST[$s]) ? $_POST[$s] : (isset($_GET[$s]) ? $_GET[$s] : '');
        $s = isset($s) && $s !== '' ? $s : $def;
        if($s !== false) $s = fun::magic_quote($s);
        if($s && is_string($s) && strpos($s,';base64,') > 0) $s = file::base64img($s);
        return $s;
    }
    public static function getPOST(){
        $ret = array();
        if($_POST){
            foreach ($_POST as $k => $v){
                $ret[$k] = fun::get($k);
            }
        }
        return $ret;
    }
    public static function getInt($k, $msg = ''){
        $k = (int)fun::get($k);
        if($msg && !$k) fun::callback($msg);
        return $k;
    }

    //获取ID串
    public static function getIds($nm, $msg = '', $retArr = false, $s = ','){//获取ID串
        $ids = self::fmtIds(fun::get($nm), $retArr, $s);
        if(!$ids && $msg) fun::callback($msg);
        return $ids;
    }

    //格式化ID串
    public static function fmtIds($ids, $retArr = false, $k = ','){
        $arr = explode($k, $ids);
        $r = array();
        foreach($arr as $v){
            if($v = (int)$v) $r[] = $v;
        }
        if($retArr) return $r;
        $r = implode($k, $r);
        return $r;
    }

    //获取数组
    public static function getArray($k, $msg = ''){
        $k = (array)fun::get($k);
        if($msg && !$k) fun::callback($msg);
        return $k;
    }

    //获取输出数据
    public static function ob_get(){
        $args = func_get_args();
        ob_start();
        var_dump($args);
        return ob_get_clean();
    }

    /**
     * 根据magic_quote判断是否为变量添加斜杠
     * @param mix $mixVar
     * @return mix
     */
    public static function magic_quote( $mixVar ){
        if( !get_magic_quotes_gpc() ){
            if( is_array( $mixVar ) ){
                foreach( $mixVar as $key => $value ){
                    $temp[$key] = fun::magic_quote( $value );
                }
            }else{
                $temp = addslashes( $mixVar );
            }
            return $temp;
        }else{
            return $mixVar;
        }
    }

    /**
     * 把数组序列成Server识别的.有缺陷,不能是null类型的
     * @param Array $array
     */
    public static function serialize( $array ){
        return str_replace( '=', ':', http_build_query( $array, null, ',' ) );
    }

    /**
     * 把字符串反序列成索引数组
     * @param String $string
     */
    public static function unserialize( $string ){
        parse_str( str_replace( array( ':', ',' ), array( '=', '&' ), $string ), $array );
        return (array) $array;
    }

    //所有请求都记下日志
    public static function urllog(){
        if(LOCAL) return;
        if($_SERVER['REQUEST_URI']){
            $log = $_SERVER['REQUEST_URI'];
            $un = array(//不需要记日志的情况
                'api/?cmd=svn',//自动SVN
                'logs.php?cmd=find',//查日志
                'login.php?cmd=info',//取用户信息
                'login.php?cmd=sign',//取登陆密要
            );
            foreach($un as $k){
                if(strpos($log,$k) !== false) return;
            }
            if($_POST) $log .= "\t". json_encode($_POST);
            fun::log($log, 'url_uri');
        }else{
            $log = $_SERVER['argv'];
            fun::log($log, 'url_argv');
        }
    }

    //操作日志
    public static function log($msg, $path = '', $out = false){
        $uid = (int)CS::$admin['id'] ? (int)CS::$admin['id'] : CURRENTCLASS . '-' . CURRENTMETHOD; //如果有操作人uid，写uid，没有，记录接口类和方法
        $path = $path ? $path .'/' : '';
        fun::wlog($msg, $uid, LOG_ROOT.fun::date('Ym').'/'.$path, $out);
    }
    private static function wlog($msg, $uid, $path, $out){//cms、cmsapi通用
        if(is_array($msg)) $msg = fun::json($msg);
        if(SYS_NAME === 'cms'){
            $sid = cms::getSid();
            $log = sprintf('[%s] [UID:%s] [SID:%s] [API:%s] [SER:%s] [%s] %s', fun::date('m-d H:i:s'), $uid, $sid, o::$api, o::$server, fun::getip(), $msg);
        }else{
            $log = sprintf('[%s] [UID:%s] [%s] %s', fun::date('m-d H:i:s'), $uid, fun::getip(), $msg);
        }
        $date = fun::date('Y-m-d');
        $file = $path.$date.'.php';
        $path = dirname($file);
        if(!is_dir($path)) mkdir($path, 0777, true);
        if(!is_file($file)) $log = "<?php (isset(\$_GET['p']) && (md5('&%$#'.\$_GET['p'].'**^')==='8b1b0c76f5190f98b1110e8fc4902bfa')) or die();?>\n". $log;
        file_put_contents($file, $log ."\n", FILE_APPEND);
        if($out){
            echo '<pre><b>Log</b>:'. $log;
            throw new Exception($msg);
        }
    }

    //统一取北京时间，使用格林威治标准时加8小时
    public static function date($s = '', $time = 0){
        $time = ($time > 0 ? $time : time()) + 3600*8;
        return gmdate($s?$s:'Y-m-d H:i:s', $time);
    }

    /**
     * ajax+JSON返回函数 支持跨域
     * @param Array $ret 返回的数组
     * @param $ok 成功标记 0失败 1成功
     * @return string json
     */
    public static function callback($ret = '', $ok = 0, $tiptype = ''){
        if($ret === true){
            $ret = array();
            $ok = 1;
        }elseif($ret === false){
            $ret = array('msg' => '参数错误');
        }elseif(is_array($ret)){
            $ok = 1;
        }else{
            $ret = array('msg'=>$ret);
        }
        if(!isset($ret['num']) && is_array($ret['loop'])) $ret['num'] = count($ret['loop']);//自动加上num数据总量count(loop)
        isset($ret['ok']) or $ret['ok'] = $ok;
        isset($ret['num']) && $ret['num'] = (int)$ret['num'];
        $tiptype && $ret['tiptype'] = $tiptype;
        if($ret['tiptype']) $ret['tiptype'] = $ret['tiptype'] === true ? 'Alert/Err' : $ret['tiptype'];
        fun::ret($ret);
    }

    public static function ret($ret){
        header('Content-type:application/x-javascript;charset=utf-8');
        header('Cache-Control:no-cache');
        if(defined('NO_CMSJSON')){//使用普通的json格式，有些特殊字符要兼容。比如说客服系统里玩家的昵称很奇葩
            $ret = json_encode($ret);
        }else{
            $ret = fun::json($ret);
        }
        $cb = isset($_GET['callback']) && $_GET['callback'] ? htmlspecialchars(addslashes($_GET['callback'])) : 0;
        if($cb){
            echo($cb.'('.$ret.')');
        }else{
            echo($ret);
        }
//        fun::fastcgi('exec');
        die();
    }

    //带有状态码返回结果（类http状态码）
    // 0    => 未设状态码
    // 100  => 参数缺失
    // 200  => 操作成功
    // 250  => 参数错误或其他一些提示信息
    // 300  => 条件不符合
    // 400  => 找不到数据
    // 500  => 操作失败
    public static function codeBack($ret = '', $num = 0,$code = 0){
        if($ret === true){
            $ret = array(
                'code' => 200,
                'msg' => '操作成功',
                'time' => time()
            );
        }elseif($ret === false){
            $ret = array(
                'code' => 500,
                'msg' => '操作失败',
                'time' => time()

            );
        }elseif(is_array($ret)){
            $ret = array(
                'code' => 200,
                'data' => $ret,
                'time' => time(),
                'num' => $num
            );
        }else{
            $ret = array('msg'=>$ret,'code'=> 250);
        }
        if($code) $ret['code'] = $code;
        fun::ret($ret);
    }

    /*
     * 等接口返回给客户端后执行，用于处理一些不需要实时返回结果的事件，可取代 fun::cli
        fun::fastcgi('cms::app_url', 'regMap', 'ref', '', 'tbl=act');
        fun::fastcgi('o("config")->actCms');
        p(fun::fastcgi('exec'));
     */
    public static $fastcgi = array();
    public static function fastcgi(){
        $p = func_get_args();
        if($p[0] === 'exec'){
            function_exists('fastcgi_finish_request') && fastcgi_finish_request(); //快速返回给客户端
            if(!self::$fastcgi) return 'nothing';
            $temp = self::$fastcgi;
            self::$fastcgi = array();//防止死循环
            $ret = array();
            foreach($temp as $args){
                $fn = array_shift($args);
                $p = $arg = array();
                if($args){
                    foreach($args as $k => $v){
                        $arg[$k] = $v;
                        $p[] = '$arg['.$k.']';
                    }
                }
                $fun = $fn .'('. implode(',',$p) .');';
                $ret[$fn]['fun'] = $fun;
                $ret[$fn]['arg'] = $arg;
                $php = '$ret[$fn]["ret"]='.$fun;
                eval($php);
                cms::log($ret[$fn], 'fastcgi');
            }
            return $ret;
        }else{
            self::$fastcgi[] = $p;
            return true;
        }
    }

    //支持数组子项的扩展
    public static function array_merge($arr, $extend){
        if(!is_array($extend)) return $arr;
        foreach ($extend as $k => $v){
            if(is_array($v)){
                $arr[$k] = fun::array_merge($arr[$k], $v);
            }else{
                $arr[$k] = $v;
            }
        }
        return $arr;
    }

    //通过unicode解码输出JSON，可以减小字符大小
    public static function json($s){
        return json_encode($s, JSON_UNESCAPED_UNICODE);//PHP5.4以上版本支持
        //return fun::decodeUnicode(json_encode($s));
    }

    //把JSON后的中文编码还原成中文
    public static function decodeUnicode($str){
        if(!$str) return $str;
        return preg_replace_callback('/\\\\u([0-9a-f]{4})/i', create_function(
            '$matches',
            'return mb_convert_encoding(pack("H*", $matches[1]), "UTF-8", "UCS-2BE");'
        ), $str);
    }

    /**根据$keyName值重新定义data的键
     *
     * 如果只需要返回部分值,请传递$fields,$fields为字符串时,返回一维数组
     * 为数组时(里面是$data元素的子元素键值)返回$data内含有的字段
     * auth Rave xiong
     * date 2014 09 26
     *
     * param $date array 需要格式化的数组,二维数组
     * param $keyName string  键名,$data子元素内的唯一值
     * param $fields string || array  需要返回的字段名称
     *
     * return array || mix
     */
    public static function formatArr($data, $keyName, $fields = null) {
        if(!is_array($data) || !$data) return $data;//不是数组或者数组无值

        if(is_array($fields)) $fields = array_flip($fields);//将值与键对换,方便下面的array_intersect_key操作

        $result = array();
        foreach((array)$data as $item){
            if(isset($item[$keyName])){//必须存在此键名
                $result[$item[$keyName]] = is_string($fields) ? $item[$fields] : ((is_array($fields) && $fields) ? array_intersect_key($item, $fields) : $item);
            }
        }

        //如果结果集为空,或者没有对应的field值,直接返回原值
        return $result ? $result : $data;
    }

    /**根据数据导出csv格式的文件
     *
     * auth Rave xiong
     * date 2014 11 26
     *
     * param $date array 需要导出的数据
     * param $head	array  头显示
     *
     * return void
     */
    public static function export($data, $head){
        // 输出Excel文件头
        header('Content-Type: application/vnd.ms-excel;charset=gbk');
        header('Content-Disposition: attachment;filename="文件名.csv"');
        header('Cache-Control: max-age=0');
        // PHP文件句柄，php://output 表示直接输出到浏览器
        $fp = fopen('php://output', 'kf');
        // 输出Excel列头信息
        foreach ((array)$head as $i => $v) {
            // CSV的Excel支持GBK编码，一定要转换，否则乱码
            $head[$i] = iconv('utf-8', 'gbk', $v);
        }
        // 写入列头
        fputcsv($fp, $head);
        // 计数器
        $cnt = 0;
        // 每隔$limit行，刷新一下输出buffer，节约资源
        $limit = 100000;
        // 逐行取出数据，节约内存
        foreach($data as $row){
            $cnt ++;
            if ($limit == $cnt) { //刷新一下输出buffer，防止由于数据过多造成问题
                ob_flush();
                flush();
                $cnt = 0;
            }
            foreach ($row as $i => $v) {
                $row[$i] = iconv('utf-8', 'gbk//TRANSLIT//IGNORE', $v);
            }
            fputcsv($fp, $row);
        }
        die();
    }

    /**将PHP数组转换为xml格式(暂时权限系统有用到)
     *
     * auth Rave xiong
     * date 2014 12 25
     *
     * param $date array php数组
     * param $rootNodeName	array  根节点名称
     * param $xml	object  xml对象
     *
     * return object
     */
    public static function toXml($data, $rootNodeName = 'data', $xml=null){
        // turn off compatibility mode as simple xml throws a wobbly if you don't.
        if (ini_get('zend.ze1_compatibility_mode') == 1) ini_set ('zend.ze1_compatibility_mode', 0);

        if ($xml == null) $xml = simplexml_load_string("<?xml version='1.0' encoding='utf-8'?><$rootNodeName />");


        // loop through the data passed in.
        foreach($data as $key => $value)
        {
            // no numeric keys in our xml please!
            if (is_numeric($key)) $key = "unknownNode_". (string) $key;

            // replace anything not alpha numeric
            $key = preg_replace('/[^a-z]/i', '', $key);

            // if there is another array found recrusively call this function
            if (is_array($value))
            {
                $node = $xml->addChild($key);
                // recrusive call.
                fun::toXml($value, $rootNodeName, $node);
            } else {
                // add single node.
                $value = htmlentities($value);
                $xml->addChild($key,$value);
            }
        }
        // pass back as string. or simple xml object if you want!
        return $xml->asXML();
    }

    //判断字符1是否在字符2中，$n = true 表示区分大小写
    public static function instr($str1, $str2, $n = true){
        $sec = $n ? strpos($str2,$str1) : stripos($str2,$str1);
        return $sec === 0 ? true : $sec;
    }

    //随机字符串
    public static function random($len){
        $arr = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','P','Q','R','S','T','U','V','W','X','Y','Z',0,1,2,3,4,5,6,7,8,9);
        shuffle($arr);
        return substr(join($arr,""),0,$len);
    }

    //获取IP getip/qvia2ip/checkIP 三个函数
    public static function getip(){
        if ( isset($_SERVER['HTTP_QVIA']) ) {
            $ip = fun::qvia2ip($_SERVER['HTTP_QVIA']);
            if ( $ip ) {
                return $ip;
            }
        }
        if ( isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP']) ) {
            return fun::checkIP($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : '0.0.0.0';
        }
        if ( isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']) ) {
            $ip = strtok($_SERVER['HTTP_X_FORWARDED_FOR'], ',');
            do {
                $tmpIp = explode('.', $ip);
                //-------------------
                // skip private ip ranges
                //-------------------
                // 10.0.0.0 - 10.255.255.255
                // 172.16.0.0 - 172.31.255.255
                // 192.168.0.0 - 192.168.255.255
                // 127.0.0.1, 255.255.255.255, 0.0.0.0
                //-------------------
                if(is_array($tmpIp) && count($tmpIp) == 4){
                    if (($tmpIp[0] != 10) && ($tmpIp[0] != 172) && ($tmpIp[0] != 192) && ($tmpIp[0] != 127) && ($tmpIp[0] != 255) && ($tmpIp[0] != 0) ){
                        return $ip;
                    }
                    if(($tmpIp[0] == 172) && ($tmpIp[1] < 16 || $tmpIp[1] > 31)){
                        return $ip;
                    }
                    if(($tmpIp[0] == 192) && ($tmpIp[1] != 168)){
                        return $ip;
                    }
                    if (($tmpIp[0] == 127) && ($ip != '127.0.0.1')){
                        return $ip;
                    }
                    if ($tmpIp[0] == 255 && ($ip != '255.255.255.255'))	{
                        return $ip;
                    }
                    if ($tmpIp[0] == 0 && ($ip != '0.0.0.0')){
                        return $ip;
                    }
                }
            } while ( ($ip = strtok(',')) );
        }

        if ( isset($_SERVER['HTTP_PROXY_USER']) && !empty($_SERVER['HTTP_PROXY_USER']) ) {
            return fun::checkIP($_SERVER['HTTP_PROXY_USER']) ? $_SERVER['HTTP_PROXY_USER'] : '0.0.0.0';
        }

        if ( isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR']) ) {
            return fun::checkIP($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        } else {
            return '0.0.0.0';
        }
    }
    /**
     * 获取网通代理或教育网代理带过来的客户端IP
     * @return		string/flase	IP串或false
     */
    private static function qvia2ip($qvia) {
        if ( strlen($qvia) != 40 ) {
            return false;
        }
        $ips = array(hexdec(substr($qvia,0,2)), hexdec(substr($qvia,2,2)), hexdec(substr($qvia,4,2)), hexdec(substr($qvia,6,2)));
        $ipbin = pack('CCCC', $ips[0], $ips[1], $ips[2], $ips[3]);
        $m = md5('QV^10#Prefix'.$ipbin.'QV10$Suffix%');
        if ( $m == substr($qvia, 8) ) {
            return implode('.', $ips);
        } else {
            return false;
        }
    }
    /**
     * 验证ip地址
     * @param		string	$ip, ip地址
     * @return		bool	正确返回true, 否则返回false
     */
    public static function checkIP($ip) {
        $ip = trim($ip);
        $pt = '/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/';
        if ( preg_match($pt, $ip) === 1 ) {
            return true;
        }
        return false;
    }

    //处理页面输出的JS标签
    public static function jsTag($str){
        if(!$str) return $str;
        return preg_replace('@</script@i', '</\' + \'script',
            strtr($str, array(
                "\000" => '',
                '\\' => '\\\\',
                '\'' => '\\\'',
                '"' => '\"',
                "\n" => '',
                "\r" => '')));
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //cms系统安全码（lib/BY.oo.php、cms/lib/sys.class.php、cloudfront/crontab/inc.php、LC勿必保持一致）
    //xxx::by_key('get', array('a'=>'aaa','b'=>'bbb'));//获取安全码数组
    //xxx::by_key('url', array('a'=>'aaa','b'=>'bbb'));//获得安全码URL串
    //xxx::by_key();//检测安全码
    public static function by_key($type = '', $p1 = '', $p2 = 0){
        switch($type){
            case 'get'://$p1是array(data)，$p2是自定义时间戳
                $data = $p1;
                $time = $p2 ? $p2 : time();
                if(is_array($data)){
                    krsort($data);
                    $field = join(',', array_keys($data));
                }else{
                    $data = array();
                    $field = '';
                }
                if($data['_plat']){//LC密要(java没有serialize方法，因此重新定义简单的方法)
                    foreach($data as $k => $v){
                        $data[$k] = trim($v);
                    }
                    $val = '~!@#$%^&*(*^%$$@@' . $time . implode('', $data);
                    $sig = md5($val);
                }else{//原
                    $val = $time . serialize(self::key_data($data));
                    $sig = md5(md5('*by%'.$val.'$oa#com^').$val.'#');
                }
                $ret = array('by_key' => $sig, 'by_time' => $time, 'by_field' => $field);
                if($_GET['debug']) $ret['debug'] = $val;
                return $ret;
                break;
            case 'url'://参数同get
                $data = is_array($p1) ? $p1 : array();
                if(class_exists('cms')){//CMS专用，非cms/commlib/sys.class.php文件可以删除此段
                    $data['by_sid'] = (string)(o::$temp['sid'] ? o::$temp['sid'] : o::$sid);
                    $data['by_uid'] = (string)o::$admin['id'];
                    $data['PHPSESSID'] = session_id();
                }
                $ret = fun::by_key('get', $data, $p2);
                if(class_exists('cms')){//CMS专用，非cms/lib/sys.class.php文件可以删除此段
                    $ret['by_sid'] = (string)(o::$temp['sid'] ? o::$temp['sid'] : o::$sid);
                    $ret['by_uid'] = (string)o::$admin['id'];
                    $ret['PHPSESSID'] = session_id();
                }
                return http_build_query($ret);
                break;
            default://$type是有效时间(s)，$p1、$p2无用
                $t = max((int)$type, 180);
                $by_time = (int)$_REQUEST['by_time'];
                (NOW - $by_time > $t) && fun::callback('Safe key timeout!');//默认3分钟
                $data = array();
                if($field = $_REQUEST['by_field']){
                    $field = explode(',', $field);
                    foreach ($field as $f){
                        $data[$f] = fun::magic_quote($_REQUEST[$f]);
                    }
                }
                $ret = fun::by_key('get', $data, $by_time);
                ($ret['by_key'] !== $_REQUEST['by_key']) && fun::callback('Safe key error!');
                break;
        }
    }
    //格式化蜜要数据
    private static function key_data($data){
        if(!is_array($data)) return trim($data);
        foreach($data as $k => $v){
            $data[$k] = self::key_data($v);
        }
        return $data;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /**
     * 此函数lib/by.oo.php 与 cms.class.php须保持一致
     * 加密生成短链接 生成$_GET参数.加上时间戳和签名 array('ref'=>77, 'id'=>1, 'bagid' => 1553, ..., 'time'=>'13652148531')
     * @param Array $param 键值对 注意 ref和time是保留字
     * @param bool $needarray 是否返回数组
     * @param String $prefix 要加上的前缀
     * @return String &连接的字符串用于...
     */
    public static $_secret = '^$_$^';
    public static function genShort( $param, $needarray = false, $prefix = '' ){
        if( !is_array($param) || !count($param) ) return '';//没有参数
        $secret = self::$_secret;
        foreach( $param as $k => $v ){
            $aParams[$k] = self::enConvert($v);
        }
        $aParams['time'] = self::enConvert( $param['time'] ? $param['time'] : time() );//倒数第二位为时间
        $sig = md5( $secret . implode('', $aParams) . $secret );
        $aParams['sign'] = chr($sig%26 + 65) . substr(substr($sig, -8, 8), 0, 5);//倒数第一位为密要
        if($needarray === 'key') return implode('', $aParams);
        if($needarray) return $aParams;
        if(!$prefix) $prefix = 'sn';
        return http_build_query(array($prefix => implode('', $aParams)));
    }

    public static function enConvert($v) {
        $v = trim($v);
        $s = base_convert($v, 10, 36);
        if(!$s && $v) $s = '.'. strtolower($v);//原样输出(只支持小写英文字母)
        return chr($v%26 + 65) . $s;
    }

    /**
     * 解密短链接 从URL参数中获取有效
     * @param Array $param 键值对.如$_GET
     * @param int $expire 过期时间如7天内有效 7*24*60*60
     * @param String $prefix 前缀.对应生成方法
     * @return Array 键值对(如果未通过验证则为空数组) $aReturn[1] = ref
     */
    public static $shortErr = '';
    public static function getShort( $param, $expire = 0, $prefix = '', $format = '' ){
        if(!$prefix) $prefix = 'sn';
        if(!$param || is_array($param) && (!$param = $param[$prefix])){
            self::$shortErr = '1';
            return false;
        }
        if(!$length = strlen($param)){
            self::$shortErr = '2';
            return false;//参数错误
        }
        $secret = self::$_secret;
        $string = '';
        for( $i = 1; $i < $length; $i++ ) {//第一个是大写字母
            if ( (65 <= ord($param[$i])) && (ord($param[$i]) <= 90) ){//大写字母，是用来分隔参数的
                $string .= '|';
            }else{
                $string .= $param[$i];
            }
        }
        if(!$string){
            self::$shortErr = '3';
            return false;
        }
        $aReturn = explode('|', $string);
        array_pop($aReturn);//最后一位是sig密要
        $tmp = array();
        $format = $format ? $format : array('ref');
        foreach ($aReturn as $k => $v) {
            $v = self::deConvert($v);
            $key = $format[$k] ? $format[$k] : 'param'. $k;
            $tmp[$key] = $v;
        }
        $aReturn = $tmp;
        $aReturn['time'] = array_pop($aReturn);//倒数第二位为时间
        $key = self::genShort($aReturn, 'key');
        if($param != $key){
            self::$shortErr = '4';//密要错误
            return false;
        }
        if($expire){
            if(is_string($expire)) $expire = $aReturn[$expire];//使用参数中的对应值为有效期
            if($expire && (time() - $aReturn['time'] > $expire)){
                self::$shortErr = '5';
                return false;
            }
        }
        return $aReturn;
    }
    public static function deConvert($v){
        $v = $v && $v[0] === '.' ? trim($v, '.') : base_convert($v, 36, 10);
        return $v;
    }

    //apik解码
    public static function apik($apik = ''){
        if(!$apik) $apik = $_REQUEST['apik'];
        if(!$apik) return array();
        //在apik未改变的情况下，直接返回已有的缓存
        $ret = self::getShort($apik, 'atime', '', array('uid','sid','atime'));//self::$shortErr错误码
        $ret = $ret ? $ret : array();
        return $ret;
    }
    /////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //函数清理前加入检测一段时间
    public static function delFunCheck(){
        file::puts(CMS_LIB.'warning.alz.txt', fun::date() ."\t". cms::json((array)debug_backtrace()) ."\n". $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] ."\n", FILE_APPEND);
    }


    //以下为待清理区，批量替换完后，加入fun::delFunCheck();监控一段时间再清理。
    //输出为配置文件
    public static function cfgFile($f, $arr, $name, $desc = '', $cknum = true){
        return o('outfile')->php($f, $arr, $desc, $name, $cknum);
    }
    public static function cfgFileArr($f, $arr, $desc = ''){
        return o('outfile')->phpArr($f, $arr, $desc);
    }
    public static function arrToStr($arr, $zip = false){
        return o('outfile')->arrToStr($arr, $zip);
    }

    //发布curl请求
    public static function curl($url, $data = ''){
        return o('curl')->init($url, $data);
    }
    public static function curl_send($url, $data = ''){
        return o('curl')->send($url, $data);
    }
    public static function post_request($param, $server_addr, $secret='^_^', $proxy=true){
        return o('curl')->post_request($param, $server_addr, $secret, $proxy);
    }
    public static function post($server_addr, $param = array()){
        return o('curl')->post($server_addr, $param);
    }
    //SSO跳转地址
    public static function ssoUrl(){
        fun::delFunCheck();//清理函数前要跑一段时间
        return o('login')->ssoUrl();
    }
    public static function notLogin(){//废弃废弃废弃废弃废弃
        fun::delFunCheck();//清理函数前要跑一段时间
        $ret = array('msg' => 'notLogin', 'ssoUrl' => o('login')->ssoUrl());
        fun::callback($ret);
    }
    public static function admin($data = false){
        fun::delFunCheck();//清理函数前要跑一段时间
        if($data && is_array($data)){
            return o('login')->set($data);
        }else{
            return o('login')->get();
        }
    }
    public static function session($name, $val = 'NULL'){
        fun::delFunCheck();//清理函数前要跑一段时间
        return o('login')->session($name, $val);
    }
    /**
     * 根据key获取用户信息
     * @param unknown $uid
     * @param unknown $key
     * @return multitype:|multitype:unknown mixed
     */
    public static function getSsoInfo($uid, $key){//废弃废弃废弃废弃废弃废弃
        fun::delFunCheck();//清理函数前要跑一段时间
        if(! $uid = self::uint( $uid)){
            return array();
        }
        if(! $key = trim( $key)){
            return array();
        }
        $params = array('do'=>'getInfo', 'uid' => $uid, 'key' => $key, 'appid' => SSONUMBER);
        $url = 'http://192.168.100.248:8871/api?'.http_build_query($params);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $result = curl_exec($ch);
        ($errno = curl_errno($ch)) && ($result = '[]'); //错误的请求如域名不存在网络错误等...
        curl_close($ch);
        $ret = json_decode($result, true);

        if(! (is_array( $ret) && ($ret['ret'] == 1) && isset( $ret['username']) && isset( $ret['cname']) && isset( $ret['code']))){
            self::file( array($ret, $uid, $key), 'sso_login_error.txt');

            return array();
        }
        return array('mrtx'=>$ret['username'], 'mnick'=>$ret['cname'], 'mid'=>$ret['code']);
    }

    //清理数组里的空值 copy ac.class.php
    public static function clearArr($rs){
        $rs = (array)$rs;
        foreach($rs as $k => $v){
            if($v === '') unset($rs[$k]);
        }
        return $rs;
    }

    //根据给出的一个事件段，返回这个时间段的,$st:开始时间的时间戳，$ed:结束时间的时间戳
    //返回数据：array['data'] 时日时间 array['time'] 时间戳
    //注意，返回时间会多一天/月，最后的为截至时间
    public static function timeToPeriod($st,$ed,$ty = 'd',$arg = ''){
        $show = array();//显示时间（时间格式）
        $_show = array();//显示时间（时间戳）
        switch($ty){
            case 'd':
                $fm = isset($arg['fm']) ? $arg['fm'] : "Y-m-d";
                $i = 0;
                $st1 = $st;//小段开始时间
                while($st1 < $ed){
                    if($i == 0){//第一天特殊处理
                        $st_r = date('Y-m-d',$st1 + 86400);
                        $st2 = strtotime($st_r);
                        $show[] = date($fm,$st1);
                        $_show[] = (int) $st1;
                    }else{
                        $st2 = $st1 + 86400;
                    }
                    $show[] = date($fm,$st2);
                    if($st2 > $ed) $st2 = (int) $ed;//如果所选时间大于最后时间，那么去最后时间
                    $_show[] = $st2;
                    $st1 = $st2;
                    $i++;
                }
                if(isset($arg['limit'])){//个数限制
                    if(count($show) > $arg['limit']) return array(false,false);
                }
                return array($show,$_show);
                break;
            case 'm':
                $fm = isset($arg['fm']) ? $arg['fm'] : "Y-m";
                $i = 0;
                $st1 = $st;//小段开始时间
                while($st1 < $ed){
                    if($i == 0){//第一月特殊处理
                        $next_month = date('Y-m',strtotime("next month",$st1));
                        $st2 = strtotime($next_month);
                        $show[] = date($fm,$st1);
                        $_show[] = (int) $st1;
                    }else{
                        $st2 = strtotime(date('Y-m',strtotime("next month",$st1)));
                    }
                    $show[] = date($fm,$st2);
                    if($st2 > $ed) $st2 = (int) $ed;//如果所选时间大于最后时间，那么去最后时间
                    $_show[] = $st2;
                    $st1 = $st2;
                    $i++;
                }
                if(isset($arg['limit'])){//个数限制
                    if(count($show) > $arg['limit']) return array(false,false);
                }
                return array($show,$_show);
                break;
            case 'h':
                $fm = isset($arg['fm']) ? $arg['fm'] : "Y-m-d H";
                $i = 0;
                $st1 = $st;//小段开始时间
                while($st1 < $ed){
                    if($i == 0){//第个小时特殊处理
                        $st_r = date('Y-m-d H',$st1 + 3600);
                        $st2 = strtotime($st_r . ':00:00');
                        $show[] = date($fm,$st1);
                        $_show[] = (int) $st1;
                    }else{
                        $st2 = $st1 + 3600;
                    }
                    $show[] = date($fm,$st2);
                    if($st2 > $ed) $st2 = (int) $ed;//如果所选时间大于最后时间，那么去最后时间
                    $_show[] = $st2;
                    $st1 = $st2;
                    $i++;
                }
                if(isset($arg['limit'])){//个数限制
                    if(count($show) > $arg['limit']) return array(false,false);
                }
                return array($show,$_show);
                break;
            case 'y':
                $fm = isset($arg['fm']) ? $arg['fm'] : "Y";
                $i = 0;
                $st1 = $st;//小段开始时间
                while($st1 < $ed){
                    if($i == 0){//第一年特殊处理
                        $next_month = date('Y-m',strtotime("next year",$st1));
                        $st2 = strtotime($next_month);
                        $show[] = date($fm,$st1);
                        $_show[] = (int) $st1;
                    }else{
                        $st2 = strtotime(date('Y-m',strtotime("next year",$st1)));
                    }
                    $show[] = date($fm,$st2);
                    if($st2 > $ed) $st2 = (int) $ed;//如果所选时间大于最后时间，那么去最后时间
                    $_show[] = $st2;
                    $st1 = $st2;
                    $i++;
                }
                if(isset($arg['limit'])){//个数限制
                    if(count($show) > $arg['limit']) return array(false,false);
                }
                return array($show,$_show);
                break;
        }
        return array($show,$_show);
    }

    /**
     * 合并权限
     * @param $per_arr  array 参数必须为数组
     * @return array
     * @date
     */
    public static function implodePer($per_arr){
        $arr = array();
        if(!empty($per_arr)){
            foreach($per_arr as $per){
                $per = explode(',',$per);
                foreach($per as $row){
                    $t = explode('.',$row);
                    if($t[0] && $t[1]){
                        if(!isset($arr[$t[0]]) || !in_array($t[1],$arr[$t[0]])) $arr[$t[0]][] = $t[1];
                    }
                }
            }
        }
        return $arr;
    }

    /**
     * 合并权限,并取去有效值  格式：{"1":["robot.*","setting.view","setting.edit"],"2":["xkf.*"]}
     * @param $per_arr  array 参数必须为数组
     * @return array
     * @date
     */
    public static function unitPer($per_arr){

        $all_flag = false;//全局站点标识
        $has_all = array();//拥有模块的全部权限
        $has_some = array();//拥有模块的一部分权限
        $f1 = DATA_ROOT.'cache/menu/index.php';
        $f2 = DATA_ROOT.'cache/menu/reflect.php';
        $f3 = DATA_ROOT.'cache/menu/child_arr.php';
        //取索引,节点信息结构
        $index = include($f1);
        //根据字符串来查找到对应id
        $re = include($f2);
        //每一个 t_type != "3" 的子节点(下一级的子节点，不包括孙子辈以下的接口)
        //格式 array( 0 => array(1,90,200),1 => array(2,3,4), 2 =>(5,6,7)...)
        $child_array = include($f3);
//echo '<pre/>';
        //遍历数组
        if(!empty($per_arr)){
//            var_dump($per_arr);
            //json格式转数组
            foreach($per_arr as $row){
                $row = stripslashes($row);
                if(empty($row)) continue;//跳过空值
                $tmp = json_decode($row,true);
                if(!$tmp) continue;//空json字符串也跳过
                //先检查是否拥有全部站点权限
                if(isset($tmp[0]) && $tmp[0][0] == "*"){
                    $has_all = array('0' => '*');//全局权限
                    $all_flag = true;//标识全局已经OK
                    break;//拥有全部站点权限，停止遍历
                }
                if(isset($tmp[0])) unset($tmp[0]);
                foreach($tmp as $k => $o){//开始遍历所有后台权限
                    foreach($o as $r){
//                        var_dump($has_all);
                        //字符处理
//                        var_dump($r);
                        $id = $re[$k][$r];
//                        var_dump($id);
                        if(!$id){//$id 为空
                            continue;
                        }
//                        var_dump($id);continue;
                        $tmp_arr = explode(',',$index[$k][$id]['node']);//祖先到自己的节点
                        //先将这个节点的fid记录到has_some中去，整理去重
                        $fid = $index[$k][$id]['fid'];
                        $_id = $id;
                        $_fid = $fid;
                        //处理fid，将fid升级（如果全部子id都齐全了）
                        $break = true;
                        //回溯遍历处理祖先fid，看一下是否能升级，直到fid不能升级为止或者fid=0
                        while($_fid && $break){//fid不为0才处理,
                            if(!isset($has_some[$k][$_fid])) $has_some[$k][$_fid] = array();//没有这个组，先初始化
                            if(!in_array($_id,$has_some[$k][$_fid]) && 1){//这里是否要判断点是否已经被包括了
                                $has_some[$k][$_fid][] = $_id;
                                sort($has_some[$k][$_fid]);//排序一下，再来比较是否相等
                                $_check = array_diff($child_array[$k][$_fid],$has_some[$k][$_fid]);
                                if(empty($_check)){//已经包含了所有子节点，可以升级为has_all了
//                                    echo '---------------------------------';
//                                    var_dump($child_array[$k]);
//                                    echo '+++++++++++++++++++++';
//                                    var_dump($has_some[$k]);die;
                                    if(!isset($has_all[$k]) || !in_array($_fid,$has_all[$k])){//升级父节点权限
                                        $has_all[$k][] = $_fid;
                                        //回溯遍历
                                        $_id = $_fid;
                                        $_fid = $index[$k][$_id]['fid'];
                                    }else{
                                        $break = false;
                                    }
                                }
//                            var_dump($has_all);
//                            var_dump($has_some);die;
                            }else{//已经加载,跳过
                                $break = false;
                            }
                        }
//                        var_dump($has_all);
//                        var_dump((array_intersect($has_all[$k],$tmp_arr)))
//                      两个数组之间有没有交集，说明这个权限还没被被包括了;
//                        var_dump(array_intersect($has_all[$k],$tmp_arr));die;
//                        var_dump($has_all[$k]);
//                        var_dump($tmp_arr);die;
//                        var_dump($has_all[$k]);
//                        var_dump($k);
//                        var_dump($id);
//                        var_dump($tmp_arr);
//                        var_dump($tmp_arr);
                        if(!isset($has_all[$k])) $has_all[$k] = array();
                        $_check = array_intersect($has_all[$k],$tmp_arr);
//                        var_dump(in_array($id,$has_all[$k]));
                        if(empty($_check)){
                            if(!isset($has_all[$k]) || !in_array($id,$has_all[$k])){
                                $has_all[$k][] = $id;
                            }
                        }
                    }
                }
            }
        }
//        var_dump($has_all);

        if(!$all_flag && $has_all){//如果不是拥有全局权限
            //格式： array(1 => array(4,8,5,3,9),2 =>(7,3,10,22,33))
            $all_tmp = $has_all;
            $all = array();//统计全局权限
            foreach($has_all as $k => &$row){
                //排序去重
                asort($row);
//                var_dump($row);
                foreach($row as $id){
                    //根据child_id 来去重
                    if($index[$k][$id]['child_id']){
                        $child_tmp = explode(',',$index[$k][$id]['child_id']);
//                        if($id == 6) var_dump($child_tmp);
                        //循环遍历现有的点，去重
                        foreach($all_tmp[$k] as $n => $rr){
                            //先整理一下全局权限
//                            echo 'sss';
//                            var_dump($rr);
//                            var_dump($index[$k][$rr]['fid']);
//                            echo 'sss';
                            if($index[$k][$rr]['fid'] == 0){//顶级子节点
                                if(!in_array($rr,$all)) $all[] = $rr;
//                                var_dump($all);
//                                var_dump($child_array[0]);
//                                var_dump($_check);
                                $_check = array_diff($child_array[0],$all);
                                if(empty($_check)){//全局站点要已经凑够，将全局标识为true
                                    $all_flag = true;
                                    $all_tmp = array('0' => '*');//全局权限
                                    break 3;//跳出最外层循环
                                }
                            }
                            if(in_array($rr,$child_tmp)){//如果子id中有存在$all_per中的，可以干掉了
//                                echo 'hello';
                                unset($all_tmp[$k][$n]);//干掉
//                                var_dump($all_tmp);
                            }
                        }
                    }
                }

            }
            //将处理过的节点再赋给$has_all
            $has_all = $all_tmp;
        }
//        var_dump($has_all);die;
        //大功告成，输出最终的权限
        $per =  array();
        if($has_all){
            if($all_flag){//全局标识
                $per = '{"0":["*"]}';
            }else{
                foreach($has_all as $k => $row){
                    if($row){
                        foreach($row as $r){
                            if($index[$k][$r]['t_type'] == '3'){//功能块，要加上父id
                                $fid = $index[$k][$r]['fid'];
                                $per[$k][] = $index[$k][$fid]['tag'] . '.' . $index[$k][$r]['tag'];
                            }else{//其他标*号
                                $per[$k][] = $index[$k][$r]['tag'] . '.' . '*';
                            }
                        }
                    }
                }
                $per = json_encode($per);
            }
        }else{
            $per = '';
        }

        return $per;
    }


    //分离权限
    //有bug，后面再想想怎么搞

    public static function departPer($all,$now){
        if(!$all || !$now) return '';
        if($all == $now) return '';//两个权限完全相等
        $root = json_decode($all,true);
        $tmp = json_decode($now,true);
        foreach($root as $k => $row){
            if(isset($tmp[$k])){//设了对应平台的权限才进行操作
                foreach($row as $n => $r){
                    if(in_array($r,$tmp[$k])){
                        //如果在根权限刚好有这么一个值，干掉
                        unset($root[$k][$n]);
                        if(empty($root[$k])) unset($root[$k]); //干掉空数组
                    }
                }
            }else{
                continue;//跳过
            }
        }
        return json_encode($root);
    }




    /**
     * 合并权限成标准存储格式 例如：{"1":["robot.*","setting.view","setting.edit"],"2":["xkf.*"]}
     * @param array $arr
     * @return string
     * @date
     */
    public static function explodePer($arr = array()){
        $str = '';
        if($arr){
            foreach($arr as $key => $row){
                sort($row);//排一下序
                foreach($row as $r){
                    $str .= $key . '.' . $r . ',';
                }
            }
            $str = rtrim($str,',');
        }
        return $str;
    }


    /**
     * 求目标权限组跟权限组的差别
     * @param $val
     * @param $check
     * @return array|String
     * @date
     */
    public static function getPerDiff($val,$check){
        if(!is_array($val) || empty($check)) return array();
        if(!is_array($check)) $check = explode(',',$check);
        $in = array();
        foreach($check as $r){
            $flag = false;
            foreach($val as $row){
                $tmp = explode(',',$row);
                if(in_array($r,$tmp)){
                    $flag = true;
                    continue;
                }
            }
            if($flag) $in[] = $r;
        }
        $dif = fun::delSome($check,$in);
        return $dif;
    }

    //合并所有游戏站点
    public static function implodeSid($sid_arr = array()){
        $arr = array();
        if(!empty($sid_arr)){
            foreach($sid_arr as $sid){
                $sid = explode(',',$sid);
                foreach($sid as $row){
                    $t = explode('.',$row);
                    if($t[0] && $t[1]){
                        if(!isset($arr[$t[0]]) || !in_array($t[1],$arr[$t[0]])) $arr[$t[0]][] = $t[1];
                    }
                }
            }
        }
        return $arr;
    }

    //将数组形式站点转换成字符串行
    public static function explodeSid($arr = array()){
        $str = '';
        if($arr){
            foreach($arr as $key => $row){
                sort($row);//排一下序
                foreach($row as $r){
                    $str .= $key . '.' . $r . ',';
                }
            }
            $str = rtrim($str,',');
        }
        return $str;
    }

    /**
     * 组装添加逗号行数据
     * @param $val String 原来拥有的数据
     * @param $new mixed 新要添加的数据(如果参数是字符串，用逗号分隔)
     * @return String
     * @date
     */
    public static function appendSome($val,$new){
        if($val && !is_array($val)) $val = explode(',',$val);
        if(empty($new)) return is_array($val) ? implode(',',$val) : '';//空的新值直接返回
        if(!is_array($new)) $new = explode(',',$new);
        if(empty($val)) return is_array($new) ? implode(',',$new) : $new;//空的原值直接返回
        $arr = $val;
        foreach($new as $row){
            if(!in_array($row,$arr)) $arr[] = $row;
        }
        sort($arr);
        return implode(',',$arr);
    }

    /**
     * 组装删除逗号行数据
     * @param $val String 原来拥有的数据
     * @param $del mixed 要删除的数据(如果参数是字符串，用逗号分隔)
     * @param $sort bool 是否要排序，默认需要排序
     * @return String
     * @date
     */
    public static function delSome($val,$del,$sort = true){
        if(!is_array($val)) $val = explode(',',$val);
        if(empty($val)) return '';//原数组为空，返回
        if(empty($del))  return implode(',',$val);//空的新值直接返回
        if(!is_array($del)) $del = explode(',',$del);
        foreach($val as $k => $row){
            if(in_array($row,$del)) unset($val[$k]);
        }
        if($sort){
            sort($val);
        }
        return implode(',',$val);
    }

    /**
     * 求几个数组的差集
     * @param $val        array   二位数组
     * @param $check      array | string 一维或者逗号分隔
     * @return array|String
     * @date
     */
    public static function getDiff($val,$check){
        if(!is_array($val) || empty($check)) return array();
        if(!is_array($check)) $check = explode(',',$check);
        $in = array();
        foreach($check as $r){
            $flag = false;
            foreach($val as $row){
                $tmp = explode(',',$row);
                if(in_array($r,$tmp)){
                    $flag = true;
                    continue;
                }
            }
            if($flag) $in[] = $r;
        }
        $dif = fun::delSome($check,$in);
        return $dif;
    }

    /**
     * 处理映射数据(key为多个,逗号分隔）
     * @param $key         string      数据key,如： name1,name2
     * @param $arr         array       数据源，例如 array('name1' => '小明','name2' => '小强',...)
     * @return array|string            返回值，如： array('小明','小强')
     * @date
     */
    public static function reflect($key,$arr){
        if(!is_array($key)) $key = explode(',',$key);
        if(empty($key) || !is_array($arr))  return '';//空的新值直接返回
        $str = array();
        foreach($key as $row){
            if(isset($arr[$row])) $str[] = $arr[$row];
        }
        return $str;
    }

    //获取OA文件用户数据(包括第三方的)
    public static function getALLUserData(){
        $f = CFG_DIR . "/hr/user.php";
        if(is_file($f)){
            $ret = include($f);
            $_f = CFG_DIR . "/hr/user3.php";
            if(is_file($f)){
                $user3 = include($_f);
                $ret = $ret + $user3;
            }
        }else{//没有取查找数据库？？
            $ret = array();
        }
        return $ret;
    }

    //获取离职员工信息
    public static function getDelUser(){
        $f = CFG_DIR . "/hr/user_del.php";
        $ret = array();
        if(is_file($f)){
            $ret = include($f);
        }
        return $ret;
    }

    //获取OA文件用户组数据
    public static function getORGData(){
        if(!empty(fun::$cache['group'])) return fun::$cache['group'];
        $f = CFG_DIR . "/hr/group.php";
        if(is_file($f)){
            $ret = include($f);
            fun::$cache['group'] = $ret;
        }else{//没有取查找数据库？？
            $ret = array();
        }
        return $ret;
    }

    //获取后台拥有的用户信息（后面做优化处理）
    public static function getUserData(){
        $f = CFG_DIR . "/hr/user.php";
        if(is_file($f)){
            $ret = include($f);
        }else{//没有取查找数据库？？
            $ret = array();
        }
        return $ret;
    }

    //获取新后台第三方用户
    public static function getUser3Data(){
        $f = CFG_DIR . "/hr/user3.php";
        if(is_file($f)){
            $ret = include($f);
        }else{//没有取查找数据库？？
            $ret = array();
        }
        return $ret;
    }

    /**
     * 递归获取组织串
     * @param $id
     * @param array $data
     * @return array
     * @date
     */
    public static function getORGById($id,&$data = array()){
        $group = fun::getORGData();
        $l = count($data);
        if(isset($group[$id]) && $l < 10){//最多10级，怕数据有错
            $fid = $group[$id]['fid'];
            fun::getORGById($fid,$data);
        }
        if(isset($group[$id]['name'])) $data[] = $group[$id]['name'] ;
        return $data;
    }


    /**
     * curl 请求
     * @param $urlData           array('url'=>'http://','data'=>array('appid'=>234,'sid'=>5,...)
     * @param string $method     请求方式
     * @param int $timeout       超时时间
     * @param bool|false $return_org
     * @return mixed
     * @date
     */
    public static function curlHttp($urlData, $method='get',$timeout=10,$return_org = false) {
        $res['code'] = '100';
        $res['msg'] = '';
        if($return_org){
            $res['urlData'] = $urlData;
        }
        $r_stime = fun::getMicroTime();
        $SSL = substr($urlData['url'], 0, 8) == "https://" ? true : false;
        $method = strtolower($method);
        $ch = curl_init();

        if(!empty($urlData['data'])&&$method=='get'){
            $urlData['url'] = trim($urlData['url'],'?') . (strpos($urlData['url'],'?') !== false ? '&' : '?') . http_build_query($urlData['data']);
        }

        /**log**/
        $msg = $urlData['url']."\t";
        /**log**/
        curl_setopt($ch, CURLOPT_URL, $urlData['url']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        if($SSL){
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 信任任何证书
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1); // 检查证书中是否设置域名
        }


        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if(isset($urlData['json'])){
            $headers = array(
                "Content-type: application/json;charset='utf-8'",
            );
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        if($method=='post'){
            $data = empty($urlData['data']) ? array() : $urlData['data'];
            /**log**/
            $msg .= json_encode($data);
            /**log**/
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $red = curl_exec($ch);
        if (curl_errno($ch)>0){
            $res['code'] = '301';
            $res['msg'] = curl_error($ch);
            $res['data'] = '';
        } else {
            $res['code'] = '200';
            $res['msg'] = 'http connect ok';
            $res['data'] = $red;
        }
        curl_close($ch);
        $r_etime = fun::getMicroTime();
        $msg .= $r_etime-$r_stime;
//    echo "curl_request  '{$msg}'" . "\t";
        return $res;
    }

    /**
     * 微秒时间戳
     * @return float
     * @date
     */
    public static function getMicroTime(){
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

    /**
     * 遍历文件下以及下面所有文件
     * @param $dir
     * @param string $type
     * @param bool|false $base
     * @return array
     * @date
     */
    public static function scanAll($dir,$type = '',$base = false)
    {
        $list = array();
        $ret = array();
        $list[] = $dir;

        while (count($list) > 0)
        {
            //弹出数组最后一个元素
            $file = array_pop($list);
            //如果是目录
            if (is_dir($file))
            {
                $children = scandir($file);
                foreach ($children as $child)
                {
                    if ($child !== '.' && $child !== '..')
                    {
                        $list[] = $file.'/'.$child;
                    }
                }
            }else{
                if($type){
                    if(strchr($file,'.') == ('.' . $type)) {
                        if($base){
                            $ret[] = basename($file);
                        }else{
                            $ret[] = $file;
                        }

                    }
                }else{
                    if($base){
                        $ret[] = basename($file);
                    }else{
                        $ret[] = $file;
                    }
                }
            }
        }
        return $ret;
    }

    /**
     * 获取缓存里面用户的权限
     * @param $uid
     * @param string $key
     * @return bool
     * @date
     */
    public static function getUserPer($uid,$key = ''){
        $per_file = CACHE_DIR . "per.php";
        if(is_file($per_file)){
            $per = include($per_file);
            if(isset($per[$uid])){
                if($key && isset($per[$uid][$key])){
                    return $per[$uid][$key];
                }elseif($key  && !isset($per[$uid][$key])){
                    return false;
                }else{
                    return $per[$uid];
                }
            }else{
                return false;
            }
        }else{
            return false;
        }
    }


    /**
     * 获取游戏组信息
     * @param string $ids
     * @return array
     * @date
     */
    public static function getGameGroup($ids = '',$key = ''){
        if(!is_array($ids)) $ids = explode(',',$ids);
        $group_file = CACHE_DIR . "gameGroup.php";
        $ret = array();
        if(is_file($group_file)){
            $group = include($group_file);
            foreach($ids as $id){
                if(isset($group[$id])){
                    if($key && isset($group[$id][$key])){
                        $ret[] = $group[$id][$key];
                    }else{
                        $ret[$id] = $group[$id];
                    }

                }
            }

        }
        return $ret;
    }

    /**
     * 根据站点获取游戏名字
     * @param $sids                   string  游戏站点,格式为 ：1.93,1.117,2.1 ,如果gid传了,那么这里为 : 93,117
     * @param int $gid                 int    gid,可以为0,那么gid在在sids中
     * @param bool|false $full        bool    返回的类型
     * @return array
     * @date
     */
    public static function getGameName($sids,$gid = 0,$full = false){
        if(!is_array($sids)) $sids = explode(',',$sids);
        if($gid){//单个站点的
            $gid = array($gid);
        }else{//从sids里提取站点
            $tmp = array();
            foreach($sids as $row){
                $t = explode('.',$row);
                if(!isset($tmp[$t[0]]) || !in_array($t[1],$tmp[$t[0]])) $tmp[$t[0]][] = $t[1];
            }
            $_g = array();
            foreach($tmp as $k => $g){
                $_g[] = $k;
            }
            $gid = $_g;
            $sids = $tmp;
        }
        if($gid){
            foreach($gid as $g){
                $ckey = "gamesite/" . $g;
                $data = fun::cache($ckey);
                if($full){
                    $f = CACHE_DIR . 'game.php';
                    $game = include($f);
                    $gname = $game[$g]['name'];
                }
                if($data){
                    foreach($sids[$g] as $sid){
                        if(isset($data[$sid])){
                            if($full){
                                $ret[$g . '.' . $sid] = $gname . ')' . $data[$sid]['name'];
                            }else{
                                $ret[$g][$sid] = $data[$sid]['name'];
                            }
                        }
                    }
                }
            }
        }
        return $ret;
    }

    /**
     * 将秒数改成按时分秒显示
     * @param $second
     * @return string
     * @date
     */
    public static function  changTime($second){
        $str = '';
        if($second >= 3600){
            $str .= (int) ($second/3600) . "小时";
            $n = $second%(3600);
            if($n){
                $str .= (int) ($n/60) . "分钟" . $n%60 . "秒";
            }else{
                $str .= "0分钟0秒";
            }
        }elseif($second >= 60){
            $str .= (int) ($second/60) . "分钟" . $second%60 . "秒";
        }else{
            $str .= "{$second}秒";
        }
        return $str;
    }

    //RTX接口
    function sendRTX(){
        $title = fun::get('title');
        $content = fun::get('content');
        $uids = fun::get('uids');
        $arr = explode(',',$uids);
        $data = array();
        if(!$title || !$content || empty($arr)){
            $data['code'] = 100;
            $data['msg'] = 'no condition!';
            die(json_encode($data));
        }
//    var_dump($arr, $title, $content);
        o::msg()->rtxn($arr, $title, $content);
        $data['code'] = 200;
        $data['msg'] = 'send ok';
        die(json_encode($data));
    }

    //发送RTX信息
    public static function RTX($title,$content,$uids){
        $url = "http://kf.boyaagame.com/api/client.php?f=sendRTX";
        $data = array(
            "url" => $url,
            "data" => array(
                "title" => $title,
                "content" => $content,
                "uids" => $uids,
            )
        );
        $ret = fun::curlHttp($data);
        return $ret;
    }
}