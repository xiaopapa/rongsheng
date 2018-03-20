<?php

/**
 * 知识库model
 * Class Robot_model
 * @author harvey
 * @Date: 17-8-16
 * @license  http://www.boyaa.com/
 */
class Kms_model extends Bmax_Model {
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->m = new mdb('bay_',$this->db);//DB助手
    }

    /**
     * 新加关键字
     * @param $gid
     * @param $kw
     * @param string $uid
     * @return mixed
     * @date
     */
    public function addKeyword($gid,$kw,$uid = ''){
        $w = array(
            'gid' => $gid,
            'keyword' => $kw
        );
        $rel = $this->db->where($w)->get('kms_keyword')->row_array();
        $data = array(
            'gid' => $gid,
            'keyword' => $kw,
            'etime' => time(),
            'editor' => $uid ? $uid : $this->_uid
        );
        if($rel){
            if($rel['del'] == 0){//已经有，提示不要重复添加
                $ret = "{$kw}这个关键字已经存在，请不要重复添加~";
            }else{//旧的已有，恢复
                $id = $rel['id'];
                $data['del'] = 0;
                $ret = $this->db->set($data)->where('id',$id)->update('kms_keyword');
            }

        }else{//直接新增
            $ret = $this->db->set($data)->insert('kms_keyword');
        }
        return $ret;
    }

    /**
     * 删除一个关键字
     * @param $id
     * @return mixed
     * @date
     */
    public function delKeyword($id){
        $ret = $this->db->where('id',$id)->set('del',1)->update('kms_keyword');
        return $ret;
    }

    /**
     * 获取关键字
     * @param $gid
     * @return mixed
     * @date
     */
    public function getKeyword($gid){
        $w = array(
            'gid' => $gid,
            'del' => 0
        );
        $ret = $this->db->where($w)->get('kms_keyword')->result_array();
        return $ret;
    }

    /**
     * 拉取分类
     * @param array $field
     * @param array $val
     * @param int $type               0:不去重，1：去重
     * @return mixed
     * @date
     */
    public function getCate($field = array(),$val = array(),$type = 0){
        $w = $this->_getValue($field,$val);
        $data['del'] = 0;
        if(isset($w['gid'])){
            $data['gid'] = $w['gid'];
        }
        if(isset($w['sid'])){
            $data['sid'] = $w['sid'];
            $type = 0;//细到站点，就有ID了
        }
        $ret = $this->db->where($data)->get('kmscate')->result_array();
        if($type && $ret){
            $rel = array();
            foreach($ret as $row){
                if(!in_array($row['title'],$rel)) $rel[] = $row['title'];
            }
        }
        return $ret;
    }


    /**
     *新加,修改，删除一个分类
     * @param $field               array
     * @param $val                 array
     * @return bool|string
     * @date
     */
    public function setCate($field = array(),$val = array()){
        $w = $this->_getValue($field,$val);
        $data = array(
            'etime' => time(),
            'editor' => $this->_uid,
        );
        $new_id = '';
//        var_dump(1);die;
        $this->db->trans_start();//事务开启
        if(!$w['id']){//新增,事务提交
            $data['gid'] = $w['gid'];
            $data['sid'] = $w['sid'];
            $data['title'] = $w['title'];
            $fid = $data['fid'] = $w['fid'];
            $this->db->set($data)->insert('kmscate');
            $new_id = $id = $this->db->insert_id();
            //如果fid不为0
            $node = '';
            if($fid){
                $sql = "SELECT * FROM `bay_kmscate` WHERE id = '{$fid}'";
                $rel = $this->db->query($sql)->row_array();
                if(!$rel) return false;
                $node = $rel['node'];
                if($node){//更新祖先节点子ID
                    $this->_addNode($node,$id);
                }
            }
            //更新新插入的node
            $child_node = fun::appendSome($node,$id);
            $sql = "UPDATE `bay_kmscate` set node = '{$child_node}' WHERE id = {$id}";
            $this->db->query($sql);
        }else{//更新或删除
            $id = $w['id'];
            if(isset($w['del']) && $w['del'] == 1){//删除操作,事务
                $data['del'] = 1;
                $this->db->set($data)->where('id',$id)->update('kmscate');
                //删除该ID下面所有子ID
                $rel = $this->db->where('id',$id)->get('kmscate')->row_array();
                if($rel){
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
            }else{//更新
                $data['title'] = $w['title'];
                $this->db->set($data)->where('id',$id)->update('kmscate');
            }
        }
        $this->db->trans_complete();//事务提交
        if ($this->db->trans_status() === FALSE){//失败
            return "操作失败，请重试";
        }else{//成功
            if($new_id){
                return $new_id;
            }else{
                return true;
            }

        }
    }

    /**
     *新加,修改，删除一个分类
     * @param $field               array
     * @param $val                 array
     * @return bool|string
     * @date
     */
    public function setKms($field,$val){
        $w = $this->_getValue($field,$val);
        $data = array(
            'etime' => time(),
            'editor' => $this->_uid,
        );
        if(!$w['id']){//新增
            $data['gid'] = $w['gid'];
            $data['sid'] = $w['sid'];
            $data['title'] = $w['title'];
            $data['cid'] = $w['cid'];
            $data['content'] = stripslashes($w['content']);
            if(isset($w['keyword'])) $data['keyword'] = $w['keyword'];
            if(isset($w['collector'])) $data['collector'] = $w['collector'];
            if(isset($w['sort'])) $data['sort'] =  $w['sort'];
            if(isset($w['hide'])) $data['hide'] =  $w['hide'];
            if(isset($w['file'])) $data['file'] =  stripslashes($w['file']);
            $ret = $this->db->set($data)->insert('kms');
        }else{//更新或删除
            $id =  $w['id'];
            if(isset($w['del'])){//删除操作,事务
                $data['del'] = $w['del'];
                $ret = $this->db->set($data)->where('id',$id)->update('kms');
            }else{//更新
                $data['title'] = $w['title'];
                $data['cid'] = $w['cid'];
                $data['content'] = stripslashes($w['content']);
                if(isset($w['keyword'])) $data['keyword'] = $w['keyword'];
                if(isset($w['collector'])) $data['collector'] = $w['collector'];
                if(isset($w['sort'])) $data['sort'] = $w['sort'];
                if(isset($w['hide'])) $data['hide'] =  $w['hide'];
                if(isset($w['file'])) $data['file'] =  stripslashes($w['file']);
                $ret = $this->db->set($data)->where('id',$id)->update('kms');
            }
        }
        return $ret;
    }


    /**
     * 知识库列表
     * @param array $val
     * @param array $field
     * @return array
     * @date
     */
    public function getKmsList($val = array(),$field = array()){
        $w = $this->_getValue($field,$val);
        $where = '';
        if($w['gid']){
            $where .= " and gid = {$w['gid']}";
            if($w['sid']){
                $where .= " and sid = {$w['sid']}";
            }
        }
        //获取分类
        $c_sql = "select * from bay_kmscate WHERE 1 {$where}";
        $crel = $this->db->query($c_sql)->result_array();
        $cids = array();
        if($crel){
            foreach($crel as $row){
                $cids[$row['id']] = $row['title'];
            }
        }

        if($w['cid']){
            $where .= " and cid = {$w['cid']}";
        }
        if($w['key']){
            $key = trim($w['key']);
        }
        if(isset($w['del'])){
            $where .= " and del = {$w['del']}";
        }
        $sql = "select id from bay_kms_keyword WHERE keyword = '{$key}'";
        $rel = $this->db->query($sql)->row_array();
        if($rel && $key){//关键字ID
            $key_id = $rel['id'];
            $where .= " and (find_in_set({$key_id},keyword) OR title like '%{$key}%')";
        }elseif($key){
            $where .= " and title like '%{$key}%'";
        }

        $_sql = "select count(*) as total from  bay_kms  WHERE  1 {$where}";
        $_rel = $this->db->query($_sql)->row_array();
        $num = $_rel['total'];

        if($w['sort']){//保留
            $where .= " order by id desc";
        }else{
            $where .= " order by id desc";
        }

        $limit = $this->getLimit('','','',1);
        $where .= $limit;

        $sql = "select * from bay_kms WHERE 1 {$where}";
        $ret = $this->db->query($sql)->result_array();
        //最后处理一下数据格式
        if($ret){
            foreach($ret as &$row){
                $row['cname'] = implode(',',fun::reflect($row['cid'],$cids));
            }
        }
        return  array($ret,$num);
    }


    /**
     * 获取一个菜单列表
     * @param array $val
     * @param array $field
     * @return array
     * @date
     */
    public function getKms($val = array(),$field = array()){
        $w = $this->_getValue($field,$val);
        $ret = array();
        $where = '';
        $c_where = '';
        $my_str = '';
        $key_arr = array();
        $all = array();
        $del = '';
        $kindex = array();//关键字权重
        $tindex = array();//标题权重
        $cindex = array();//内容权重
        $oindex = array();//普通内容权重
        if($w['gid']){
            $c_where .= " and gid = {$w['gid']}";
            $where .= " and gid = {$w['gid']}";
            if($w['sid']){
                $c_where .= " and sid = {$w['sid']}";
                $where .= " and sid = {$w['sid']}";
            }
        }
        if($w['cid']){
            $where .= " and cid = {$w['cid']}";
        }elseif(isset($w['cname'])){
            $csql = "select * from bay_kmscate WHERE 1 $c_where AND title = '{$w['cname']}' AND del = 0";
            $crel = $this->db->query($csql)->result_array();
            if($crel){
                $cid_arr = array();
                foreach($crel as $c){
                    $cid_arr[] = $c['id'];
                }
                $where .= " and cid in(" . implode(',',$cid_arr) . ')';
            }
        }
        if($w['key']){
            $tmp = explode(' ',trim($w['key']));
            foreach($tmp as $k => $a){
                if(!$a) unset($tmp[$k]);
            }
            $key_arr = $tmp;
        }
        if($w['my']){//我的收藏
            $my_str = " and find_in_set({$this->_uid},collector)";
        }
        if(isset($w['del'])){
            $where .= " and del = {$w['del']}";
        }else{
            $where .= " and del = 0";
        }
        if(isset($w['hide'])){
            $where .= " and hide = {$w['del']}";
        }else{
            $where .= " and hide = 0";
        }
        if(isset($w['order'])){
            $order = "order by etime desc";
        }else{
            $order = "order by etime desc";
        }

        $str = implode("','",$key_arr);
        $sql = "select id from bay_kms_keyword WHERE keyword in ('{$str}')";
        $rel = $this->db->query($sql)->result_array();
        //获取分类
        $c_sql = "select * from bay_kmscate WHERE 1 {$c_where}";
        $crel = $this->db->query($c_sql)->result_array();
        $cids = array();
        if($crel){
            foreach($crel as $row){
                $cids[$row['id']] = $row['title'];
            }
        }
        $kid = array();
        if($rel){
            foreach($rel as $r){
                $kid[] = $r['id'];
            }
        }
        $new = array();
        if($kid){
            foreach($kid as $k => $id){
                $sql = "select * from bay_kms WHERE 1 {$where} {$my_str} AND find_in_set({$id},keyword) {$del} {$order} ";
                $rel = $this->db->query($sql)->result_array();
//                    $kdata[$k] = $rel ? $rel : array();
                if($rel){
                    foreach($rel as $r){
                        $_id = $r['id'];
//                            if(!isset($kdata[$_id])) $kdata[$_id] = $r;
                        if(!isset($all[$_id])) $all[$_id] = $r;
                        if(isset($kindex[$_id])){
                            $kindex[$_id] = $kindex[$_id] + 1;
                        }else{
                            $kindex[$_id] = 1;
                        }
                    }
                }
            }
        }
        if($key_arr){
            foreach($key_arr as $key){
                //标题搜索
                $sql = "select * from bay_kms WHERE 1 {$where} {$my_str} AND title like '%{$key}%' {$del} {$order}";
                $rel = $this->db->query($sql)->result_array();
                if($rel){
                    foreach($rel as $kr){
                        if(!isset($all[$kr['id']])) $all[$kr['id']] = $kr;
                        if(isset($index[$kr['id']])){//标题权重
                            $tindex[$kr['id']] = $tindex[$kr['id']] + 1;
                        }else{
                            $tindex[$kr['id']] = 1;
                        }
                    }
                }

                //内容搜索
                $sql = "select * from bay_kms WHERE 1 {$where} {$my_str} AND content like '%{$key}%' {$del} {$order}";
                $rel = $this->db->query($sql)->result_array();
                if($rel){
                    foreach($rel as $row){//内容权重
                        if(!isset($all[$row['id']])) $all[$row['id']] = $row;
                        if(isset($index[$row['id']])){
                            $cindex[$row['id']] = $cindex[$row['id']] + 1;
                        }else{
                            $cindex[$row['id']] = 1;
                        }
                    }
                }
            }
        }
        //最后的搜索(游戏、站点、分类条件)
        if($where){
            $sql = "select * from bay_kms WHERE 1 {$where} {$my_str} {$del} {$order}";
            $rel = $this->db->query($sql)->result_array();
//                var_dump($sql);
//                var_dump($rel);die;
            if($rel){
                foreach($rel as $row){//内容权重
                    if(!isset($all[$row['id']])){
                        $all[$row['id']] = $row;
                        $oindex[$row['id']] = 1;//没有的，最后才装上
                    }
                }
            }
        }


        //最后处理并排序数据
        if($all){
            $st = time() - 3600 * 24;
            //最新的集中在这里处理
            foreach($all as &$n){
                if($n['etime'] >= $st){
                    $n['new'] = 1;
                }else{
                    $n['new'] = 0;
                }
            }

            $data = array();
            $top = array();
            $new = array();
            //关键字匹配权重
            if($kindex){
                asort($kindex);
                foreach($kindex as $k => $r){
                    if(!$my_str){//单纯搜索页处理
                        if($all[$k]['sort'] > 0){
                            if(!isset($top[$k])) $top[$k] = $all[$k];
                        }elseif($all[$k]['new'] = 1){
                            if(!isset($new[$k])) $new[$k] = $all[$k];
                        }else{
                            if(!isset($data[$k])) $data[$k] = $all[$k];
                        }
                    }else{//我的搜藏
                        if(!isset($data[$k])) $data[$k] = $all[$k];
                    }
                }
            }
            //标题匹配权重
            if($tindex){
                asort($tindex);
                foreach($tindex as $k => $r){
                    if(!$my_str){//单纯搜索页处理
                        if($all[$k]['sort'] > 0){
                            if(!isset($top[$k])) $top[$k] = $all[$k];
                        }elseif($all[$k]['new'] = 1){
                            if(!isset($new[$k])) $new[$k] = $all[$k];
                        }else{
                            if(!isset($data[$k])) $data[$k] = $all[$k];
                        }
                    }else{//我的搜藏
                        if(!isset($data[$k])) $data[$k] = $all[$k];
                    }
                }
            }
            //内容匹配权重
            if($cindex){
                asort($cindex);
                foreach($cindex as $k => $r){
                    if(!$my_str){//单纯搜索页处理
                        if($all[$k]['sort'] > 0){
                            if(!isset($top[$k])) $top[$k] = $all[$k];
                        }elseif($all[$k]['new'] = 1){
                            if(!isset($new[$k])) $new[$k] = $all[$k];
                        }else{
                            if(!isset($data[$k])) $data[$k] = $all[$k];
                        }
                    }else{//我的搜藏
                        if(!isset($data[$k])) $data[$k] = $all[$k];
                    }
                }
            }
            //普通匹配处理
            if($oindex){
                foreach($oindex as $k => $r){
                    if(!$my_str){//单纯搜索页处理
                        if($all[$k]['sort'] > 0){
                            if(!isset($top[$k])) $top[$k] = $all[$k];
                        }elseif($all[$k]['new'] = 1){
                            if(!isset($new[$k])) $new[$k] = $all[$k];
                        }else{
                            if(!isset($data[$k])) $data[$k] = $all[$k];
                        }
                    }else{//我的搜藏
                        if(!isset($data[$k])) $data[$k] = $all[$k];
                    }
                }
            }
            if(!$my_str){
                $ret = array_merge($top,$new,$data);
            }else{
                $ret = $data;
            }

            //最后处理一下数据格式
            if($ret){
                foreach($ret as &$row){
                    $row['cname'] = implode(',',fun::reflect($row['cid'],$cids));
                }
            }
        }
        return  array_values($ret);
    }

    /**
     * 收藏知识库
     * @param $val
     * @return string
     * @date
     */
    public function collectKms($val){
        $rel = $this->db->where('id',$val['id'])->get('kms')->row_array();
        if($rel){
            $cl = $rel['collector'];
            if($val['type'] == 1){//收藏
                $new = fun::appendSome($cl,$this->_uid);
            }else{//取消收藏
                $new = fun::delSome($cl,$this->_uid);
            }
            $ret = $this->db->set('collector',$new)->where('id',$val['id'])->update('kms');
        }else{
            $ret = "没有这个ID的数据！";
        }
        return $ret;
    }

    /**
     * 获取每个站点下面的收藏数目
     * @return mixed
     * @date
     */
    public function getCollectNum(){
        $sql = "select * from bay_kms WHERE find_in_set({$this->_uid},collector)  AND del = 0";
        $rel = $this->db->query($sql)->result_array();
        $count = array();
        if($rel){
            foreach($rel as $row){
                 $count[$row['gid']][$row['sid']] = isset($count[$row['gid']][$row['sid']]) ? $count[$row['gid']][$row['sid']] + 1 : 1;
            }
        }
        return $count;
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
            $sql = "SELECT * FROM `bay_kmscate` WHERE id = {$row}";
            $rel = $this->db->query($sql)->row_array();
            if(isset($rel['child_id'])){
                $new_str = fun::appendSome($rel['child_id'],$cid);
                $sql = "UPDATE  `bay_kmscate` SET child_id = '{$new_str}' WHERE id = {$row}";
                $this->db->query($sql);//更新子节点
            }else{//警报？
                fun::log("菜单节点操作失败",'kmscate');
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
        foreach($fid as $key => $id){
            $sql = "SELECT * FROM `bay_kmscate` WHERE id = {$id}";
            $rel = $this->db->query($sql)->row_array();
            if(isset($rel['child_id']) && $rel['child_id']){
                $new_str = fun::delSome($rel['child_id'],$child_id);
                $sql = "UPDATE  `bay_kmscate` SET child_id = '{$new_str}' WHERE id = {$id}";
                $this->db->query($sql);//更新子节点
            }else{//警报？
                fun::log("菜单节点操作失败",'kmscate');
            }
        }
    }

    /**
     * 删除子ID
     * @param $child_id
     * @date
     */
    private function _delChild($child_id){
        $sql = "UPDATE `bay_kmscate` set del = 1 WHERE id IN ({$child_id})";
        $this->db->query($sql);
    }

}