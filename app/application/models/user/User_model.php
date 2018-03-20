<?php

/**
 * 用户model
 * Class Robot_model
 * @Useror harvey
 * @Date: 16-12-29
 * @license  http://www.boyaa.com/
 */
class User_model extends Bmax_Model {
    private $ndb;
    private $odb;
    private $m;
    private $_game;
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->m = new mdb('bay_',$this->db);//DB助手
    }

    /**
     * 获取旧DB连接
     * @date
     */
    private function getODb(){
        if(!$this->odb){
            $this->odb = $this->load->database('online',true);
        }
    }

    //获取分页总条数
    public function countPage($db = ''){
        $sql = $this->m->count();
        if($db){
            $query = $db->query($sql);
        }else{
            $query = $this->db->query($sql);
        }
        $rel = $query->result_array();
        return $rel[0]['count(*)'];
    }

    //根据条件获取一个申请列表
    //未审核：status = 0 ,审核通过：status = 1,审核不通过：status = 2, 默认取全部
    public function getApply($val = array(),$where = '',$limit = true){
        $w = $this->_getValue($where,$val);
//        if(isset($val['status'])) $w['status'] = $val['status'];
        if(isset($w['status'])){
            if(strpos($w['status'],',') !== false){
                $this->db->where_in('status',explode(',',$w['status']));
            }else{
                $this->db->where('status',$w['status']);
            }
        }
//        if(isset($val['id'])) $w['id'] = $val['id'];
        if(isset($w['id'])){
            if(strpos($w['id'],',') !== false){
                $this->db->where_in('id',explode(',',$w['id']));
            }else{
                $this->db->where('id',$w['id']);
            }
        }
        if(isset($w['opper'])){
            if(is_numeric($w['opper'])){
                $uid = array($w['opper']);
            }else{
                $uid = $this->getUidByName($w['opper']);
                if(empty($uid)){
                    fun::codeBack("找不到名字为'{$w['opper']}'这么一个人~");
                }
            }
            $this->db->where_in('editor',$uid);
        }
        if(isset($w['apper'])){
            if(is_numeric($w['apper'])){
                $uid = array($w['apper']);
            }else{
                $uid = $this->getUidByName($w['apper']);
                if(empty($uid)){
                    fun::codeBack("找不到名字为'{$w['opper']}'这么一个人~");
                }
            }
            $this->db->where_in('uid',$uid);
        }
        if(isset($w['st']) && isset($w['ed'])){
            if(!is_numeric($w['st'])){
                $w['st'] = strtotime($w['st']);
            }
            $this->db->where('atime >',$w['st']);
            if(!is_numeric($w['ed'])){
                $w['ed'] = strtotime($w['ed']);
            }
            $this->db->where('atime <',$w['ed']);
        }
        $this->db->from('apply');
        if($limit){//分页
            $num = $this->db->count_all_results('',false);
            $this->getLimit($this->db);
        }else{
            $num = 0;
        }
        $rel = $this->db->get()->result_array();
        return  (array($rel,$num));
    }

    //根据条件获取用户信息
//    public function getUser($field = '',$limit = true){
    public function getUser($val = array(),$field = '',$limit = true){
        $w = $this->_getValue($field,$val);
        if(isset($w['cname'])){
            $this->db->where('cname',$w['cname']);
        }
        if(isset($w['uid'])){
            if(strpos($w['uid'],',') !== false){
                $this->db->where_in('id',explode(',',$w['uid']));
            }else{
                $this->db->where('id',$w['uid']);
            }
        }
        $this->db->from('users');
        if($limit){//分页
            $num = $this->db->count_all_results('',false);
            $this->getLimit($this->db);
        }else{
            $num = 0;
        }
        $rel = $this->db->get()->result_array();
        return  (array($rel,$num));
    }

    public function getPer($uid){
        $rel = $this->getUser(array('uid' => $uid),array(),false);
        $rel = $rel[0];
        $ret = array();
        if($rel){
            $arr = array(
                'bsid' => $rel[0]['bsid'],
                'psid' => $rel[0]['psid'],
                'ksid' => $rel[0]['ksid'],
                'rsid' => $rel[0]['rsid']
            );
            $f = CACHE_DIR . '/game.php';
            $game = include($f);
            foreach($arr as $key => $row){
                $a = fun::implodeSid(array($row));
                foreach($game as $k => $r){
                    $data = $a[$k] ? $a[$k] : array();
                    $ret[$key][$k] = $this->dealSid($data,$k);
                }
            }
        }
        return $ret;
    }

    //组装sid数据
    public function dealSid($sid_arr,$key){
        $arr = array();
        if($sid_arr){
            foreach($sid_arr as $row){
                $arr[] = $key . '.' . $row;
            }
        }
        return $arr;
    }

    //新增或审核一条申请
    public function setUser($field,$where = '',$set = array()){
        $this->m->table('User')->setValue($field);
        if($set && is_array($set)){//补充值
            foreach($set as $key => $row){
                $this->m->value[$key] = $row;
            }
        }

        if(!$where){//新增
            $this->m->value['atime'] = time();
            $this->m->value['uid'] = $this->_uid;
            $a_type = fun::get("a_type");
            if($a_type) $this->m->value['a_type'] = $a_type;
            $sql = $this->m->insert(true,true);
            $ret = $this->db->query($sql);
            if($ret){
                return true;
            }else{
                return false;
            }
        }else{//更新或删除
            $del = (int) fun::get('del');
            if($del == 1){//删除操作,事务
                $id = fun::get('id');
                $this->db->trans_start();//事务开启
                $sql = $this->m->update($where,true,true);
                $this->db->query($sql);
                //删除改ID下面所有子ID
                $sql = "SELECT * FROM `bay_menu` WHERE id = {$id}";
                $rel = $this->db->query($sql)->result_array();
                if($rel){
                    $rel = $rel[0];
                    $node = $rel['node'] ? $rel['node'] : '';
                    $child_id = $rel['child_id'] ? $rel['child_id'] : '';

                    if($child_id){
                        $this->_delChild($child_id);
                        $child_id = $child_id . ",{$id}";//删完子节点，再加上自己，去处理祖先节点
                    }else{
                        $child_id = $id;
                    }
                    if($node) $this->_dealNode($node,$child_id);
                }
                $this->db->trans_complete();//事务提交
            }else{//更新
                $sql = $this->m->update($where,true,true);
                $rel = $this->db->query($sql);
            }
            if(!$rel) return false;
        }
        return true;
    }

    //拉取所有游戏
    public function game(){
        if(!isset($this->_game)){
            $f = DATA_ROOT.'cache/game.php';
            if(is_file($f)){
                $this->_game = include($f);
            }else{
                $rel = $this->db->select('id,name, api, mid_url')->get('game')->result_array();
                //写缓存
                $ret = array();
                foreach($rel as $rs){
                    $id = $rs['id'];
                    unset($rs['id']);
                    $ret[$id] = $rs;
                }
                $out = load_class('OP','helpers');
                $out->php($f,$ret);
                $this->_game = $ret;
            }
        }
        return $this->_game;
    }

    public function site($gid){
        $dir = 'gamesite/';
        if($gid === 'clear') return fun::cache($dir, 'clear');
        $ckey = $dir . $gid;
        $data = fun::cache($ckey);
        if(!$data){
            $cfg = $this->game();
            if($api = $cfg[$gid]['api']){
                $data = file_get_contents($api);
                $data = (array)json_decode($data, true);
            }else{
                return array();
            }
            $temp = array();
            foreach($data as $rs){
                $id = $rs['id'];
                unset($rs['id']);
                if(isset($rs['langs']) && $rs['langs'] && is_string($rs['langs'])) $rs['langs'] = explode(',', $rs['langs']);//支持逗号分隔和数组
                $temp[$id] = $rs;
            }
            $data = $temp;
            fun::cache($ckey, $data);
        }
        return $data;
    }


}