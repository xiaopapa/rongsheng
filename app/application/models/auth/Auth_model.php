<?php

/**
 * 权限model
 * Class Robot_model
 * @author harvey
 * @Date: 16-12-29
 * @license  http://www.boyaa.com/
 */
class Auth_model extends Bmax_Model {
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

    /**
     * 获取分页总条数
     * @param string $db
     * @return mixed
     * @date
     */
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

    /**
     * 根据条件获取一个申请列表
     * 未审核：status = 0 ,审核通过：status = 1,审核不通过：status = 2, 默认取全部
     * @param array $val 指定值
     * @param string $field  灵活获取值
     * @param bool|true $limit 是否按分页来获取
     * @return array
     * @date
     */
    public function getApply($val = array(),$field = '',$limit = true, $name = true){
        $w = $this->_getValue($field,$val);
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
        if(isset($w['search'])){
            if(is_numeric($w['apper'])){
                $uid = array($w['apper']);
            }else{
                $uid = $this->getUidByName($w['search']);
                if(empty($uid)){
                    fun::codeBack("找不到名字为'{$w['opper']}'这么一个人~");
                }
            }
            $sql = " (uid in ({$uid}) or editor in ({$uid}))";
            $this->db->where($sql);
        }
        if(isset($w['st'])){
            if(!is_numeric($w['st'])){
                $w['st'] = strtotime($w['st']);
            }
            $this->db->where('atime >',$w['st']);
        }
        if(isset($w['st']) && isset($w['ed'])){
            if(!is_numeric($w['ed'])){
                $w['ed'] = strtotime($w['ed']);
            }
            $this->db->where('atime <',$w['ed']);
        }
        $this->db->from('apply');
//        $this->db->join('users', 'apply.uid = users.uid', 'left');
        if($limit){//分页
            $num = $this->db->count_all_results('',false);
            $this->getLimit($this->db);
        }else{
            $num = 0;
        }
        $rel = $this->db->get()->result_array();
        if($rel && $name){
            $user = fun::getALLUserData();
            foreach($rel as &$row){
                $row['apply_name'] = isset($user[$row['uid']]) ? $user[$row['uid']]['name'] : '';
                $row['check_name'] = isset($user[$row['editor']]) ? $user[$row['editor']]['name'] : '';
                $row['org'] = implode('/',fun::getORGById($row['org_id']));
            }
        }
        return  (array($rel,$num));
    }


    /**
     * 新增或审核一条申请
     * @param $field
     * @param string $where
     * @param array $set
     * @return mixed
     * @date
     */
    public function addApply($field,$where = '',$set = array()){
        $this->m->table('apply')->setValue($field);
        if($set && is_array($set)){//补充值
            foreach($set as $key => $row){
                $this->m->value[$key] = $row;
            }
        }
        $this->m->value['atime'] = time();
        $this->m->value['uid'] = $this->_uid;
//        $this->m->value['org_id'] = $this->_org;
        //暂时方案
        $f = CFG_DIR . 'hr/user.php';
        $user= include_once($f);
        $org_id = (int) $user[$this->_uid]['pid'];//公司组织ID
        if($org_id) $this->m->value['org_id'] = $org_id;


        $a_type = fun::get("a_type");
        if($a_type) $this->m->value['a_type'] = $a_type;
        $sql = $this->m->insert(true,true);
        $ret = $this->db->query($sql);
        return $ret;
    }

    /**
     * 审核一条申请
     * 通过：status =1  不通过：status =2
     * @param $id
     * @param $status
     * @param $field
     * @return bool|string
     * @date
     */
    public function checkApply($id,$status,$field){
        $w = $this->_getValue($field);//拉取值
        $rel = $this->getApply(array('id' => $id));
        $rel = $rel[0];
        if(empty($rel)) return "申请ID出错";
        $uid = $rel[0]['uid'];
        $data = array(
            'status' => $status,
            'statement' => $w['statement'] ? $w['statement'] :'',
            'etime' => time(),
            'editor' => $this->_uid
        );
        if($status == 1){//审核通过
            //拉取角色
            $r_per = '';
            if($w['role_id']){
                $re = $this->getRole(array('id' => $w['role_id']),array(),false);
                $re = $re[0];
                if(empty($re)) return "没有这样一个角色ID";
                $per_arr = array();
                foreach($re as $row){
                    if($row['per']) $per_arr[] = $row['per'];
                }
                if($per_arr) $r_per = fun::unitPer($per_arr);
            }
            //拉取组
            if($w['group_id']){
                $re = $this->getGroup($w['group_id'],array(),false);
                $re = $re[0];
                if(!$re) return "没有这样一个组ID";
                $bsid_arr = array();
                $psid_arr = array();
                $ksid_arr = array();
                $rsid_arr = array();
                foreach($re as $row){
                    $bsid_arr[] = $row['bsid'];
                    $psid_arr[] = $row['psid'];
                    $ksid_arr[] = $row['ksid'];
                    $rsid_arr[] = $row['rsid'];
                }
            }

            if(isset($w['only_bsid'])){//只取选出的
                $bsid = $this->implodeSid(array($w['bsid']));
            }else{
                $bsid_arr[] = $w['bsid'];
                $bsid = $this->implodeSid($bsid_arr);
            }
            if(isset($w['only_psid'])){//只取选出的
                $psid = $this->implodeSid(array($w['psid']));
            }else{
                $psid_arr[] = $w['psid'];
                $psid = $this->implodeSid($psid_arr);
            }
            if(isset($w['only_ksid'])){//只取选出的
                $ksid = $this->implodeSid(array($w['ksid']));
            }else{
                $ksid_arr[] =$w['ksid'];
                $ksid = $this->implodeSid($ksid_arr);
            }
            if(isset($w['only_rsid'])){//只取选出的
                $rsid = $this->implodeSid(array($w['rsid']));
            }else{
                $rsid_arr[] =$w['rsid'];
                $rsid = $this->implodeSid($rsid_arr);
            }
            $bsid = $this->explodeSid($bsid);
            $psid = $this->explodeSid($psid);
            $ksid = $this->explodeSid($ksid);
            $rsid = $this->explodeSid($rsid);
            $data['gper'] = $r_per;
            $data['gsid'] = json_encode(array('bsid' => $bsid,'psid' => $psid,'ksid' => $ksid,'rsid' => $rsid));
            $_data = array();
            if(isset($w['nickname'])) $_data['nickname'] = $w['nickname'];
            if(isset($w['max'])) $_data['max'] = $w['max'];
            if(isset($w['pic'])) $_data['pic'] = $w['pic'];
            if(isset($w['role_id'])) $_data['role'] = $w['role_id'];
            if(isset($w['group_id'])) $_data['grp'] = $w['group_id'];
            $data['info'] = json_encode($_data);
            //事务操作
            $this->db->trans_start();//事务开启
            $this->db->set($data)->where('id',$w['id'])->update('apply');//申请清单状态修改，加备注
            $_data['per'] = $r_per;
            $_data['bsid'] = $bsid;
            $_data['psid'] = $psid;
            $_data['ksid'] = $ksid;
            $_data['rsid'] = $rsid;
            $time = time();
            $_data['etime'] = $time;
            $_data['editor'] = $this->_uid;
            $this->db->set($_data)->where('uid',$uid)->update('users');//给人员分配权限
            $this->db->trans_complete();//事务提交
            if ($this->db->trans_status() === FALSE)//失败
            {
                return "更新失败，请重试";
            }else{//成功
                //更新权限缓存
                $this->_flushPer($time);
                return true;
            }
        }else{//不同改过
            $ret = $this->db->set($data)->where('id',$w['id'])->update('apply');
            return $ret;
        }
    }


    /**
     * 获取角色信息
     * del 0:获取未删除的，1：获取删除的，999：获取全部
     * @param array $val
     * @param array $field
     * @param bool|true $limit
     * @param bool|true $name
     * @return array
     * @date
     */
    public function getRole($val = array(),$field = array(),$limit = true,$name = true){
        $w = $this->_getValue($field,$val);
        if(isset($w['del']) && $w['del'] != 999){
            if(strpos($w['id'],',') !== false){
                $this->db->where_in('del',explode(',',$w['del']));
            }else{
                $this->db->where('del',$w['del']);
            }
        }elseif($w['del'] != 999){
            $this->db->where('del',0);
        }
        if(isset($w['id'])){
            if(strpos($w['id'],',') !== false){
                $this->db->where_in('id',explode(',',$w['id']));
            }else{
                $this->db->where('id',$w['id']);
            }
        }
        if(isset($w['tag'])){
            $this->db->where('tag',$w['tag']);
        }
        if(isset($w['name'])){
            $this->db->like('name',$w['name']);
        }

        $this->db->from('role');
        if($limit){//分页
            $num = $this->db->count_all_results('',false);
            $this->getLimit($this->db);
        }else{
            $num = 0;
        }
        $rel = $this->db->get()->result_array();
//        var_dump($this->db->last_query());die;
        if($rel && $name){
            foreach($rel as &$row){
                list($grp,$grp_id) = $this->getPById($row['id'],'role');
                $row['grp'] = implode(',',$grp);
                $row['grp_id'] = $grp_id;
            }
        }
        return  (array($rel,$num));
    }

    /**
     * 根据一个组ID来拉取数据
     * @param $id
     * @return mixed
     * @date
     */
    public function getRoleById($id){
        $id = (int) $id;
        $ret = $this->db->where('id',$id)->get('role')->row_array();
        return $ret;
    }

    /**
     * 检查角色里面是否有一样的名字了
     * @param $name
     * @param int $id
     * @return bool|string
     * @date
     */
    public function checkRName($name,$id = 0){
        if(empty($name)) return "空名字~";
        $this->db->where('name',$name);
        if($id){
            $this->db->where('id !=',$id);
        }
        $rel = $this->db->get('role')->row_array();
        if(empty($rel)) return false;
        return true;
    }

    /**
     * 获取一个组名映射表
     * @return array
     * @date
     */
    public function getRoleName(){
        $rel = $this->db->get('role')->result_array();
        $arr = array();
        foreach($rel as $row){
            $arr[$row['id']] = $row['name'];
        }
        return $arr;
    }

    /**
     * 新加一个角色
     * @param string $field
     * @return mixed
     * @date
     */
    public function addRole($field = ''){
        $w = $this->_getValue($field);
        $data = array(
            'name' => $w['name'],
            'des' => isset($w['des']) ? $w['des'] : ''
        );
        if(isset($w['per'])) $data['per'] = stripslashes($w['per']);//权限为JSON格式，要去掉反斜杠
        $this->db->trans_start();//事务开启
        $ret = $this->db->set($data)->insert('role');
        $id = $this->db->insert_id();
        //新修改
        if($id){
            if(isset($w['add_role'])){//新加角色
                $data = $this->getUserPer($w['add_role']);
                foreach($data as $row){
                    $up = array('uid' => $row['uid']);
                    if(!empty($row['role'])){
                        $up['role'] = fun::appendSome($up['role'],$id);
                    }else{
                        $up['role'] = $id;
                    }
                    $this->giveUserPer($up);
                }
            }
        }

        /*if($id){
            if(isset($w['add_role'])){//新加角色
                $data = $this->getUserPer($w['add_role']);
                foreach($data as $row){
                    $up = array('uid' => $row['uid']);
                    if(!empty($row['role'])){
                        $up['role'] = fun::appendSome($up['leader'],$id);
                    }else{
                        $up['role'] = $id;
                    }
                    
                    $up['per'] = fun::unitPer(array($row['per'],stripslashes($w['per'])));
                    $this->giveUserPer($up);
                }
            }
        }*/

        $this->db->trans_complete();//事务提交
        if ($this->db->trans_status() === FALSE)//失败
        {
            return "保存失败，请重试";
        }else{//成功
            //更新权限缓存
            $this->_flushPer();
            return true;
        }
    }

    /**
     * 更新一个角色
     * @param array $val
     * @param string $field
     * @return bool|string
     * @date
     */
    public function editRole($val = array(),$field = ''){
        $id = $val['id'];
        if(!$id) return "角色ID出错";
        $w = $this->_getValue($field);
        $data = array(
            'name' => $w['name'],
            'des' => isset($w['des']) ? $w['des'] : ''
        );
        $del = false;
        if(isset($w['del']) && $w['del'] == 1){//删除
            $del = true;
            $data = array('del' => 1);
        }
        if(isset($w['per'])) $data['per'] = stripslashes($w['per']);//权限为JSON格式，要去掉反斜杠
        $this->db->trans_start();//事务开启





        /**
         *  逻辑说明：先获取原来拥有这个组的所有人，
         *  然后把这些人的旧的权限删掉，
         *  然后再为新的人追加新的权限
         *
         */
        //拉取原来的组信息
        $old_data = $this->getRoleById($id);
//        var_dump($old_data);die;


//        var_dump($old_leader);die;

        //更新操作
        $this->db->set($data)->where('id',$id)->update('role');
        //新修改
        if($del){
            //拉取原来有这个角色的人员
            list(,$old_role) = $this->getPById($id,'role');
            if(!empty($old_role)){//删除用户中这个角色
            $data = $this->getUserPer(implode(',',$old_role));
                foreach($data as $row){
                    $up = array('uid' => $row['uid']);
                    $role_id = fun::delSome($row['role'],$id);
                    $up['role'] = $role_id;
                    $this->giveUserPer($up);
                }
            }
        }




       /* if(!empty($old_role)){//删除角色
            $data = $this->getUserPer(implode(',',$old_role));
            foreach($data as $row){
                $up = array('uid' => $row['uid']);
                $role_id = fun::delSome($row['role'],$id);
                //旧角色权限
                $old_per = $row['per'];
                if(isset($old_data['per']) && $old_data['per']){
                    if($old_per){
                        $old_per = fun::departPer($old_per,$old_data['per']);
                    }else{
                        $old_per = '';
                    }
                }
                $up['role'] = $role_id;
                $up['per'] = $old_per;
                $this->giveUserPer($up);
            }
        }
        if(isset($w['role']) && !$del){//新加角色
            $data = $this->getUserPer($w['role']);
            foreach($data as $row){
                $up = array('uid' => $row['uid']);
                $old_per = $row['per'];
                if(!empty($row['role'])){
                    $up['role'] = fun::appendSome($row['role'],$id);
                }else{
                    $up['role'] = $id;
                }
                $new_per = isset($w['per']) ? $w['per'] : '';
                $up['per'] = fun::unitPer(array($old_per,$new_per));//权限为JSON格式，要去掉反斜杠
                $this->giveUserPer($up);
            }
        }*/

        $this->db->trans_complete();//事务提交
        if ($this->db->trans_status() === FALSE)//失败
        {
            return "保存失败，请重试";
        }else{//成功
            //更新权限缓存
            $this->_flushPer();
            return true;
        }
    }


    /**
     * 获取组信息
     * del 0:获取未删除的，1：获取删除的，999：获取全部 默认取未删除的
     * @param array $val
     * @param array $filed
     * @param bool|true $limit
     * @param bool|true $name
     * @return array
     * @date
     */
    public function getGroup($val = array(),$filed = array(),$limit = true,$name = true){
        $w = $this->_getValue($filed,$val);
        if(isset($w['del']) && $w['del'] != 999){
            if(strpos($w['del'],',') !== false){
                $this->db->where_in('del',explode(',',$w['del']));
            }else{
                $this->db->where('del',$w['del']);
            }
        }elseif($w['del'] != 999){
            $this->db->where('del',0);
        }
        if(isset($w['id'])){
            if(strpos($w['id'],',') !== false){
                $this->db->where_in('id',explode(',',$w['id']));
            }else{
                $this->db->where('id',$w['id']);
            }
        }
        if(isset($w['tag'])){
            $this->db->where('tag',$w['tag']);
        }
        if(isset($w['name'])){
            $this->db->like('name',$w['name']);
        }
        $this->db->from('group');
        if($limit){//分页
            $num = $this->db->count_all_results('',false);
            $this->getLimit($this->db);
        }else{
            $num = 0;
        }
        $rel = $this->db->get()->result_array();
        if($rel && $name){
            foreach($rel as &$row){
                list($leader,$leader_id) = $this->getPById($row['id'],'leader');
                $row['leader'] = implode(',',$leader);
                $row['leader_id'] = $leader_id;
                list($group,$group_id) = $this->getPById($row['id'],'grp');
                $row['group'] = implode(',',$group);
                $row['group_id'] = $group_id;
            }
        }
        return  (array($rel,$num));
    }

    /**
     * 根据一个组ID来拉取数据
     * @param $id
     * @return mixed
     * @date
     */
    public function getGroupById($id){
        $id = (int) $id;
        $ret = $this->db->where('id',$id)->get('group')->row_array();
        return $ret;
    }

    /**
     * 检查组里面是否有一样的名字了
     * @param $name
     * @param int $id
     * @return bool|string
     * @date
     */
    public function checkGName($name,$id = 0){
        if(empty($name)) return "空名字~";
        $this->db->where('name',$name);
        if($id){
            $this->db->where('id !=',$id);
        }
        $rel = $this->db->get('group')->row_array();
        if(empty($rel)) return false;
        return true;
    }

    /**
     * 获取一个组名映射表
     * @return array
     * @date
     */
    public function getGrpName(){
        $rel = $this->db->get('group')->result_array();
        $arr = array();
        foreach($rel as $row){
            $arr[$row['id']] = $row['name'];
        }
        return $arr;
    }

    /**
     * 获取一个组/角色里面有哪些人
     * @param $id
     * @param $where
     * @return array
     * @date
     */
    public function getPById($id,$where){
        $id = (int) $id;
        if(!in_array($where,array('grp','leader','role'))) return array(array(),array());
        $sql = "select uid,cname from bay_users where find_in_set($id,$where)";
//        var_dump($sql);
        $rel = $this->db->query($sql)->result_array();
        $name = array();
        $uid = array();
        if($rel){
            foreach($rel as $row){
                $name[] = $row['cname'];
                $uid[] = $row['uid'];
            }
        }
        return array($name,$uid);
    }

    /**
     * 获取组权限
     * @param $id
     * @return array|string
     * @date
     */
    public function getGrpSids($id){
        $rel = $this->getGroup(array('id' => $id),array(),false,false);
        $rel = $rel[0];
        $data = array();
        if($rel){
            $rel = $rel[0];
            $ret = array();
            $arr = array(
                'bsid' => $rel['bsid'],
                'psid' => $rel['psid'],
                'ksid' => $rel['ksid'],
                'rsid' => $rel['rsid']
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
            $data = array(
                'select' => $ret,
            );
        }else{
            $data = "id有错，没有这么一个组！";
        }
        return $data;
    }

    /**
     * 新加一个组
     * @param string $field
     * @return mixed
     * @date
     */
    public function addGroup($field = ''){
        $w = $this->_getValue($field);
        $data = array(
            'name' => $w['name'],
            'des' => $w['des']
        );
        if(isset($w['bsid'])) $data['bsid'] = $w['bsid'];
        if(isset($w['psid'])) $data['psid'] = $w['psid'];
        if(isset($w['ksid'])) $data['ksid'] = $w['ksid'];
        if(isset($w['rsid'])) $data['rsid'] = $w['rsid'];
        $this->db->trans_start();//事务开启
        $ret = $this->db->set($data)->insert('group');
//        var_dump($this->db->last_query());
        $id = $this->db->insert_id();
        if($id){
            if(isset($w['add_leader'])){//新加小组长
                $data = $this->getUserPer($w['add_leader']);
                foreach($data as $row){
                    $up = array('uid' => $row['uid']);
                    if(!empty($row['leader'])){
                        $up['leader'] = fun::appendSome($row['leader'],$id);
                    }else{
                        $up['leader'] = $id;
                    }
                    $up['bsid'] = fun::appendSome($row['bsid'],$w['bsid']);
                    $up['psid'] = fun::appendSome($row['psid'],$w['psid']);
                    $up['ksid'] = fun::appendSome($row['ksid'],$w['ksid']);
                    $up['rsid'] = fun::appendSome($row['rsid'],$w['rsid']);
                    $this->giveUserPer($up);
                }
            }
            if(isset($w['add_grp'])){//新加小组组员
                $data = $this->getUserPer($w['add_grp']);
                foreach($data as $row){
                    $up = array('uid' => $row['uid']);
                    if(!empty($row['grp'])){
                        $up['grp'] = fun::appendSome($row['grp'],$id);
                    }else{
                        $up['grp'] = $id;
                    }
                    $up['bsid'] = fun::appendSome($row['bsid'],$w['bsid']);
                    $up['psid'] = fun::appendSome($row['psid'],$w['psid']);
                    $up['ksid'] = fun::appendSome($row['ksid'],$w['ksid']);
                    $up['rsid'] = fun::appendSome($row['rsid'],$w['rsid']);
                    $this->giveUserPer($up);
                }
            }
        }
        $this->db->trans_complete();//事务提交
        if ($this->db->trans_status() === FALSE)//失败
        {
            return "保存失败，请重试";
        }else{//成功
            //更新权限缓存
            $this->_flushPer($time);
            return true;
        }
    }

    /**
     * 更新一个组
     * @param array $val
     * @param string $field
     * @return bool|string
     * @date
     */
    public function editGroup($val = array(),$field = ''){
        $id = $val['id'];
        if(!$id) return "组ID出错";
        $w = $this->_getValue($field);
        $data = array(
            'name' => $w['name'],
            'des' => $w['des']
        );
        $del = false;
        if(isset($w['del']) && $w['del'] == 1){//删除
            $del = true;//删除标记
            $data = array('del' => 1);
        }
        $data['bsid'] = isset($w['bsid']) ? $w['bsid'] : '';
        $data['psid'] = isset($w['psid']) ? $w['psid'] : '';
        $data['ksid'] = isset($w['ksid']) ? $w['ksid'] : '';
        $data['rsid'] = isset($w['rsid']) ? $w['rsid'] : '';
        $this->db->trans_start();//事务开启
        /**
         *  逻辑说明：先获取原来拥有这个组的所有人，
         *  然后把这些人的旧的权限删掉，
         *  然后再为新的人追加新的权限
         *
         */
        //拉取原来的组信息
        $old_data = $this->getGroupById($id);
//        var_dump($old_data);die;

        //拉取原来有这个组长人员
        list(,$old_leader) = $this->getPById($id,'leader');
//        var_dump($old_leader);die;

        //拉取原来有这个组的人员
        list(,$old_grp) = $this->getPById($id,'grp');
//        var_dump($old_grp);die;

        $this->db->set($data)->where('id',$id)->update('group');
//        var_dump($this->db->last_query());die;
        if(!empty($old_leader)){//删除小组长
            $data = $this->getUserPer(implode(',',$old_leader));
            foreach($data as $row){
                $up = array('uid' => $row['uid']);
                $leader_id = fun::delSome($row['leader'],$id);
                //把拥有的组员ID也加上
                $grp_id_new = '';
                if($row['grp']) $grp_id_new = fun::delSome($row['grp'],$id);
                //最终ID，组长ID和组员ID，去掉了这次删除的ID了
                $all_id = fun::appendSome($leader_id,$grp_id_new);
                $rel = $this->getGroup(array('id' => $all_id),array(),false,false);
                $rel = $rel[0];
                $bsid = array();
                $psid = array();
                $ksid = array();
                $rsid = array();
                if($rel){
                    foreach($rel as $r){
                        $bsid[] = $r['bsid'];
                        $psid[] = $r['psid'];
                        $ksid[] = $r['ksid'];
                        $rsid[] = $r['rsid'];
                    }
                }
                $up['leader'] = $leader_id;
                $up['bsid'] = fun::delSome($row['bsid'],fun::getDiff($bsid,$old_data['bsid']));
                $up['psid'] = fun::delSome($row['psid'],fun::getDiff($psid,$old_data['psid']));
                $up['ksid'] = fun::delSome($row['ksid'],fun::getDiff($ksid,$old_data['ksid']));
                $up['rsid'] = fun::delSome($row['rsid'],fun::getDiff($rsid,$old_data['rsid']));
                $this->giveUserPer($up);
            }
        }

        if(!empty($old_grp)){//删除小组员
            $data = $this->getUserPer(implode(',',$old_grp));
            foreach($data as $row){
                $up = array('uid' => $row['uid']);
                $grp_id = fun::delSome($row['grp'],$id);
                //把拥有的组员组长ID也加上
                $leader_id_new = '';
                if($row['leader']) $leader_id_new = fun::delSome($row['leader'],$id);
                //最终ID，组长ID和组员ID，去掉了这次删除的ID了
                $all_id = fun::appendSome($grp_id,$leader_id_new);
                $rel = $this->getGroup(array('id' => $all_id),array(),false,false);
                $rel = $rel[0];
                $bsid = array();
                $psid = array();
                $ksid = array();
                $rsid = array();
                if($rel){
                    foreach($rel as $r){
                        $bsid[] = $r['bsid'];
                        $psid[] = $r['psid'];
                        $ksid[] = $r['ksid'];
                        $rsid[] = $r['rsid'];
                    }
                }
                $up['grp'] = $grp_id;
                $up['bsid'] = fun::delSome($row['bsid'],fun::getDiff($bsid,$old_data['bsid']));
                $up['psid'] = fun::delSome($row['psid'],fun::getDiff($psid,$old_data['psid']));
                $up['ksid'] = fun::delSome($row['ksid'],fun::getDiff($ksid,$old_data['ksid']));
                $up['rsid'] = fun::delSome($row['rsid'],fun::getDiff($rsid,$old_data['rsid']));
                $this->giveUserPer($up);
            }
        }

        if(isset($w['leader']) && !$del){//新加小组长权限
            $data = $this->getUserPer($w['leader']);
            foreach($data as $row){
                $up = array('uid' => $row['uid']);
                if(!empty($row['leader'])){
                    $up['leader'] = fun::appendSome($row['leader'],$id);
                }else{
                    $up['leader'] = $id;
                }
                //$up['bsid'] = fun::explodeSid(fun::implodeSid(fun::appendSome($row['bsid'],$w['bsid'])));
                $up['bsid'] = fun::appendSome($row['bsid'],$w['bsid']);
                $up['psid'] = fun::appendSome($row['psid'],$w['psid']);
                $up['ksid'] = fun::appendSome($row['ksid'],$w['ksid']);
                $up['rsid'] = fun::appendSome($row['rsid'],$w['rsid']);
                $this->giveUserPer($up);
            }
        }

        if(isset($w['grp']) && !$del){//新加小组组员权限
            $data = $this->getUserPer($w['grp']);
            foreach($data as $row){
                $up = array('uid' => $row['uid']);
                if(!empty($row['grp'])){
                    $up['grp'] = fun::appendSome($row['grp'],$id);
                }else{
                    $up['grp'] = $id;
                }
                //$up['bsid'] = fun::explodeSid(fun::implodeSid(fun::appendSome($row['bsid'],$w['bsid'])));
                $up['bsid'] = fun::appendSome($row['bsid'],$w['bsid']);
                $up['psid'] = fun::appendSome($row['psid'],$w['psid']);
                $up['ksid'] = fun::appendSome($row['ksid'],$w['ksid']);
                $up['rsid'] = fun::appendSome($row['rsid'],$w['rsid']);
                $this->giveUserPer($up);
            }
        }
        $this->db->trans_complete();//事务提交
        if ($this->db->trans_status() === FALSE)//失败
        {
            return "保存失败，请重试";
        }else{//成功
            //更新权限缓存
            $this->_flushPer();
            return true;
        }
    }


    /**
     * 组设置中保存sid
     * @param array $val
     * @param array $field
     * @return mixed
     * @date
     */
    public function saveGSid($val = array(),$field = array()){
        $sid = $val['sid'];
        $id = $val['id'];
//        if(!$sid || !$uid) return false;
        $w = $this->_getValue($field,$val);
        $where = explode(',',$w['where']);
        foreach($where as $row){
            if(!in_array($row,array('bsid','psid','ksid','rsid'))) continue;//不是指定字段不操作，防止出错

            $this->db->set($row,$sid);
        }
        $rel = $this->db->where('id',$id)->update('group');
        return $rel;
    }

    /**
     * 获取用户列表
     * del 0:获取未删除的，1：获取删除的，默认拉取获取全部
     * @param array $val
     * @param array $field
     * @param bool|true $limit
     * @return array
     * @date
     */
    public function getUser($val = array(),$field = array(),$limit = true){
        $w = $this->_getValue($field,$val);

        if(isset($w['del'])){
            if(strpos($w['id'],',') !== false){
                $this->db->where_in('del',explode(',',$w['del']));
            }else{
                $this->db->where('del',$w['del']);
            }
        }

        if(isset($w['id'])){
            if(strpos($w['id'],',') !== false){
                $this->db->where_in('id',explode(',',$w['id']));
            }else{
                $this->db->where('id',$w['id']);
            }
        }
        if(isset($w['uid'])){
            if(strpos($w['uid'],',') !== false){
                $this->db->where_in('uid',explode(',',$w['uid']));
            }else{
                $this->db->where('uid',$w['uid']);
            }
        }
        if(isset($w['cname'])){
            $this->db->like('cname',$w['cname']);
        }
        if(isset($w['search'])){
            $sql = "(cname like '%" . $w['search'] . "%' or nickname like '%" . $w['search'] . "%' or uid = '{$w['search']}')";
            $this->db->where($sql);
        }
        $this->db->order_by('del','asc');//默认排序
        if(isset($w['oby'])){
            //排序
            $t = explode('_',$w['oby']);
            $sc = (isset($t[1]) && in_array($t[1],array('asc','desc'))) ? $t[1] : 'asc';
            if(in_array($t[0],array('role','group','id','uid'))){
                $this->db->order_by($t[0],$sc);
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
        if($rel){//组装数据
            $grpName = $this->getGrpName();
            $roleName = $this->getRoleName();
//            var_dump($grpName);
//            var_dump($roleName);die;
            foreach($rel as &$row){
                $row['grp_name'] = $row['grp'] ? rtrim(implode(',',fun::reflect($row['grp'],$grpName)),',') : '';
                $row['role_name'] = $row['role'] ? rtrim(implode(',',fun::reflect($row['role'],$roleName)),',') : '';
            }
        }
        return  (array($rel,$num));
    }

    /**
     * 获取组合权限
     * @param $role_id
     * @param string $self_per
     * @return array
     * @date
     */
    public function getUnitPer($role_id,$self_per = ''){
        $role = explode(',',$role_id);
        $rel = $this->db->where_in("id",$role)->get('role')->result_array();
        $per = array();
        if($rel){
            foreach($rel as $row){
                if($row['per']) $per[] = $row['per'];
            }
        }
        if($self_per) $per[] = $self_per;

        if($per){
            $per = fun::unitPer($per);
        }
        return $per;
    }

    /**
     * 获取用户权限
     * @param $uid
     * @return array
     * @date
     */
    public function getUserPer($uid){
        $data = array();
        if($uid){
            if(strpos($uid,',')){
                $this->db->where_in('uid',explode(',',$uid));
            }else{
                $this->db->where('uid',$uid);
            }
            $data = $this->db->get('users')->result_array();
        }
        return $data;
    }

    /**
     * 获取用户权限
     * @param $uid
     * @return array|string
     * @date
     */
    public function getPer($uid){
        $rel = $this->getUser(array('uid' => $uid),array(),false);
        $rel = $rel[0];
        $data = array();
        if($rel){
            $rel = $rel[0];
            $ret = array();
            $role = $this->getNameById($rel['role'],'role');
            $group = fun::appendSome($rel['grp'],$rel['leader']);
            $group = $this->getNameById($group,'group');
            $arr = array(
                'bsid' => $rel['bsid'],
                'psid' => $rel['psid'],
                'ksid' => $rel['ksid'],
                'rsid' => $rel['rsid']
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
            $data = array(
                'select' => $ret,
                'group' => $group,
                'role' => $role,
            );
        }else{
            $data = "uid有错，没有这么一个用户！";
        }
        return $data;
    }

    /**
     * 组装sid数据
     * @param $sid_arr
     * @param $key
     * @return array
     * @date
     */
    public function dealSid($sid_arr,$key){
        $arr = array();
        if($sid_arr){
            foreach($sid_arr as $row){
                $arr[] = $key . '.' . $row;
            }
        }
        return $arr;
    }

    /**
     * 检查用户列表里面是否已经有这个用户了
     * @param $uid
     * @return bool|string
     * @date
     */
    public function checkUser($uid){
        $uid = (int) $uid;
        if(empty($uid)) return "UID有错";
        $rel = $this->db->where('uid',$uid)->get('users')->row_array();
        if(empty($rel)) return false;
        return true;
    }

    /**
     * 保存用户信息
     * @param array $val
     * @param string $field
     * @return mixed
     * @date
     */
    public function setUser($val = array(),$field = ''){
        $w = $this->_getValue($field,$val);
        $r = $this->getLocalUserData($w['uid']);
        $r = $r[$w['uid']];
        if(empty($r)) return "在本地找不到这个用户信息，请联系管理员~";
        $data = array(
            'uid' => $w['uid'],
            'max' => $w['max'],
            'nickname' => $w['nickname'],
            'pic' => $w['pic'] ? $w['pic'] : '',
            'name' => $r['rtx'] ? $r['rtx'] : '',
            'cname' => $r['name'] ? $r['name'] : '',
            'org_id' => $r['org_id'] ? $r['org_id'] : '',
            'phone' => $r['phone'] ? $r['phone'] : '',
            'email' => $r['email'] ? $r['email'] : '',
            'statement' => $w['statement'] ? $w['statement'] : '',
            //新修改
            'role' => $w['role_id'] ? $w['role_id'] : '',
        );



        if(isset($w['edit'])){//编辑，获取用户原来信息
            list($old_data) = $this->getUserPer($w['uid']);
        }
//        var_dump($w);die;
        $old_role = isset($old_data['role']) ? $old_data['role'] : '';//旧角色
        $old_per = isset($old_data['per']) ? $old_data['per'] : '';//旧权限



        $old_leader = isset($old_data['leader']) ? $old_data['leader'] : '';//旧组长
        $old_grp = isset($old_data['grp']) ? $old_data['grp'] : '';//旧组员
        $old_bsid = isset($old_data['bsid']) ? $old_data['bsid'] : '';//旧后台权限
        $old_psid = isset($old_data['psid']) ? $old_data['psid'] : '';//旧工作台权限
        $old_ksid = isset($old_data['ksid']) ? $old_data['ksid'] : '';//旧知识库权限
        $old_rsid = isset($old_data['rsid']) ? $old_data['rsid'] : '';//旧反馈搜索权限

        //要删除的$role_id
        /*$del_role_id = fun::getDiff(array($w['role_id']),$old_role);
//        var_dump($del_role_id);die;
        if($del_role_id){//删除角色权限
            list($rl) = $this->getRole(array('id' => $del_role_id),'',false);
            if($rl){
                $tmp = array();
                foreach($rl as $row){
                    //循环删除旧权限
                    if($old_per && $row['per']) $old_per = fun::departPer($old_per,$row['per']);
                }
            }
        }

        if(isset($w['role_id'])){//添加角色
            list($rl) = $this->getRole(array('id' => $w['role_id']),'',false);
            if($rl){
                $tmp = array();
                foreach($rl as $row){
                    if($row['per']) $tmp[] = $row['per'];
                }
                if($old_per) $tmp[] = $old_per;
                $per = fun::unitPer($tmp);
                $data['role'] = $w['role_id'];
                $data['per'] = $per;
            }
        }else{//角色ID为空
            $data['role'] = '';
        }*/

        //要删除的$grp_id
        //用新的组ID和拥有的组长ID来跟就组id比较差异，得出要删除的组ID
        $del_grp_id = fun::getDiff(array($w['grp_id'],$old_leader),$old_grp);//删除掉的组员ID
//        var_dump($del_grp_id);die;
        if($del_grp_id){//删除组权限
            list($rl) = $this->getGroup(array('id' => $del_grp_id),'',false);
            if($rl){
                $tmp = array();
                foreach($rl as $row){
                    if($row['bsid']) $tmp['bsid'][] = $row['bsid'];
                    if($row['psid']) $tmp['psid'][] = $row['psid'];
                    if($row['ksid']) $tmp['ksid'][] = $row['ksid'];
                    if($row['rsid']) $tmp['rsid'][] = $row['rsid'];
                }
                $old_bsid = fun::delSome($old_bsid,fun::explodeSid(fun::implodePer($tmp['bsid'])));
                $old_psid = fun::delSome($old_psid,fun::explodeSid(fun::implodePer($tmp['psid'])));
                $old_ksid = fun::delSome($old_ksid,fun::explodeSid(fun::implodePer($tmp['ksid'])));
                $old_rsid = fun::delSome($old_rsid,fun::explodeSid(fun::implodePer($tmp['rsid'])));
            }
        }
        $bsid = array();
        $psid = array();
        $ksid = array();
        $rsid = array();
        //旧权限
        if($old_bsid) $bsid[] = $old_bsid;
        if($old_psid) $psid[] = $old_psid;
        if($old_ksid) $ksid[] = $old_ksid;
        if($old_rsid) $rsid[] = $old_rsid;
        if(isset($w['grp_id'])){//添加小组
            list($rl) = $this->getGroup(array('id' => $w['grp_id']),'',false,false);
            if($rl){
                foreach($rl as $row){
                    $bsid[] = $row['bsid'];
                    $psid[] = $row['psid'];
                    $ksid[] = $row['ksid'];
                    $rsid[] = $row['rsid'];
                }
            }
            $data['grp'] = $w['grp_id'];
        }else{//组ID为空
            $data['grp'] = '';
        }

        if(isset($w['leader_id'])){//添加组长(这里暂时没用到)
            list($rl) = $this->getGroup(array('id' => $w['leader_id']),'',false,false);
            if($rl){
                foreach($rl as $row){
                    $bsid[] = $row['bsid'];
                    $psid[] = $row['psid'];
                    $ksid[] = $row['ksid'];
                    $rsid[] = $row['rsid'];
                }
            }
            $data['leader'] = $w['leader_id'];
        }
        //最后整理组员和组长合并的权限
        $bsid = fun::implodeSid($bsid);
        $psid = fun::implodeSid($psid);
        $ksid = fun::implodeSid($ksid);
        $rsid = fun::implodeSid($rsid);
        $data['bsid'] = fun::explodeSid($bsid);
        $data['psid'] = fun::explodeSid($psid);
        $data['ksid'] = fun::explodeSid($ksid);
        $data['rsid'] = fun::explodeSid($rsid);
        $time = time();
        $data['etime'] = $time;
        $data['editor'] = $this->_uid;
        if(isset($w['edit']) && $w['edit'] == 1){//更新
            $ret = $this->db->set($data)->where('uid',$w['uid'])->update('users');
        }else{
            $ret = $this->db->set($data)->insert('users');
        }
        //更新权限缓存
        $this->_flushPer($time);
        return $ret;
    }

    /**
     * 更新权限缓存
     * @date
     */
    public function _flushPer($time = ''){
        $f = CACHE_DIR . 'per.php';
        $per = array();
        if(is_file($f)){
            $per = include($f);
            if($time){
                $sql = "select * from bay_users WHERE del = 0 and etime = $time ORDER BY  uid";
            }else{
                $time = time() - 180;//三分钟内的修改
                $sql = "select * from bay_users WHERE del = 0 and etime > $time ORDER BY  uid";
            }
            $rel = $this->db->query($sql)->result_array();
        }else{//首次生成或者没文件
            $sql = "select * from bay_users WHERE del = 0 ORDER BY uid";//获取全部
            $rel = $this->db->query($sql)->result_array();
        }
        if($rel){
            foreach($rel as $row){
                $p = $this->getUnitPer($row['role'],$row['per']);
                $per[$row['uid']] = array(
                    'per' => $row['per'],
                    'all_per' => $p,
                    'role' => $row['role'],
                    'grp' => $row['grp'],
                    'game_group' => (STRING) $row['set_id'],
                    'leader' => $row['leader'],
                    'bsid' => $row['bsid'],
                    'psid' => $row['psid'],
                    'ksid' => $row['ksid'],
                    'rsid' => $row['rsid']
                );
            }
        }
        $out = load_class('OP','helpers');
        $out->php($f,$per);
    }



    /**
     * 保存用户全部（用户站点和权限，只保存传过来的）
     * @param array $val
     * @param string $field
     * @return mixed
     * @date
     */
    public function setAll($val = array(),$field = ''){
        $w = $this->_getValue($field,$val);
        $r = $this->getLocalUserData($w['uid']);
        $r = $r[$w['uid']];
        if(empty($r)) return "在本地找不到这个用户信息，请联系管理员~";
        $data = array(
            'uid' => $w['uid'],
            'max' => $w['max'],
            'nickname' => $w['nickname'],
            'pic' => $w['pic'] ? $w['pic'] : '',
            'name' => $r['rtx'] ? $r['rtx'] : '',
            'cname' => $r['name'] ? $r['name'] : '',
            'org_id' => $r['org_id'] ? $r['org_id'] : '',
            'phone' => $r['phone'] ? $r['phone'] : '',
            'email' => $r['email'] ? $r['email'] : '',
            'statement' => $w['statement'] ? $w['statement'] : '',
        );
        $data['role'] = isset($w['role_id']) ? $w['role_id'] : '';
        $data['grp'] = isset($w['grp_id']) ? $w['grp_id'] : '';
        $data['per'] = isset($w['per']) ? stripslashes($w['per']) : '';
        $data['bsid'] = isset($w['bsid']) ? $w['bsid'] : '';
        $data['psid'] = isset($w['psid']) ? $w['psid'] : '';
        $data['ksid'] = isset($w['ksid']) ? $w['ksid'] : '';
        $data['rsid'] = isset($w['rsid']) ? $w['rsid'] : '';
        $time = time();
        $data['etime'] = time();
        $data['editor'] = $this->_uid;
        if($this->checkUser($w['uid'])){//更新
            $ret = $this->db->set($data)->where('uid',$w['uid'])->update('users');
        }else{//新增
            $ret = $this->db->set($data)->insert('users');
        }
        //更新权限缓存
        $this->_flushPer($time);
        return $ret;
    }

    /**
     * 用户权限设置中保存sid
     * @param array $val
     * @param array $field
     * @return mixed
     * @date
     */
    public function saveUSid($val = array(),$field = array()){
        $sid = $val['sid'];
        $uid = $val['uid'];
//        if(!$sid || !$uid) return false;
        $w = $this->_getValue($field,$val);
        $where = explode(',',$w['where']);
        $data = array();
        foreach($where as $row){
            if(!in_array($row,array('bsid','psid','ksid','rsid'))) continue;//不是指定字段不操作，防止出错
            $data[$row] = $sid;
        }
        $time = time();
        $data['etime'] = $time;
        $data['editor'] = $this->_uid;
        $this->db->set($data);
        $rel = $this->db->where('uid',$uid)->update('users');
        //更新权限缓存
        $this->_flushPer($time);
        return $rel;
    }

    /**
     * 删除一个用户
     * @param $uid
     * @param $del
     * @return mixed
     * @date
     */
    public function delUser($uid,$del = 1){
        $del = (int) $del;
        $time = time();
        $data = array(
            'del' => $del,
            'etime' => $time,
            'editor' => $this->_uid
        );
        $ret = $this->db->set($data)->where('uid',$uid)->update('users');
        //更新权限缓存
        $this->_flushPer($time);
        return $ret;
    }

    /**
     * 编辑一个用户
     * @param array $val
     * @param string $field
     * @return mixed
     * @date
     */
    public function editUser($val = array(),$field = ''){
        $w = $this->_getValue($field,$val);
        $r = $this->getLocalUserData($w['uid']);
        $r = $r[$w['uid']];
        $data = array(
            'uid' => $w['uid'],
            'name' => $r['name'] ? $r['name'] : '',
            'cname' => $r['cname'] ? $r['cname'] : '',
            'org_id' => $r['org_id'] ? $r['org_id'] : '',
            'phone' => $r['phone'] ? $r['phone'] : '',
            'email' => $r['email'] ? $r['email'] : '',
            'statement' => $w['statement'] ? $w['statement'] : '',
        );
        if(isset($w['role_id'])){//添加角色
            $rl = $this->getRole(array('id' => $w['role_id']),'',false);
            $rl = $rl[0];
            if($rl){
                $role = '';
                $tmp = array();
                foreach($rl as $row){
                    $role .= $row['id'] . ',';
                    $tmp[] = $row['per'];
                }
                $per = fun::unitPer($tmp);
                $data['role'] = rtrim($role,',');
                $data['per'] = stripslashes($per);
            }
        }
        if(isset($w['grp_id'])){//添加角色
            $rl = $this->getGroup(array('id' => $w['grp_id']),'',false,false);
            $rl = $rl[0];
            if($rl){
                $grp = '';
                $bsid = array();
                $psid = array();
                $ksid = array();
                $rsid = array();
                foreach($rl as $row){
                    $grp .= $row['id'] . ',';
                    $bsid[] = $row['bsid'];
                    $psid[] = $row['psid'];
                    $ksid[] = $row['ksid'];
                    $rsid[] = $row['rsid'];
                }
                $bsid = fun::implodePer($bsid);
                $psid = fun::implodePer($psid);
                $ksid = fun::implodePer($ksid);
                $rsid = fun::implodePer($rsid);
                $data['grp'] = rtrim($grp,',');
                $data['bsid'] = $bsid;
                $data['psid'] = $psid;
                $data['ksid'] = $ksid;
                $data['rsid'] = $rsid;
            }
        }
        $time = time();
        $data['etime'] = $time;
        $data['editor'] = $this->_uid;
        $ret = $this->db->set($data)->insert('users');
        //更新权限缓存
        $this->_flushPer($time);
        return $ret;
    }

    /**
     * 查询本地保存的员工信息表
     * @param int $uid
     * @param string $name
     * @return array
     * @date
     */
    public function getLocalUserData($uid = 0,$name = ''){
        if(empty($uid) && empty($name)) return array();
//        $f = CFG_DIR . '/hr/user.php';
//        $user = include($f);
        $user = fun::getALLUserData();
        $data = array();
        if($user){
            if($uid){
                $uid = explode(',',$uid);
                foreach($uid as $row){
                    $id = (int) $row;
                    if($id && isset($user[$id])) $data[$id] = $user[$id];
                }
            }elseif($name){
                $name = explode(',',$name);
                foreach($user as $key => $row){
                    foreach($name as $r){
                        if($row['name'] == $r) $data[$key] = $row;
                    }
                }
            }
        }
        return $data;
    }

    /**
     * 获取角色或者组名字
     * @param $id
     * @param $type
     * @return array
     * @date
     */
    public function getNameById($id,$type){
        if(!$id || !in_array($type,array('role','group'))) return array();
        if(!is_array($id)) $id = explode(',',$id);
        $rel = $this->db->where_in('id',$id)->get($type)->result_array();
        $ret = array();
        if($rel){
            foreach($rel as $row){
                $ret[$row['id']] = $row['name'];
            }
        }
        return $ret;
    }

    /**
     * 给用户添加权限
     * @param array $val
     * @param array $field
     * @return string
     * @date
     */
    public function giveUserPer($val = array(),$field = array()){
        if(empty($val['uid'])) return "没有UID";
        $w = $this->_getValue($field,$val);
        $data = array();
        if(isset($w['set_id'])){
            $data['set_id'] = $w['set_id'];
        }
        if(isset($w['leader'])){
            $data['leader'] = $w['leader'];
        }
        if(isset($w['grp'])){
            $data['grp'] = $w['grp'];
        }
        if(isset($w['bsid'])){
            $data['bsid'] = $w['bsid'];
        }
        if(isset($w['psid'])){
            $data['psid'] = $w['psid'];
        }
        if(isset($w['ksid'])){
            $data['ksid'] = $w['ksid'];
        }
        if(isset($w['rsid'])){
            $data['rsid'] = $w['rsid'];
        }
        if(isset($w['role'])){
            $data['role'] = $w['role'];
        }
        if(isset($w['per'])){
            $data['per'] = stripslashes($w['per']);
        }
        $data['etime'] = time();
        $data['editor'] = $this->_uid;
        $this->db->set($data);
        if(strpos($val['uid'],',')){
            $this->db->where_in('uid',explode(',',$val['uid']));
        }else{
            $this->db->where('uid',$val['uid']);
        }
        $ret = $this->db->update('users');
//        var_dump($this->db->last_query());
        return $ret;
    }

    /**
     * 合并权限
     * @param $per_arr
     * @return array
     * @date
     */
    public function implodePer($per_arr){
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
     * 将数组形式站点转换成字符串行
     * @param array $arr
     * @return string
     * @date
     */
    public function explodePer($arr = array()){
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
     * 合并所有游戏站点
     * @param array $sid_arr
     * @return array
     * @date
     */
    public function implodeSid($sid_arr = array()){
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

    /**
     * 将数组形式站点转换成字符串行
     * @param array $arr
     * @return string
     * @date
     */
    public function explodeSid($arr = array()){
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
     * 执行sql语句
     * @param $sql
     * @return bool
     * @date
     */
    public function doSql($sql){
        if($sql){
            return $this->db->query($sql)->result_array();
        }else{
            return false;
        }

    }



}