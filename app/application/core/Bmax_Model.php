<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Bmax_Model extends CI_Model{//继承超级类，留作扩张用
    public $pageName = "page";//页数参数名
    public $pageSizeName = "pagesize";//条数参数名
    protected $_uid;//当前员工工号
    protected $_gid;//当前操作对应的gid
    protected $_sid;//当前操作对应的sid
    protected $_user = array();//用户信息(登录过后台用户)
    protected $_au = array();//全公司用员工
    public function __construct()
    {
        parent::__construct();
        $this->_uid = CS::$admin['id'];
        $this->_gid = fun::getInt('gid');
        $this->_sid = fun::getInt('sid');
    }

    //获取登录用户信息数据
    protected function getUserData(){
        $f = CFG_DIR . '/usr.php';
        if(empty($this->_user)){
            if(is_file($f)){
                $this->_user = include_once($f);
            }else{//生成缓存文件
                $sql = "select * from bay_user";
                $rel = $this->db->query($sql);
                if($rel){
                    $arr = array();
                    foreach($rel as $k => $row){
                        $arr['uid'] = $row;
                    }
                }
            }
        }
    }

    //获取全公司员工信息
    protected function getALLUser(){
        $f = CFG_DIR . '/hr/user.php';
        if(empty($this->_au)){
            if(is_file($f)){
                $this->_au = include_once($f);
            }else{//警报//写错误日志
               fun::log("获取全公司用户信息错误！");
            }
        }
    }

    //通过名字获取员工pid
    protected function getUidByName($name){
        $sql = "select uid from  bay_users where cname = '{$name}'";
        $rel = $this->db->query($sql)->result_array();
        if($rel){
            $uid_arr = array();
            foreach($rel as $row){
                $uid_arr[] = $row['uid'];
            }
            return implode(',',$uid_arr);
        }else{
            return '';
        }
    }

    //获取limit参数或者语句
    protected function getLimit($db ='',$pageName = '',$pageSizeName = '',$type = 0){//若不跟参，则自动使用page、pageSize对应值来设置limit
        $page = $pageName ? $pageName : $this->pageName;
        $page = (int) fun::get($page,1);
        $pageSizeName = $pageSizeName ? $pageSizeName : $this->pageSizeName;
        $pageSize = (int) fun::get($pageSizeName,15);
        $start = $pageSize * ($page-1);
        if($db){
            $db->limit($pageSize,$start);
        }elseif($type == 1){
            return " LIMIT $start,$pageSize";
        }
        return array($start,$pageSize);
    }

    /**
     * 获取where条件值
     * @param array $where  字段名
     * @param array $val     传过来的值，如果有跟$where一样的字段名，优先取这里的
     * @param string $db     ci框架db，如果有传，往里面set值
     * @param int $w         0: 默认set值（如果set了全部值，注意upadte会有主键冲突的可能），1：where值
     * @return array
     * @date
     */
    protected function _getValue($where = array(),$val = array(),$db = '',$w = 0){
        if(!is_array($where)){
            $where = explode(',',$where);
        }
        if(!empty($val)){
            $key = array_keys($val);
            $where = array_unique(array_merge($where,$key));
        }
        $arr = array();
        if(!empty($where)){
            foreach($where as $row){
                if(isset($val[$row])){//首取$val里面的值
                    if($db){
                        if($w){
                            $db->where($row,$val[$row]);
                        }else{
                            $db->set($row,$val[$row]);
                        }
                    }
                    $arr[$row] = $val[$row];
                }else{
                    $t = fun::get($row,false);
                    if($t !== false){
                        if($db){
                            if($w){
                                $db->where($row,$t);
                            }else{
                                $db->set($row,$t);
                            }
                        }
                        $arr[$row] = $t;
                    }
                }
            }
        }
        return $arr;
    }
}