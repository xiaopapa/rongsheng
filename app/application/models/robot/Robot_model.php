<?php

/**
 * 机器人model
 * Class Robot_model
 * @author harvey
 * @Date: 16-12-29
 * @license  http://www.boyaa.com/
 */
class Robot_model extends Bmax_Model {
    private $ndb;
    private $odb;
    private $m;
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

    /**
     * 检查是否及已经有一条设置了
     * @param $gid
     * @param $sid
     * @return bool
     * @date
     */
    public function hasSet($gid,$sid){
        $sql = "SELECT * FROM bay_robot WHERE gid = {$gid} and sid = {$sid}";
        $query = $this->db->query($sql);
        if($query->result()){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 获取站点信息
     * @param $gid
     * @param $sid
     * @return mixed
     * @date
     */
    public function getSetting($gid,$sid){
        $sql1 = "SELECT * FROM bay_robot WHERE sid = {$sid} and gid = {$gid}";
        $sql2 = "SELECT * FROM bay_mbfaqcate WHERE sid = {$sid} and gid = {$gid}";
        $sql3 = "SELECT * FROM bay_mbfaq WHERE sid = {$sid} and gid = {$gid}";
        $rel1 = $this->db->query($sql1)->result_array();
        $rel2 = $this->db->query($sql2)->result_array();
        $cate = array();
        if($rel2){
            foreach($rel2 as $row){
                $cate[$row['id']] = $row['title'];
            }
        }
        $rel3 = $this->db->query($sql3)->result_array();
        $faq = array();
        if($rel3){
            foreach($rel3 as $row){
                $faq[$row['id']] = $row['question'];
            }
        }
        if($rel1){//数据处理
            foreach($rel1 as &$row){
                if($row['hid']){
                    $tmp = explode(',',$row['hid']);
                    foreach($tmp as $r){
                        $t = explode('.',$r);
                        if($t[0] == 'h'){//问题ID
                            $row['hname'][] = array('id' => "h.".$t[1],'name' => isset($faq[$t[1]]) ? $faq[$t[1]] : '');
//                            $row['hname']["h.".$t[1]] = isset($faq[$t[1]]) ? $faq[$t[1]] : '';
                        }elseif($t[0] == 'c'){
                            $row['hname'][] = array('id' => "c.".$t[1],'name' => isset($cate[$t[1]]) ? $cate[$t[1]] : '');
//                            $row['hname']["c.".$t[1]] = isset($cate[$t[1]]) ? $cate[$t[1]] : '';
                        }
                    }
                }
            }
        }
        return $rel1;
    }

    /**
     * 保存机器人头设置信息
     * @param $field
     * @return mixed
     * @date
     */
    public function saveData($field){
        $this->m->table('robot')->setValue($field);
        $this->m->value['etime'] = time();
        $this->m->value['editor'] = $this->_uid;
        if($this->hasSet($this->_gid,$this->_sid)){//更新
            $where = "gid = {$this->_gid} and sid = {$this->_sid}";
            $sql = $this->m->update($where,true,true);
        }else{//新增
            $sql = $this->m->insert(true,true);
        }
        $rel = $this->db->query($sql);
        return $rel;
    }

    /**
     * 获取分类
     * @param $gid
     * @param $sid
     * @return mixed
     * @date
     */
    public function getCate($gid,$sid){
        $sql = "SELECT * FROM bay_mbfaqcate WHERE sid = {$sid} and gid = {$gid} and del != 1";
        $query = $this->db->query($sql);
        return $query->result_array();
    }

    /**
     * 新增或者更新/删除（del设为1）一条分类
     * @param $field
     * @param string $where
     * @param array $set
     * @return bool
     * @date
     */
    public function setCate($field,$where = '',$set = array()){
        $this->m->table('mbfaqcate')->setValue($field);
        if($set && is_array($set)){//补充值
            foreach($set as $key => $row){
                $this->m->value[$key] = $row;
            }
        }
        $new_id = 0;
        $this->m->value['etime'] = time();
        $this->m->value['editor'] = $this->_uid;
        if(!$where){//新增,事务提交
            $fid = (int) fun::get('fid');
            $sql = $this->m->insert(true,true);
            $this->db->trans_start();//事务开启
            $this->db->query($sql);
            $id = $this->db->insert_id();
            $new_id = $id;
            //如果fid不为0
            if($fid){
                $sql = "SELECT * FROM `bay_mbfaqcate` WHERE id = '{$fid}'";
                $rel = $this->db->query($sql);
                $data = $rel->result_array();
                if(!$data) return false;
                $data = $data[0];
                $node = $data['node'];
                if($node){//更新祖先节点子ID
                    $this->_addNode($node,$id);
                }
            }
            //更新新插入的node
            $child_node = (isset($node) && $node) ?  $node . ",{$id}" : $id;
            $sql = "UPDATE `bay_mbfaqcate` set node = '{$child_node}' WHERE id = {$id}";
            $this->db->query($sql);
            $this->db->trans_complete();//事务提交

        }else{//更新或删除
            $del = (int) fun::get('del');
            if($del == 1){//删除操作,事务
                $id = fun::get('id');
                $this->db->trans_start();//事务开启
                $sql = $this->m->update($where,true,true);
                $this->db->query($sql);
                //删除改ID下面所有子ID
                //删除热门问题里面
                $sql = "SELECT * FROM `bay_mbfaqcate` WHERE id = {$id}";
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
                    //删除机器人设置中的热门问题
                    $this->_delHot($child_id,'c');
                }
                $this->db->trans_complete();//事务提交
            }else{//更新
                $sql = $this->m->update($where,true,true);
                $rel = $this->db->query($sql);
            }
            if(!$rel) return false;
        }
        return $new_id ? $new_id : true;
    }

    /**
     * 为祖先节点加子节点
     * @param $fid
     * @param $cid
     * @date
     */
    private function _addNode($fid,$cid){
        $fid = explode(',',$fid);
        foreach($fid as $key => $row){
            $sql = "SELECT * FROM `bay_mbfaqcate` WHERE id = {$row}";
            $rel = $this->db->query($sql)->result_array();
            if(isset($rel[0]['child_id'])){
                $temp = $rel[0]['child_id'];
                $new_str = $temp ? $temp . ",{$cid}" : $cid;
                $sql = "UPDATE  `bay_mbfaqcate` SET child_id = '{$new_str}' WHERE id = {$row}";
                $this->db->query($sql);//更新子节点
            }else{//警报？

            }
        }
    }

    /**
     * 分类修改处理父节节点子ID
     * @param $fid
     * @param $child_id
     * @date
     */
    private function _dealNode($fid,$child_id){
        $fid = explode(',',$fid);
        $child_id = explode(',',$child_id);
        foreach($fid as $key => $row){
            $sql = "SELECT * FROM `bay_mbfaqcate` WHERE id = {$row}";
            $rel = $this->db->query($sql)->result_array();
            if(isset($rel[0]['child_id']) && $rel[0]['child_id']){
                $temp = $rel[0]['child_id'];
                $temp = explode(',',$temp);
                $new = array_diff($temp,$child_id);
                sort($new);
                $new_str = implode(',',$new);
                $sql = "UPDATE  `bay_mbfaqcate` SET child_id = '{$new_str}' WHERE id = {$row}";
                $this->db->query($sql);//更新子节点
            }else{//警报？

            }
        }
    }

    /**
     * 删除子ID
     * @param $child_id
     * @date
     */
    private function _delChild($child_id){
        $sql = "UPDATE `bay_mbfaqcate` set del = 1 WHERE id IN ({$child_id})";
        $this->db->query($sql);
    }

    /**
     * 删除机器人设置中的热门问题(分类或者知识库id)
     * @param $id
     * @param $type
     * @return bool
     * @date
     */
    private function _delHot($id,$type){
        if(!in_array($type,array('c','h'))) return false;
        $tmp = explode(',',$id);
        $time = time();
        foreach($tmp as $row){
            $key = $type .'.' . $row;
            $sql = "SELECT id,hid FROM `bay_robot` where FIND_IN_SET('{$key}',hid)";
            $rel = $this->db->query($sql)->result_array();
            if($rel){
                foreach($rel as $d){
                    $new_hid = fun::delSome($d['hid'],$key,false);
                    $u_sql = "update `bay_robot` set hid = '{$new_hid}',etime = {$time},editor = {$this->_uid} WHERE id = {$d['id']}";
                    $this->db->query($u_sql);
                }
            }
        }
        return true;
    }


    /**
     * 获取知识库
     * @return mixed
     * @date
     */
    public function getKms(){
        $where = fun::setWhere(array(
            'gid' => 0,
            'sid' => 0,
            'question' => 1,
        ));
        $cid = fun::get('cid');
        $gid = fun::get('gid');
        $sid = fun::get('sid');
        //如果是按分类查询，那还需要查找该分类的子id
        if(fun::get('cid',false) !== false){
            $sql = "select child_id from bay_mbfaqcate WHERE id = {$cid} AND gid = {$gid} AND sid = {$sid}";
            $child_id = $this->db->query($sql)->row_array();
            if($child_id['child_id']){
                $child_id = $child_id['child_id'];
                $where .= " and cid in ( {$cid}," . $child_id . " ) " ;

            }else{
                $where .= " and cid = {$cid}";
            }
        }
        if(fun::get('del',false) !== false){
            $where .= " and del = " . fun::get('del');
        }
        $id = (int) fun::get('id');
        if($id){//如果传了ID，只用ID来查找
            $where = "id = {$id}";
        }else{
            if(fun::get('status',false) !== false) $where .= ' and status = ' . (int) fun::get('status');
        }

        $so = array(
            'question' => 1,//模糊查找标题
            'editor' => 0,
        );
        $order = 'etime desc';
        $sql = $this->m->table('bay_mbfaq')->where($where,$so)->limit()->order($order)->getSql();
//        var_dump($sql);die;
        $ret = $this->db->query($sql)->result_array();
        if($ret){
            //名字
            $user = fun::getUserData();
            //分类
            $cate = $this->getCate($gid,$sid);
            $c = array();
            if($cate){
                foreach($cate as $r){
                    $c[$r['id']] = $r['title'];
                }
            }
            foreach($ret as &$row){
                $row['ename'] = isset($user[$row['editor']]['name']) ? $user[$row['editor']]['name'] : '';
                $row['cate_name'] = isset($c[$row['cid']]) ? $c[$row['cid']] : '';
            }

        }
        return $ret;
    }

    /**
     * 新增或者更新/删除（del设为1）一条知识库内容
     * @param $field
     * @param string $id
     * @param array $set
     * @return mixed
     * @date
     */
    public function setKms($field,$id = '',$set = array()){
        $this->m->table('mbfaq')->setValue($field);
        if($set && is_array($set)){//补充值
            foreach($set as $key => $row){
                $this->m->value[$key] = $row;
            }
        }
        $this->m->value['etime'] = time();
        $this->m->value['editor'] = $this->_uid;
        if(!$id){//新增
            $sql = $this->m->insert(true,true);
        }else{//更新或删除
            if(strpos(',',$id) !== false){
                $where = "id in ({$id})";
            }else{
                $where = "id = {$id}";
            }
            if(isset($this->m->value['del']) && $this->m->value['del'] == 1){//删除热门问题
                $this->_delHot($id,'h');
            }
            $sql = $this->m->update($where,true,true);

        }
        $rel = $this->db->query($sql);
        return $rel;
    }

    /**
     * 获取同义词
     * @return mixed
     * @date
     */
    public function getSyn(){
        $where = fun::setWhere(array(
            'gid' => 0,
            'title|similar' => 1,//模糊查找标题,同义词，or关系
        ));
        $so = array(
            'title|similar' => 1,//模糊查找标题,同义词，or关系
        );
        $order = 'etime desc';
        $sql = $this->m->table('bay_synonym')->where($where,$so)->limit()->order($order)->getSql();
        $query = $this->db->query($sql);
        return $query->result_array();
    }

    /**
     * 新增或者更新/删除/恢复（del设为1）一条同义词(已弃用）
     * @param $field
     * @param string $where
     * @param array $set
     * @return mixed
     * @date
     */
    public function setSyn($field,$where = '',$set = array()){
        $this->m->table('synonym')->setValue($field);
        if($set && is_array($set)){//补充值
            foreach($set as $key => $row){
                $this->m->value[$key] = $row;
            }
        }
        $this->m->value['etime'] = time();
        $this->m->value['editor'] = $this->_uid;
        if(!$where){//新增
            $sql = $this->m->insert(true,true);
        }else{//更新或删除/恢复
            $sql = $this->m->update($where,true,true);
        }
        $rel = $this->db->query($sql);
        return $rel;
    }

    /**
     * 获取关键字
     * @param $val
     * @param $field
     * @param bool|true $limit
     * @return array
     * @date
     */
    public function getKeyword($val,$field,$limit = true){
        $this->_getValue($field,$val,$this->db,1);
        $this->db->from('keyword');
        if($limit){//分页
            $num = $this->db->count_all_results('',false);
            $this->getLimit($this->db);
        }else{
            $num = 0;
        }
        $rel = $this->db->get()->result_array();
        return array($rel,$num);
    }

    /**
     * 检查一条关键词是否已经存在了
     * @param $gid
     * @param $sid
     * @param $content
     * @param int $id
     * @return bool
     * @date
     */
    public function checkKey($gid,$sid,$content,$id = 0){
        $w = array(
            'gid' => $gid,
            'sid' => $sid,
            'content' => $content,
            'del' => 0
        );
        if($id) $this->db->where("id != {$id}");
        $ret = $this->db->where($w)->get('keyword')->row_array();
        if($ret){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 判断一条要恢复的关键字是否有重复
     * @param $id
     * @return bool
     * @date
     */
    public function checkKeyById($id){
        $rel = $this->db->where('id',$id)->get("keyword")->row_array();
        if($rel){
            $gid = $rel['gid'];
            $sid = $rel['sid'];
            $content = $rel['content'];
            $ret = $this->checkKey($gid,$sid,$content);
            return $ret;
        }else{
            return true;//id有错
        }

    }
    /**
     * 新增，编辑/删除 关键词
     * @param $field
     * @param string $id
     * @param array $set
     * @return mixed
     * @date
     */
    public function setKeyword($field,$id = '',$set = array()){
        $this->m->table('keyword')->setValue($field);
        if($set && is_array($set)){//补充值
            foreach($set as $key => $row){
                $this->m->value[$key] = $row;
            }
        }
        $this->m->value['etime'] = time();
        $this->m->value['uid'] = $this->_uid;
        if(!$id){//新增
            $sql = $this->m->insert(true,true);
        }else{//更新或删除/恢复
            if(strpos(',',$id) !== false){
                $where = "id in ({$id})";
            }else{
                $where = "id = {$id}";
            }
            $sql = $this->m->update($where,true,true);
            if(isset($this->m->value['del']) && $this->m->value['del'] == 1){//删除问题中的关键词
                $this->_delKmsKey($id);
            }
        }
        $rel = $this->db->query($sql);
        return $rel;
    }

    /**
     * 删除知识库中的关键词
     * @param $id
     * @return bool
     * @date
     */
    private function _delKmsKey($id){
        $tmp = explode(',',$id);
        $time = time();
        foreach($tmp as $row){
            $sql = "SELECT id,keyword FROM `bay_mbfaq` where FIND_IN_SET('{$row}',keyword)";
            $rel = $this->db->query($sql)->result_array();
            if($rel){
                foreach($rel as $d){
                    $new_key = fun::delSome($d['keyword'],$row,false);
                    $u_sql = "update `bay_mbfaq` set keyword = '{$new_key}',etime = {$time},editor = {$this->_uid} WHERE id = {$d['id']}";
                    $this->db->query($u_sql);
                }
            }
        }
        return true;
    }

    //导入分类
    public function importCate($data,$gid,$sid){
        $done = array();
        $index = array();
        $this->db->trans_start();//事务开启
        $time = time();
        //先插入数据
        foreach($data as $key => $row){
            $sql = "INSERT INTO `bay_faqcate` SET gid = {$gid},sid = {$sid},title = '{$row['title']}',etime = $time,editor = {$this->_uid}";
            $this->db->query($sql);
            $id = $this->db->insert_id();
            $row['nid'] = $id;
            $done[] = $row;
            //新旧ID映射
            $index[$row['id']] = $id;
        }
        //更新node和child_id
        foreach($data as $key => $row){
            //更新node
            if($row['node']){
                $temp = explode(',',$row['node']);
                $temp = sort($temp);//排一下顺序
                $_temp = array();
                foreach($temp as $r){
                    $_temp[] = $index[$r];
                }
                $node = implode(',',$_temp);
                $sql = "UPDATE `bay_faqcate` SET node = '{$node}' WHERE id = {$row['nid']}";
                $this->db->query($sql);
            }
            //更新child_id
            if($row['child_id']){
                $temp = explode(',',$row['child_id']);
                $temp = sort($temp);//排一下顺序
                $_temp = array();
                foreach($temp as $r){
                    $_temp[] = $index[$r];
                }
                $child_id = implode(',',$_temp);
                $sql = "UPDATE `bay_faqcate` SET child_id = '{$child_id}' WHERE id = {$row['nid']}";
                $this->db->query($sql);
            }
        }

        $this->db->trans_complete();//事务提交
        //检查状态
        if ($this->db->trans_status() === FALSE){
            fun::codeBack('操作不成功，请重试~',500);
        }
        return true;

    }

    /**
     * 获取未解决问题
     * @return array
     * @date
     */
    public function noResolved(){
        $gid = fun::get('gid');
        $sid = fun::get('sid');
        $where = " click_type = 11 AND click_value = 2 and ext != ''";
        if($gid){
            $where .= " and gid = " . $gid;
        }
        if($sid){
            $where .= " and site_id = " . $sid;
        }
        $limit = $this->m->getLimit();
        $sql = "select count(*) as total,ext from t_clicks_log WHERE {$where} GROUP BY ext ORDER BY  total DESC {$limit}";
        $_sql = "select id from t_clicks_log WHERE {$where} GROUP BY ext";
        $this->getODb();
        $ret = $this->odb->query($sql)->result_array();
        $num = $this->odb->query($_sql)->result_array();
        return array($ret,count($num));
    }

    /**
     * 关键词排行榜
     * @param $gid
     * @param $sid
     * @return array
     * @date
     */
    public function getKeySort($gid,$sid){
        $where = " gid = {$gid} and site_id = {$sid} and click_type = 2 ";
        $limit = $this->m->getLimit();
        $sql = "select count(*) as total,click_text from t_clicks_log WHERE {$where} GROUP BY click_text ORDER BY  total DESC {$limit}";
        $_sql = "select id from t_clicks_log WHERE {$where} GROUP BY click_text ";
        $this->getODb();
        $ret = $this->odb->query($sql)->result_array();
        $num = $this->odb->query($_sql)->result_array();
        return array($ret,count($num));
    }

    /**
     * 接待用户数
     * @return mixed
     * @date
     */
    public function serverNum(){
        $gid = fun::get('gid');
        $sid = fun::get('sid');
        $where = " click_type = 11 AND click_value = 2 and ext != ''";
        if($gid){
            $where .= " and gid = " . $gid;
        }
        if($sid){
            $where .= " and site_id = " . $sid;
        }
        $sql = "select count(*) as total,ext from t_clicks_log WHERE {$where} GROUP BY ext ORDER BY  total DESC ";
        $this->getODb();
        $query = $this->odb->query($sql);
        return $query->result_array();
    }

    /**
     * 转人工用户数
     * @return mixed
     * @date
     */
    public function changeNum(){
        $gid = fun::get('gid');
        $sid = fun::get('sid');
        $where = " click_type = 11 AND click_value = 2 and ext != ''";
        if($gid){
            $where .= " and gid = " . $gid;
        }
        if($sid){
            $where .= " and site_id = " . $sid;
        }
        $sql = "select count(*) as total,ext from t_clicks_log WHERE {$where} GROUP BY ext ORDER BY  total DESC ";
        $this->getODb();
        $query = $this->odb->query($sql);
        return $query->result_array();
    }

    /**
     * 用户对话查找
     * @return array
     * @date
     */
    public function getDialogue(){
        $where = '1';
        $gid = fun::get('gid');
        $sid = fun::get('sid');
        $click = fun::get('click');
        if($gid){
            $where .= " and gid = " . $gid;
        }
        if($sid){
            $where .= " and site_id = " . $sid;
        }
        if(fun::get('click',false) !== false){
            $where .= " and click = " . fun::get('click');
        }
        $id = (int) fun::get('id');
        if($id){//如果传了ID，只用ID来查找
            $where = "id = {$id}";
        }
        $order = 'clock desc';
        $sql = $this->m->table('bot_accept_log','t_')->where($where)->limit()->order($order)->getSql();
        $this->getODb();
        $ret = $this->odb->query($sql)->result_array();
        $num = $this->countPage($this->odb);
        return array($ret,$num);
    }

    /**
     * 用户对话查找
     * @param $filed
     * @return mixed
     * @date
     */
    public function setDialogue($filed){
        $this->m->table('bot_accept_log','t_')->setValue($filed);
        $where = "id = " . fun::get('id');
        $this->getODb();
        $sql = $this->m->update($where,true,true);
        return $this->odb->query($sql);
    }

    /**
     * 用户接客
     * @param $st
     * @param $ed
     * @return array|bool
     * @date
     */
    public function jieKe($st,$ed){
        if(!$st || !$ed || $st > $ed){
            return false;
        }
        $where = "clock >= {$st} and clock < {$ed}";
        $gid = fun::get('gid');
        $sid = fun::get('sid');
        if($gid){
            $where .= " and gid = " . $gid;
        }
        if($sid){
            $where .= " and site_id = " . $sid;
        }
        $sql = "Select id,clock from t_bot_accept_log WHERE {$where}";
        $this->getODb();
        $rel= $this->odb->query($sql)->result_array();
        if($rel){
            $data = array();
            $n = 0;
            list($day,$time) = fun::timeToPeriod($st,$ed);
            $len = count($day) -1;//循环次数
            foreach($rel as $key => $row){
                if($row['clock'] >= $time[$n] && $row['clock'] < $time[$n+1]){
                    $data[$n][] = $row;
                }else{
                    for($i=0;$i < $len;$i++){
                        if($row['clock'] >= $time[$i] && $row['clock'] < $time[$i+1]){
                            $n = $i;
                            $data[$n][] = $row;
                            break;
                        }
                    }
                }
            }
            //统计数据
            $ret = array();
            $d = array();
            $t = array();
            foreach($data as $k =>$row){
                $d[] = count($row);
                $t[] = $day[$k];
            }
            $ret = array(
                'series'=> array(
                    array('name' => '接待用户数','data' => $d)
                ),
                'categories' => $t
            );
        }else{
            $ret = array('code' => 404,'msg' => '没找到数据~');
        }
        return $ret;
    }

    /**
     * 机器人转人工
     * @param $st
     * @param $ed
     * @return array|bool
     * @date
     */
    public function getRobotChange($st,$ed){
        if(!$st || !$ed  || $st > $ed){
            return false;
        }
        $where = "clock >= {$st} and clock < {$ed} and shift_type = 1";
        $gid = fun::get('gid');
        $sid = fun::get('sid');
        if($gid){
            $where .= " and gid = " . $gid;
        }
        if($sid){
            $where .= " and site_id = " . $sid;
        }
        $sql = "Select id,clock from t_chat_shift WHERE {$where}";
        $this->getODb();
        $rel= $this->odb->query($sql)->result_array();
        if($rel){
            $data = array();
            $n = 0;
            list($day,$time) = fun::timeToPeriod($st,$ed);
            $len = count($day) -1;//循环次数
            foreach($rel as $key => $row){
                if($row['clock'] >= $time[$n] && $row['clock'] < $time[$n+1]){
                    $data[$n][] = $row;
                }else{
                    for($i=0;$i < $len;$i++){
                        if($row['clock'] >= $time[$i] && $row['clock'] < $time[$i+1]){
                            $n = $i;
                            $data[$n][] = $row;
                            break;
                        }
                    }
                }
            }
            //统计数据
            $ret = array();
            $d = array();
            $t = array();
            foreach($data as $k =>$row){
                $d[] = count($row);
                $t[] = $day[$k];
            }
            $ret = array(
                'series'=> array(
                    array('name' => '转人工客服数','data' => $d)
                ),
                'categories' => $t
            );
        }else{
            $ret = array('code' => 404,'msg' => '没找到数据~');
        }
        return $ret;
    }


    /**
     * 导入机器人faq数据(faq分类，faq内容)
     * @param $data               array     faq内容
     * @param $cate               array     faq分类
     * @param $gid                int       游戏id
     * @param $sid                int       站点id
     * @return bool
     * @date
     */
    public function importFAQ($data,$cate,$gid,$sid){
        //先锁表写
        $this->db->query("LOCK TABLE `bay_mbfaqcate` WRITE");
        //开启事务
        $this->db->trans_start();
        $w = array('gid' => $gid,'sid' => $sid,'del' => 0);
//        var_dump($data);die;
        //先处理分类
        //这里分类主键id自定义,若果是因为期间其他地方插入了新的分类，导致id冲突，插入分类会失败
        //失败事务回滚在重新执行，直到成功就好了
        $sql = "select id from bay_mbfaqcate ORDER by id desc limit 1";
        $last_id = $this->db->query($sql)->row_array();
        $last_id = (int) $last_id['id'];//最后一条ID
        //分类先自己决定插入的ID，防止锁表等级不同读取不了未提交的数据
        $cate_ref = array();
        foreach($cate as $k => &$c){
            /**
             *  $k =>  0 : id, 1 : fid, 2 : title
             */
            $last_id ++;//id自增
            $c['new_id'] = $last_id;
            $cate_ref[$c[0]] = $last_id;//新旧ID映射
            if($c['1']){
                if(null == $cate_ref[$c['1']]){//防止旧数据导出有问题
                    fun::codeBack("导入的分类资料有误,找不到这个旧的分类的fID值，分类ID为{$c[0]},请跟开发联系！");
                }else{
                    $c['new_fid'] = $cate_ref[$c['1']];
                }
            }else{
                $c['new_fid'] = 0;
            }
        }
        //处理完成分类，开始在数据库中插入分类(逐条插入)
        $time = time();
        $editor = $this->_uid;
//        var_dump($cate_ref);
//        var_dump($cate);
//        var_dump(count($cate));die;
        foreach($cate as $k => $ca){
            $node = null;
            $sql = "insert into bay_mbfaqcate set gid = {$gid},sid = {$sid},id = {$ca['new_id']},title = '" . fun::magic_quote($ca['2']) . "', fid = {$ca['new_fid']},etime = {$time},editor = {$editor}";
//            var_dump($sql);continue;
            $this->db->query($sql);
            $id = $new_id = $ca['new_id'];
            $fid = $ca['new_fid'];
            if($fid){
                $sql2 = "SELECT * FROM `bay_mbfaqcate` WHERE id = '{$fid}'";
                $c_fid = $this->db->query($sql2)->result_array();
                if(!$c_fid) return false;
                $c_fid = $c_fid[0];
                $node = $c_fid['node'];
                if($node){//更新祖先节点子ID
                    $this->_addNode($node,$id);
                }
            }
            //更新新插入的node
            $child_node = (isset($node) && $node) ?  $node . ",{$id}" : $id;
            $sql = "UPDATE `bay_mbfaqcate` set node = '{$child_node}' WHERE id = {$id}";
            $this->db->query($sql);
        }
        //关键词输处理
        $rel = $this->db->where($w)->get('keyword')->result_array();
        $keyword = array();
        if($rel){
            foreach($rel as $row){
                $keyword[$row['content']] = $row['id'];
            }
        }
        foreach($data as $k => $row){
            /**
             *  $k =>  0 : question, 1 : answer, 2 : keyword , 3 : fid
             */
            $sql = '';
            $data = array(
                'gid' => $gid,
                'sid' => $sid,
                'editor' => $this->_uid,
                'cid' => $cate_ref[$row[3]] ? $cate_ref[$row[3]] : 0,//映射到新id
                'etime' => time(),
//                'question' => mb_convert_encoding($row[0],"UTF-8", "GBK"),
//                'answer' => mb_convert_encoding($row[1],"UTF-8", "GBK"),
                'question' => fun::magic_quote($row[0]),
                'answer' => fun::magic_quote($row[1])
            );
            //处理关键字
//            $word = mb_convert_encoding($row[2],"UTF-8", "GBK");
            $word = $row[2];
            $key_str = '';
            if($word){
                $t = explode('/',$word);
//                var_dump($t);die;
                foreach($t as $d){
                    if(isset($keyword[$d])){
                        $key_str .= $keyword[$d] . ',';
                    }else{//没有这个关键字，去新建
                        $val = array(
                            'gid' => $gid,
                            'sid' => $sid,
                            'content' => $d,
                            'uid' => $this->_uid,
                            'etime' => time()
                        );
                        $this->db->set($val)->insert('keyword');
                        $id = $this->db->insert_id();
                        $keyword[$d] = $id;
                        $key_str .= $id . ',';
                    }
                }
            }
            $data['keyword'] = rtrim($key_str,',');
            $this->db->set($data)->insert('mbfaq');
        }
//        die;
        $this->db->trans_complete();//事务提交
        //最后解锁表
        $this->db->query("UNLOCK TABLES");
        //检查状态
        if ($this->db->trans_status() === FALSE){
            return false;
        }
        return true;
    }

    //复制机器人faq数据
    public function copyData($val){
        $w = $this->_getValue($val);
        $fgid = $w['f_gid'];
        $fsid = $w['f_sid'];
        $tgid = $w['t_gid'];
        $tsid = $w['t_sid'];
        //先锁表写
        $this->db->query("LOCK TABLE `bay_mbfaqcate` WRITE");
        //开启事务
        $this->db->trans_start();
        //先删除三个表站点的数据
        $time = time();
        $s1 = "update bay_mbfaq set del = 1,etime = {$time},editor = {$this->_uid} where gid = {$tgid} and sid = {$tsid}";
        $s2 = "update bay_mbfaqcate set del = 1,etime = {$time},editor = {$this->_uid} where gid = {$tgid} and sid = {$tsid}";
//        $s3 = "update bay_keyword set del = 1,etime = {$time},editor = {$this->_uid} where gid = {$tgid} and sid = {$tsid}";
        $this->db->query($s1);
        $this->db->query($s2);
//        $this->db->query($s3);
        //查找出三个数据源
        $sql1 = "select * from bay_keyword WHERE gid = {$fgid} and sid = {$fsid} AND del = 0";
        $sql2 = "select * from bay_mbfaqcate WHERE gid = {$fgid} and sid = {$fsid} AND del = 0";
        $sql3 = "select * from bay_mbfaq WHERE gid = {$fgid} and sid = {$fsid} AND del = 0";
        $where = array(
            'gid' => $tgid,
            'sid' => $tsid,
            'del' => 0
        );
        $rel1 = $this->db->query($s1)->result_array($sql1);
        $rel2 = $this->db->query($s1)->result_array($sql2);
        $rel3 = $this->db->query($s1)->result_array($sql3);
        $k_index = array();
        $c_index = array();
        $q_index = array();


        $rel = $this->db->where($where)->get('keyword')->result_array();


        //关键字
        if($sql1){
            //关键词输处理
            if($rel){
                foreach($rel as $row){
                    $k_index[$row['content']] = $row['id'];
                }
            }
            foreach($rel1 as $row){
                if(!isset($keyword[$row['content']])){//没有这个有关键字，先新加
                    $val = array(
                        'gid' => $tgid,
                        'sid' => $tsid,
                        'content' => $row['content'],
                        'uid' => $this->_uid,
                        'etime' => time()
                    );
                    $this->db->set($val)->insert('keyword');
                    $id = $this->db->insert_id();
                    $k_index[$row['content']] = $id;
                }
            }
        }

        //faq分类
        if($rel2){
            //首先删除原有分类
            $s2 = "update bay_mbfaqcate set del = 1,etime = {$time},editor = {$this->_uid} where gid = {$tgid} and sid = {$tsid}";
            $this->db->query($s2);

            $sql = "select id from bay_mbfaqcate ORDER by id desc limit 1";
            $last_id = $this->db->query($sql)->row_array();
            $last_id = (int) $last_id['id'];//最后一条ID

            foreach($rel2 as $row){
                $sq = "insert into bay_mbfaqcate SET ";
            }

        }








        $w = array('gid' => $gid,'sid' => $sid,'del' => 0);
//        var_dump($data);die;
        //先处理分类
        //这里分类主键id自定义,若果是因为期间其他地方插入了新的分类，导致id冲突，插入分类会失败
        //失败事务回滚在重新执行，直到成功就好了
        $sql = "select id from bay_mbfaqcate ORDER by id desc limit 1";
        $last_id = $this->db->query($sql)->row_array();
        $last_id = (int) $last_id['id'];//最后一条ID
        //分类先自己决定插入的ID，防止锁表等级不同读取不了未提交的数据
        $cate_ref = array();
        foreach($cate as $k => &$c){
            /**
             *  $k =>  0 : id, 1 : fid, 2 : title
             */
            $last_id ++;//id自增
            $c['new_id'] = $last_id;
            $cate_ref[$c[0]] = $last_id;//新旧ID映射
            if($c['1']){
                if(null == $cate_ref[$c['1']]){//防止旧数据导出有问题
                    fun::codeBack("导入的分类资料有误,找不到这个旧的分类的fID值，分类ID为{$c[0]},请跟开发联系！");
                }else{
                    $c['new_fid'] = $cate_ref[$c['1']];
                }
            }else{
                $c['new_fid'] = 0;
            }
        }
        //处理完成分类，开始在数据库中插入分类(逐条插入)
        $time = time();
        $editor = $this->_uid;
//        var_dump($cate_ref);
//        var_dump($cate);
//        var_dump(count($cate));die;
        foreach($cate as $k => $ca){
            $node = null;
            $sql = "insert into bay_mbfaqcate set gid = {$gid},sid = {$sid},id = {$ca['new_id']},title = '" . fun::magic_quote($ca['2']) . "', fid = {$ca['new_fid']},etime = {$time},editor = {$editor}";
//            var_dump($sql);continue;
            $this->db->query($sql);
            $id = $new_id = $ca['new_id'];
            $fid = $ca['new_fid'];
            if($fid){
                $sql2 = "SELECT * FROM `bay_mbfaqcate` WHERE id = '{$fid}'";
                $c_fid = $this->db->query($sql2)->result_array();
                if(!$c_fid) return false;
                $c_fid = $c_fid[0];
                $node = $c_fid['node'];
                if($node){//更新祖先节点子ID
                    $this->_addNode($node,$id);
                }
            }
            //更新新插入的node
            $child_node = (isset($node) && $node) ?  $node . ",{$id}" : $id;
            $sql = "UPDATE `bay_mbfaqcate` set node = '{$child_node}' WHERE id = {$id}";
            $this->db->query($sql);
        }
        //关键词输处理
        $rel = $this->db->where($w)->get('keyword')->result_array();
        $keyword = array();
        if($rel){
            foreach($rel as $row){
                $keyword[$row['content']] = $row['id'];
            }
        }
        foreach($data as $k => $row){
            /**
             *  $k =>  0 : question, 1 : answer, 2 : keyword , 3 : fid
             */
            $sql = '';
            $data = array(
                'gid' => $gid,
                'sid' => $sid,
                'editor' => $this->_uid,
                'cid' => $cate_ref[$row[3]] ? $cate_ref[$row[3]] : 0,//映射到新id
                'etime' => time(),
//                'question' => mb_convert_encoding($row[0],"UTF-8", "GBK"),
//                'answer' => mb_convert_encoding($row[1],"UTF-8", "GBK"),
                'question' => fun::magic_quote($row[0]),
                'answer' => fun::magic_quote($row[1])
            );
            //处理关键字
//            $word = mb_convert_encoding($row[2],"UTF-8", "GBK");
            $word = $row[2];
            $key_str = '';
            if($word){
                $t = explode('/',$word);
//                var_dump($t);die;
                foreach($t as $d){
                    if(isset($keyword[$d])){
                        $key_str .= $keyword[$d] . ',';
                    }else{//没有这个关键字，去新建
                        $val = array(
                            'gid' => $gid,
                            'sid' => $sid,
                            'content' => $d,
                            'uid' => $this->_uid,
                            'etime' => time()
                        );
                        $this->db->set($val)->insert('keyword');
                        $id = $this->db->insert_id();
                        $keyword[$d] = $id;
                        $key_str .= $id . ',';
                    }
                }
            }
            $data['keyword'] = rtrim($key_str,',');
            $this->db->set($data)->insert('mbfaq');
        }
//        die;
        $this->db->trans_complete();//事务提交
        //最后解锁表
        $this->db->query("UNLOCK TABLES");
        //检查状态
        if ($this->db->trans_status() === FALSE){
            return false;
        }
        return true;
    }
}


