<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class mdb{

    public $pageName = 'page';
    public $pageSizeName = 'pagesize';
    public $value;
    public $diysql;
    public $sql;
    public $rs;
    public $allnum = -1;
    public $err;
    private $limit;
    private $_conn;
    private $_select = '*';
    private $_table;
    private $_where;
    private $_order;
    private $_limit;
    private $_dbfix;
    private $_ci;

    public function __construct($dbfix = '',$db = '')
    {
        $this->_dbfix = $dbfix;
        if($db) $this->_ci = $db;
    }

    //o::db会重置条件
    public function reset($_table){
        $this->_select = '*';
        $this->_where = '';
        $this->_order = '';
        $this->_limit = '';
        $_table && $this->table($_table);
        return $this;
    }

    //链试SQL语句
    public function select($s = "*"){
        if($s) $this->_select = $s;
        return $this;
    }
    public function table($t = '',$dbfix=''){
        if($t) $this->_table = $dbfix ? $dbfix . $t : $this->_dbfix.str_replace($this->_dbfix,'',$t) ;
        return $this;
    }
    public function data($sql = ''){
        $this->rs = $this->fetchAll($this->getSql($sql));
        return $this->rs;
    }

    public function one($sql = '', $k = ''){
        $this->rs = $this->fetch($this->getSql($sql));
        if($k){
            return $this->rs ? $this->rs[$k] : false;
        }else{
            return $this->rs;
        }
    }
    public function order($o = '', $b = 'ASC'){
        $order = strtoupper(fun::get('s_order', $b));
        $order = $order === 'DESC' ? $order : 'ASC';
        $key = fun::get('s_key');
        if($order && $key){
            if(strpos($key,'|') !== false){
                $key = explode('|', $key);
                $key = $key[0];
            }
            $this->_order = ' ORDER BY `' . $key . '` ' . $order;
        }elseif($o){
            $this->_order = " ORDER BY " . $o;
        }
        return $this;
    }
    public function where($w = '', $so = ''){
        if($w || is_array($so)){
            !$w && $w = array();
            if(!is_array($w)) $w = array($w);
            if(is_array($so)){
                foreach ($so as  $k => $t){
                    $v = fun::get($k);
                    if($v == '') continue;
                    if(strpos($k,'|') !== false){//or查询
                        $arr = explode('|', $k);
                        $temp = array();
                        foreach ($arr as $n){
                            $temp[] = $this->whereone($n, $v, $t);
                        }
                        $w[] = '('. join(' OR ', $temp) .')';
                    }else{//单一and查询
                        $w[] = $this->whereone($k, $v, $t);
                    }
                }
            }
            $w = join(' AND ', $w);
            $w && $this->_where = ' WHERE '.$w;
        }
        return $this;
    }
    private function whereone($k, $v, $t = false){
        $sign = $_GET[$k.'_sign'];
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
    public function limit($l = ''){//若不跟参，则自动使用page、pageSize对应值来设置limit
        if($l){
            $this->_limit = " LIMIT ".$l;
        }else{
            $page = $this->getInt($this->pageName,1);
            $pageSize = $this->getInt($this->pageSizeName,15);
            $start = $pageSize * ($page-1);
            $this->_limit = " LIMIT $start,$pageSize";
        }
        return $this;
    }
    public function getLimit($type = false){//若不跟参，则自动使用page、pageSize对应值来设置limit
        $page = $this->getInt($this->pageName,1);
        $pageSize = $this->getInt($this->pageSizeName,15);
        $start = $pageSize * ($page-1);
        if($type){
            return array($start,$pageSize);
        }else{
            return " LIMIT $start,$pageSize";
        }
    }

    public function count($sql = ''){
        $sql = $sql ? $sql : "SELECT count(*) FROM ". $this->_table . $this->_where;
        return $sql;
        $this->allnum = $this->fetch($sql,MYSQL_NUM);
        $this->allnum = $this->allnum[0];
        return $this;
    }

    //获取某个字段的值
    public function value($id = 0){
        $type = is_numeric($id) ? MYSQL_NUM : MYSQL_ASSOC;
        $rs = $this->fetch("",$type);
        $this->rs = isset($rs[$id]) ? $rs[$id] : "";
        return $this->rs;
    }

    //以SQL获取某个字段的值
    public function get($sql){
        $rs = $this->fetch($sql, MYSQL_NUM);
        $this->rs = isset($rs[0]) ? $rs[0] : "";
        return $this->rs;
    }

    //获取SQL
    public function getSql($sql = ''){
        $this->sql = $sql ? $sql : "SELECT ".$this->_select." FROM ".$this->_table.$this->_where.$this->_order.$this->_limit;
        return $this->sql;
    }

    public function query($sql, $type = true){
        $this->err = '';
//        ($this->result = @mysqli_query($sql, $this->_conn)) or ($this->err = $this->errorInfo($type,$sql));
        ($this->result = $this->_ci->query($sql)) or ($this->err = $this->errorInfo($type,$sql));
        return $this->err ? false : $this->result;
    }

    public function exec($sql, $log = true, $die = true){
        $this->sql = $sql;
        $suc = $this->query($sql, false);
        if($suc){
            if($log){
                $s = strtolower($this->sql);
                $s = str_replace('insert into ', '', $s);//插入语句
                $s = str_replace('insert ignore into ', '', $s);
                $s = str_replace('update ', '', $s);//更新语句
                $s = str_replace('delete from ', '', $s);
                $s = str_replace('`', '', $s);
                $s = trim($s);
                $s = explode(' ', $s);
                $s = $s[0];
                fun::log($this->sql, 'sql/'. $s .'/');
            }
            return true;
        }else if($log){
            fun::log($this->err, 'db', $die);
            if($die) exit();
            return false;
        }
    }

    public function execNum(){//返回操作影响数据行数
        return mysqli_affected_rows();
    }

    public function fetch($sql = "", $type = MYSQL_ASSOC){
        if(!$sql) $sql = $this->getSql();
        $result = $this->query($sql);
        $array = @mysqli_fetch_array($result, $type);
        return is_array( $array) ? $array : array();
    }

    public function fetchAll($sql = "", $type = MYSQL_ASSOC){
        if(!$sql) $sql = $this->getSql();
        $result = $this->query($sql);
        while ($array = @mysqli_fetch_array($result, $type)) {
            $temp[] = $array;
        }
        return (array)$temp;
    }

    public function getOne($sql, $type = MYSQL_ASSOC){//兼容业务方法
        return $this->fetch($sql, $type);
    }
    public function getAll($sql, $type = MYSQL_ASSOC){//兼容业务方法
        return $this->fetchAll($sql, $type);
    }

    //最后插入ID
    public function lastId($table = ''){
        return mysqli_insert_id($this->_conn);
    }

    //转换时间格式
    public function setTime($key,$s = DATESTR){
        if(isset($this->rs[0])){//data
            foreach ($this->rs as &$rs){
                if($rs[$key]) $rs[$key] = date($s,$rs[$key]);
            }
        }else if(isset($this->rs[$key])){//one
            if($this->rs[$key]) $this->rs[$key] = date($s,$this->rs[$key]);
        }
        return $this;
    }

    //设置默认排序ID
    public function setSortid(){
        $sql = "UPDATE ". $this->_table ." SET sortid=id WHERE sortid=0 ORDER BY id DESC limit 1";
        $this->exec($sql);
    }

    //编码转换
    public function iconv($arr, $old = 'gb2312', $now = 'utf-8'){
        if($this->rs && is_array($this->rs[0])){//data
            foreach ($this->rs as &$rs){
                $this->iconvExec($arr,$old,$now,$rs);
            }
        }else{//one
            $this->iconvExec($arr,$old,$now,$this->rs);
        }
    }
    private function iconvExec($arr,$old,$now,&$rs){
        if(is_array($arr)){
            foreach ($arr as $key){
                $rs[$key] = iconv($old,$now,$rs[$key]);
            }
        }else{
            $rs[$arr] = iconv($old,$now,$rs[$arr]);
        }
    }

    //取单个数字值
    public function getInt($name, $def = 0){
        $v = fun::get($name, $def);
        return (int)$v;
    }

    //通过字段返回表单值
    public function setValue($field){
        $this->value = array();//重置
        if(!$field) return;
        $field = explode(',', $field);
        foreach ($field as $k){
            if(isset($_GET[$k]) || isset($_POST[$k])) $this->value[$k] = fun::get($k);
        }
    }

    //合服多语言
    public function translate($arr = '', $type = 'encode', $k = 'translate'){
        if(is_array($arr) && $arr){//提交时$db->translate(array('title','descr'));
            foreach(o::$cfg['langfixs'] as $lan){
                if($lan == o::$cfg['langfix']) continue;
                foreach($arr as $n){
                    if($v = cms::get($n.'_'.$lan)) $translate[$n.'_'.$lan] = $v;
                }
            }
            $this->value[$k] = $translate ? ($type == 'encode' ? $this->encode($translate) : cms::json($translate)) : '';
        }else{//编辑的时候解码$db->translate();
            $this->rs = $this->translateOne($this->rs, $type, $k);
        }
    }
    public function translateOne($rs, $type = 'encode', $k = 'translate'){
        if($val = $rs[$k]){
            unset($rs[$k]);
            $val = $type == 'encode' ? $this->decode($val) : json_decode($val,true);
            if($val) $rs = array_merge($rs, (array)$val);
        }
        return $rs;
    }

    //多语言 弃用，推荐translate 参见app:feed gift
    public function langfixs($name, $names = ''){
        o('msg')->rtxn(438, '废弃函数', __FILE__.':'.__LINE__.'=>langfixs');
        if($names === true){//编辑的时候，解码
            if($val = $this->rs[$name]){
                $val = $this->decode($val);
                $this->rs = array_merge($this->rs, (array)$val);
            }
        }else{
            $arr = (array)o::$cfg['langfixs'];
            if(!$arr) return;
            $ret = $names ? array() : $this->value;
            foreach ($arr as $k){
                $k = $name .'_'. $k;
                if(isset($_GET[$k]) || isset($_POST[$k])) $ret[$k] = fun::get($k);
            }
            $n = $ret[$name .'_'. o::$cfg['langfix']];
            $ret[$name] = $n ? $n : fun::get($name);
            if($names){//将多语言写到一个字段中
                unset($ret[$name]);
                $this->value[$names] = $this->encode($ret);
            }else{
                $this->value = $ret;
            }
        }
    }

    //更新操作
    public function update($where, $log = true, $getsql = false){
        $sql = "UPDATE ". $this->_table ." SET ". $this->getUpdateValue() ." WHERE ". $where;
        if($getsql) return $sql;
        return $this->exec($sql, $log, false);
    }

    //插入新数据
    public function insert($log = true, $getsql = false){
        $sql = "INSERT INTO ". $this->_table ." SET ". $this->getUpdateValue();
        if($getsql) return $sql;
        return $this->exec($sql, $log, false);
    }

    //删除操作
    public function delete($where, $log = true, $getsql = false){
        $sql = "DELETE FROM ". $this->_table ." WHERE ". $where;
        if($getsql) return $sql;
        if($log){
            $old = $this->select('*')->where($where)->data();
            cms::log($sql."\t".cms::json($old), 'sql_delete/'.$this->_table);//记录被删除的数据
        }
        return $this->exec($sql, $log, false);
    }

    //包括所有子栏目 $arr：条件数组
    public function setCid($arr = ''){
        $cid = $this->getInt("cid",0);
        $where = array();
        if($cid){
            $cid = $this->get('SELECT child FROM cms_applist WHERE id='.$cid);
            $where[] = strpos($cid,",") !== false ? "cid IN ($cid)" : "cid=".$cid;
        }
        if($arr && count($arr)) $where = array_merge($where,$arr);
        $this->where(join($where," AND "));
        return $this;
    }

    //回收站
    public function deled($name, $child = false, $showdel = true){
        $a = array();
        $v = fun::get($name);
        if($v === 'deled'){//回收站
            $a[] = 'del=1';
        }elseif($v || $v == '0'){
            if($child === 'cids'){//多分类
                $a[] = fun::cidstr('sql', $v);
            }else{
                if($child && $v) $child = $this->get('SELECT child FROM cms_applist WHERE fid='.$v);
                if($child && $v){
                    $a[] = $name .' IN ('. $child .')';
                }else{
                    $a[] = $name ."='$v'";
                }
            }
            if(!$showdel) $a[] = 'del=0';//正常数据是否显示已删除数据
        }
        return $a;
    }

    //复杂查询时使用 参见评论应用 comment
    public function limitData($sql){
        $this->limit();
        $sql .= $this->_limit;
        $this->data($sql);
    }

    //查询返回
    public function getRet($list = true, $sql = false, $other = array()){
        if($list){
            if($this->allnum === 'count'){
                $this->allnum = count($this->rs);
            }else{
                if($this->allnum < 0) $this->count();
            }
            $ret['num'] = (int)$this->allnum;
            $ret['loop'] = $this->rs;
            if($sql) $ret['sql'] = $this->sql;
        }else{
            $ret = $this->rs;
        }
        if($other) $ret = array_merge($ret, $other);
        if($this->_editLock) $ret['_editLock'] = $this->_editLock;
        if($this->_editLockUid) $ret['_editLockUid'] = $this->_editLockUid;
        if($this->_editLockId) $ret['_editLockId'] = $this->_editLockId;
        return $ret;
    }

    //输出JSON
    public function json($list = true, $sql = false, $other = array()){
        $ret = $this->getRet($list, $sql, $other);
        fun::callback($ret);
    }

    //通过字段和值返回正确的SQL语句片段
    private function getUpdateValue(){
        $tempV = array();
        if($this->value && is_array($this->value)){
            foreach($this->value as $k => $v){
                $tempV[] = "`{$k}`='{$v}'";
            }
        }
        if($this->diysql){
            $tempV[] = $this->diysql;
            $this->diysql = '';
        }
        return join(",",$tempV);
    }

    //向指定字段添加指定值，参见客服系统工单app:order
    public function append($k = 'uids', $uid = 0){
        $uid = $uid ? $uid : o::$admin['id'];
        $old = "REPLACE($k,',$uid,',',')";//先替换，防止重复添加
        $this->diysql = "$k=CONCAT($old,'$uid,')";//追加
    }

    public function errorInfo($die = true, $msg = ''){
        $msg = 'Date:'.date('Y-m-d H:i:s') .", Errno:". mysqli_errno() .', Error:'. mysqli_error() . $msg;
        if($die){
            die($msg);
        }else{
            fun::log($msg, 'sqlError');
            return $msg;
        }
    }

    //json+base64
    public function encode($v){
        if(!$v) return '';
        if(is_array($v)) $v = fun::json($v);
        return base64_encode($v);
    }
    public function decode($v, $d = false){
        if(!$v) return array();
        $v = base64_decode($v);
        if($d) $v = stripslashes($v);
        return json_decode($v, true);
    }
    //干掉转义
    public function stripslashes($d){
        if(is_array($d) && $d){
            foreach($d as $k => $v){
                $d[$k] = $this->stripslashes($v);
            }
            return $d;
        }else if($d && is_string($d)){
            return stripslashes($d);
        }else{
            return $d;
        }
    }
}