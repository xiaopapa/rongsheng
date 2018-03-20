<?php

/**
 * 机器人model
 * Class Robot_model
 * @author harvey
 * @Date: 16-12-29
 * @license  http://www.boyaa.com/
 */
class Game_model extends Bmax_Model {
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


    //
    /**
     * 拉取所有游戏
     * @return array|mixed
     * @date
     */
    public function game(){
        if(!isset($this->_game)){
            $f = DATA_ROOT.'cache/game.php';
            if(is_file($f)){
                $this->_game = include($f);
            }else{
                $this->flushGame();//刷新缓存再取
                $this->_game = include($f);
            }
        }
        return $this->_game;
    }

    /**
     * 根据游戏获取站点(从缓存拉取）
     * 如果最后修改时间超过1天，那么重新从游戏站点去拉取一份写入缓存(1/3机率）
     * @param $gid
     * @return array|bool|int|mixed|string
     * @date
     */
    public function site($gid){
        $dir = 'gamesite/';
        if($gid === 'clear') return fun::cache($dir, 'clear');
        $ckey = $dir . $gid;
        $file_patch = CACHE_DIR . $ckey;
        if(is_file($file_patch) && $ckey != 8){//八字不管它更新了
            $mTime = filemtime ($file_patch);
            if(time() - $mTime > 3600 * 24){//超过一天了
                if(rand(0,2) > 1 && 0){//api地址要全部外网才行
                    fun::cache($ckey,'clear');
                }
            }
        }
        $data = fun::cache($ckey);
        if(!$data){
            $cfg = $this->game();
            if($api = $cfg[$gid]['api']){
                if(strpos($api,'http') !== false){
                    $data = @file_get_contents($api);
                    $data = (array)json_decode($data, true);
                    //更新bay_site 表
                    if($data){
                        $this->updateSite($gid,$cfg[$gid]['name'],$data);
                    }
                }
            }else{
                return array();
            }
            $temp = array();
            if($data){
                foreach($data as $rs){
                    $id = $rs['id'];
                    unset($rs['id']);
                    if(isset($rs['langs']) && $rs['langs'] && is_string($rs['langs'])) $rs['langs'] = explode(',', $rs['langs']);//支持逗号分隔和数组
                    $temp[$id] = $rs;
                }
                $data = $temp;
                fun::cache($ckey, $data);
            }

        }
        return $data;
    }

    /**
     * 获取所有有系站点
     * @date
     */
    public function getAllSite($gid = ''){
        $gid_arr = array();
        if(!$gid){
            $game = $this->game();
            if($game){
                foreach($game as $key => $row){
                    $gid_arr[] = $key;
                }
            }
        }else{
            $gid_arr = explode(',',$gid);
        }
        if(!$gid_arr) return false;
        $data = array();
        foreach($gid_arr as $row){
            $data[$row] = $this->site($row, true);
        }
        return $data;
    }


    /**
     * 从数据库中获取游戏站点
     * @param array $val
     * @param array $field
     * @param bool|true $limit
     * @return array
     * @date
     */
    public function getGameList($val = array(),$field = array(),$limit = true){
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
        if($w['id']){
            if(strpos($w['id'],',') !== false){
                $this->db->where_in('id',explode(',',$w['id']));
            }else{
                $this->db->where('id',$w['id']);
            }
        }
        $this->db->from('game');
        if($limit){//分页
            $num = $this->db->count_all_results('',false);
            $this->getLimit($this->db);
        }else{
            $num = 0;
        }
        $rel = $this->db->get()->result_array();
        if($rel){
            $arr = fun::getALLUserData();
            foreach($rel as $key => &$row){
               $row['ename'] = $arr[$row['uid']]['name'];
            }

        }
        return  (array($rel,$num));
    }

    /**
     * 新加/编辑/删除 游戏
     * @param array $val
     * @param array $field
     * @return mixed
     * @date
     */
    public function setGame($val = array(),$field = array()){
        $w = $this->_getValue($field,$val,$this->db);
        $time = time();
        $data = array(
            'uid' => $this->_uid,
            'times' => $time
        );
        $this->db->set($data);
        $gid = fun::get('id');
        if($gid){//删除/删除还原  / 编辑
            if(isset($w['del'])){
                $data['del'] = $w['del'];
            }
            if(strpos($gid,',') !== false){
                $gid_arr = explode(',',$gid);
                $this->db->where_in('id',$gid_arr);
            }else{
                $this->db->where('id',$gid);
            }
            $ret = $this->db->set($data)->update('game');
        }else{//新加
            $ret = $this->db->set($data)->insert('game');
        }
        //刷新游戏缓存
        $this->flushGame();
        //站点缓存
        if(isset($gid_arr) && $gid_arr){
            foreach($gid_arr as $_gid){
                $this->flushSite($_gid);
            }
        }elseif($gid){
            $this->flushSite($gid);
        }
        return $ret;
    }


    /**
     * 添加、编辑游戏组
     * @param array $val
     * @param array $field
     * @return mixed
     * @date
     */
    public function setGroup($val = array(),$field = array()){
        $w = $this->_getValue($field,$val);
        $gids = fun::get("gids");
        $uids = fun::get("uids");
        $time = time();
        $data = array(
            'editor' => $this->_uid,
            'etime' => $time
        );
        if(isset($w['name'])) $data['name'] = $w['name'];
        $this->db->set($data);
        $this->db->trans_start();
        if($w['id']){
            if(isset($w['del'])){//删除/删除还原
                $data['del'] = $w['del'];
                $this->db->set($data)->where('id',$w['id'])->update('game_group');
//                var_dump($this->db->last_query());die;
                if($w['del'] == 0){//删除还原
                    //站点修改
                    if($gids){
                        $tmp = explode(',',$gids);
                        $sql = "update bay_site set set_id = {$w['id']} WHERE ";
                        foreach($tmp as $row){
                            $_tmp = explode('.',$row);
                            if(isset($_tmp[0]) && isset($_tmp[1])){
                                $sql .= " (gid = {$_tmp[0]} and sid = {$_tmp[1]}) OR";
                            }
                        }
                        $sql = rtrim($sql,'OR');
                        $this->db->query($sql);
                    }
                    //用户UID修改
                    if($uids){
                        $sql = "select uid,set_id from bay_users WHERE uid in ({$uids})";
                        $rel = $this->db->query($sql)->result_array();
                        $user_data = array();
                        if($rel){
                            foreach($rel as $row){
                                $user_data[$row['uid']] = $row;
                            }
                        }
                        $tmp = explode(',',$uids);
                        foreach($tmp as $_uid){
                            if(!isset($user_data[$_uid])) continue;
                            $new_set_id = fun::appendSome($user_data[$_uid]['set_id'],$w['id']);
                            $sql = "update bay_users set set_id = '{$new_set_id}',editor = {$this->_uid},etime = {$time} WHERE uid = {$_uid} ";
                            $this->db->query($sql);
                        }
                    }
                }elseif($w['del'] == 1){//删除
                    $this->db->query("update bay_site set set_id = 0 WHERE set_id = {$w['id']}");
                    $sql = "select uid,set_id from bay_users where find_in_set(set_id,{$w['id']})";
                    $rel = $this->db->query($sql)->result_array();
                    if($rel){
                        foreach($rel as $row){
                            $new_set_id = fun::delSome($row['set_id'],$w['id']);
                            $sql = "update bay_users set set_id = '{$new_set_id}',editor = {$this->_uid},etime = {$time} WHERE uid = {$row['uid']} ";
                            $this->db->query($sql);
                        }
                    }
                }
            }else{//编辑
                //变编辑游戏组
                $this->db->set($data)->where('id',$w['id'])->update('game_group');
                //先查找原来拥有的
                $sql = "select * from bay_site WHERE set_id = {$w['id']}";
                $rel = $this->db->query($sql)->result_array();
                $first = array();//之前拥有
                if($rel){
                    foreach($rel as $row){
                        $first[] = $row['gid'] . '.' . $row['sid'];
                    }
                }
                $now = array();//现在有的
                if($gids){
                    $now = explode(',',$gids);
                }
                $del_sid = array_diff($first,$now);
                $add_sid = array_diff($now,$first);
                //删除
                if($del_sid){
                    $sql = "update bay_site set set_id = 0 WHERE ";
                    foreach($del_sid  as $row){
                        $tmp = explode('.',$row);
                        if(isset($tmp[0]) && isset($tmp[1])){
                            $sql .= " (gid = {$tmp[0]} and sid = {$tmp[1]}) OR";
                        }
                    }
                    $sql = rtrim($sql,'OR');
                    $this->db->query($sql);
                }
                //新加
                if($add_sid){
                    $sql = "update bay_site set set_id = {$w['id']} WHERE ";
                    foreach($add_sid  as $row){
                        $tmp = explode('.',$row);
                        if(isset($tmp[0]) && isset($tmp[1])){
                            $sql .= " (gid = {$tmp[0]} and sid = {$tmp[1]}) OR";
                        }
                    }
                    $sql = rtrim($sql,'OR');
                    $this->db->query($sql);
                }
                //用户权限
                $sql = "select uid,set_id from bay_users WHERE find_in_set({$w['id']},set_id)";
                $rel = $this->db->query($sql)->result_array();
                $first = array();//编辑器拥有
                if($rel){
                    $user_data = array();
                    foreach($rel as $row){
                        $first[] = $row['uid'];
                        $user_data[$row['uid']] = $row;
                    }
                }
                $last = array();
                if($uids){
                    $last = explode(',',$uids);
                }
                $del_user = array_diff($first,$last);
                if($del_user){
                    foreach($del_user as $_uid){
                        $new_set_id = fun::delSome($user_data[$_uid]['set_id'],$w['id']);
                        $sql = "update bay_users  set set_id = '{$new_set_id}',editor = {$this->_uid},etime = {$time} WHERE uid = {$_uid} ";
                        $this->db->query($sql);
                    }
                }
                $add_user = array_diff($last,$first);
                if($add_user){
                    $rel = $this->db->where_in('uid',$add_user)->get('users')->result_array();
                    if($rel){
                        $user_data = array();
                        foreach($rel as $row){
                            $user_data[$row['uid']] = $row;
                        }
                        foreach($add_user as $_uid){
                            $new_set_id = fun::appendSome($user_data[$_uid]['set_id'],$w['id']);
                            $sql = "update bay_users set set_id = '{$new_set_id}',editor = {$this->_uid},etime = {$time} WHERE uid = {$_uid} ";
                            $this->db->query($sql);
                        }
                    }

                }
            }
            //刷新用户缓存
        }else{//新加
            $this->db->insert('game_group');
            $id = $this->db->insert_id();
            if($gids){
                $tmp = explode(',',$gids);
                $sql = "update bay_site set set_id = {$id} WHERE ";
                foreach($tmp as $row){
                    $_tmp = explode('.',$row);
                    if(isset($_tmp[0]) && isset($_tmp[1])){
                        $sql .= " (gid = {$_tmp[0]} and sid = {$_tmp[1]}) OR";
                    }
                }
                $sql = rtrim($sql,'OR');
                $this->db->query($sql);
            }
            if($uids){
                $last = explode(',',$uids);
                $sql = "select set_id,uid from bay_users WHERE uid in ({$uids})";
                $rel = $this->db->query($sql)->result_array();
                $udata = array();
                if($rel){
                    foreach($rel as $row){
                        $udata[$row['uid']] = $row['set_id'];
                    }
                }
                foreach($last as $uid){
                    $new_set_id = fun::appendSome($udata[$uid],$id);
                    $sql = "update bay_users set set_id = '{$new_set_id}',editor = {$this->_uid},etime = {$time} WHERE uid = {$uid} ";
                    $this->db->query($sql);
                }
            }
        }
        $this->db->trans_complete();
        //刷新缓存
        $this->flushGroup();
        //用户权限缓存
        $this->load->model('auth/Auth_model');
        $this->Auth_model->_flushPer($time);
        //权限缓存??
        if ($this->db->trans_status() === FALSE){//失败
            return "保存失败，请重试";
        }else{//成功
            return true;
        }
    }

    /**
     * 刷新游戏站点缓存
     * @date
     */
    public function flushGame(){
        $f = DATA_ROOT.'cache/game.php';
        $rel = $this->db->select('id,name, api, mid_url')->where('del','0')->get('game')->result_array();
        //写缓存
        $ret = array();
        foreach($rel as $rs){
            $id = $rs['id'];
            unset($rs['id']);
            $ret[$id] = $rs;
        }
        $out = load_class('OP','helpers');
        $out->php($f,$ret);
    }

    /**
     * 刷新站点缓存(删除所有缓存文件)
     * @param int $gid
     * @date
     */
    public function flushSite($gid = 0){
        $dir = 'gamesite/';
        if($gid) $dir .= $gid;
        fun::cache($dir, 'clear');
    }

    /**
     * 更新游戏站点(触发更新)
     * @param $gid
     * @param $data
     * @date
     */
    public function updateSite($gid,$gname,$data){
        if($gid && $data){
            $time = time();
            $sql = "insert into bay_site (gid,sid,gname,lname,lang,langs,etime,del) VALUES";
            foreach($data  as $row){
                $sql .= "({$gid},{$row['id']},'{$gname}','{$row['name']}','{$row['lang']}','{$row['langs']}',{$time},0),";
            }
            $sql = rtrim($sql,',');
            $sql .= "ON DUPLICATE KEY UPDATE gname=VALUES(gname),lname=VALUES(lname),lang=VALUES(lang),langs=VALUES(langs),etime=VALUES(etime),del=VALUES(del)";
            $this->db->query($sql);
            $_time = $time - 3600*24*7;//7天内拉取不到就标记删除
            $_sql = "update bay_site set del = 1 WHERE etime < {$_time} AND gid = {$gid}";
            $this->db->query($_sql);
        }

    }

    /**
     * 刷新游戏组缓存
     * @date
     */
    public function flushGroup(){
        $f = DATA_ROOT.'cache/gameGroup.php';
        $rel = $this->db->select('id,name')->where('del','0')->get('game_group')->result_array();
        //写缓存
        $ret = array();
        if($rel){
            $w = array(
                'del' => 0,
                'set_id !=' => 0
            );
            $rels = $this->db->where($w)->get('site')->result_array();
            $sids = array();
            if($rels){
                foreach($rels as $row){
                    $sids[$row['set_id']][] = $row['gid'] . '.' . $row['sid'];
                }
            }
            foreach($rel as $rs){
                $id = $rs['id'];
                if(isset($sids[$id])){
                    $ret[$id] = array(
                        'name' => $rs['name'],
                        'sids' => implode(',',$sids[$id])
                    );
                }
            }
        }
        $out = load_class('OP','helpers');
        $out->php($f,$ret);
    }

    /**
     * 获取游戏组
     * del 0:获取未删除的，1：获取删除的，999：获取全部
     * @param array $val
     * @param array $field
     * @param bool|true $limit
     * @param bool|true $name
     * @return array
     * @date
     */
    public function getGroupList($val = array(),$field = array(),$limit = true,$name = true){
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
        if(isset($w['name'])){
            $this->db->like('name',$w['name']);
        }

        $this->db->from('game_group');
        if($limit){//分页
            $num = $this->db->count_all_results('',false);
            $this->getLimit($this->db);
        }else{
            $num = 0;
        }
        $rel = $this->db->get()->result_array();
//        var_dump($this->db->last_query());die;
        if($rel && $name){
            $users = fun::getALLUserData();
            foreach($rel as &$row){
                $row['uid'] = $this->getGroupUid($row['id']);
                $row['sids'] = $this->getGroupSid($row['id']);
                $row['ename'] = $users[$row['editor']]['name'];
            }
        }
        return  (array($rel,$num));
    }

    /**
     * 获取未被分配的游戏站点
     * @return array
     * @date
     */
    public function getUnusedSid(){
        $sql = "select * from bay_site where set_id = 0  and del = 0";
        $rel = $this->db->query($sql)->result_array();
        $ret = array();
        if($rel){
            foreach($rel as $row){
                if(!isset($ret[$row['gid']]['id'])) $ret[$row['gid']]['id'] = $row['gid'];
                if(!isset($ret[$row['gid']]['name'])) $ret[$row['gid']]['name'] = $row['gname'];
                $ret[$row['gid']]['child'][$row['sid']] = $row['sid'] . ')' . $row['lname'];
            }
        }
        return $ret;
    }

    /**
     * 获取游戏组下面的人
     * @param $set_id
     * @return array
     * @date
     */
    public function getGroupUid($set_id){
        $sql = "select * from bay_users where find_in_set({$set_id},set_id) AND del = 0";
        $rel = $this->db->query($sql)->result_array();
        $uid = array();
        if($rel){
            foreach($rel as $row){
                $uid[] = $row['uid'];
            }
        }
        return $uid;
    }

    /**
     * 获取游戏组下面的游戏站点
     * @param $set_id
     * @return array
     * @date
     */
    public function getGroupSid($set_id){
        $sql = "select * from  bay_site WHERE set_id = {$set_id}";
        $rel = $this->db->query($sql)->result_array();
        $sids = array();
        if($rel){
            foreach($rel as $row){
                $sids[$row['gid'] . '.' . $row['sid']] = $row['gname'] . '-' . $row['lname'];
            }
        }
        return $sids;
    }

    /**
     * 获取用户游戏组
     * @param $uid
     * @return array
     * @date
     */
    public function getUserSetId($uid){
        $ret = array();
        $srel = fun::getUserPer($uid,'game_group');
        if($srel){
            $grps = fun::getGameGroup($srel);
            $all = array();
            foreach($grps as $set_id => $row){
                $all[] = $set_id;
                $_t = array();
                if($row['sids']){
                    $name = fun::getGameName($row['sids'],0,true);
                    if($name){
                        foreach($name as $sid => $n){
                            $_t[] = array(
                                'id' => $sid,
                                'name' => $n
                            );
                        }
                    }
                }
                $ret[] = array(
                    'id' => $set_id,
                    'name' => $row['name'],
                    'sids' => $_t
                );
            }
            $a = array(
                'id' => implode(',',$all),
                'name' => '全部',
                'sids' => array(),
            );
            array_unshift($ret,$a);
        }
        return $ret;
    }
}