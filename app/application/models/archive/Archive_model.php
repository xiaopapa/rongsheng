<?php

/**
 * 归档
 * Class Archive_model
 * @author harvey
 * @Date: ${DAY}
 * @license  http://www.boyaa.com/
 */
class Archive_model extends Bmax_Model {
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
     * 获取归档项列表
     * del 0:获取未删除的，1：获取删除的，999：获取全部 默认取未删除的
     * @param string $field
     * @param array $val
     * @date
     */
    public function getArchive($field = '',$val=  array()){
        $w = $this->_getValue($field,$val);
        if(isset($w['del']) && $w['del'] != 999){
            if(strpos($w['del'],',') !== false){
                $this->db->where_in('del',explode(',',$w['del']));
            }else{
                $this->db->where('del',$w['del']);
            }
        }elseif($w['del'] != 999){
            $this->db->where('del',0);
        }
        if(isset($w['gid'])){
            $this->db->where('gid',$w['gid']);
        }
        if(isset($w['sid'])){
            $this->db->where('sid',$w['sid']);
        }
        if(isset($del['id'])){
            if(strpos($w['id'],',') !== false){
                $this->db->where_in('id',explode(',',$w['id']));
            }else{
                $this->db->where('id',$w['id']);
            }
        }
        $ret = $this->db->get('archive')->result_array();
        return $ret;
    }

    /**
     * 新增或者更新/删除（del设为1）一条分类
     * @param array $field
     * @param array $val
     * @return bool|string
     * @date
     */
    public function setArchive($field = array(),$val = array()){
        $w = $this->_getValue($field,$val,$this->db);
        $time = time();
        $this->db->set('etime',$time);
        $this->db->set('editor',$this->_uid);
        if(isset($val['id'])){//编辑
            $id = $val['id'];
            $del = $val['del'];
            if($del == 1){//删除操作,事务
                $this->db->trans_start();//事务开启
                $sql = "update `bay_archive` set del = 1 WHERE id ={$id}";
                $this->db->query($sql);
                //删除改ID下面所有子ID
                $sql = "SELECT * FROM `bay_archive` WHERE id = {$id}";
                $rel = $this->db->query($sql)->row_array();
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
                $this->db->trans_complete();//事务提交
                if ($this->db->trans_status() === FALSE){//失败
                    return "保存失败，请重试";
                }else{//成功
                    return true;
                }
            }else{//更新
                $ret = $this->db->where('id',$id)->update('archive');
                return $ret;
            }
        }else{//新增
            $this->db->trans_start();//事务开启
            $this->db->insert('archive');
            $id = $this->db->insert_id();
            $fid = $w['fid'];
            //如果fid不为0
            if($fid){
                $sql = "SELECT * FROM `bay_archive` WHERE id = '{$fid}'";
                $data = $this->db->query($sql)->row_array();
                if(!$data) return false;
                $node = $data['node'];
                if($node){//更新祖先节点子ID
                    $this->_addNode($node,$id);
                }
            }
            //更新新插入的node
            $child_node = (isset($node) && $node) ?  $node . ",{$id}" : $id;
            $sql = "UPDATE `bay_archive` set node = '{$child_node}' WHERE id = {$id}";
            $this->db->query($sql);
            $this->db->trans_complete();//事务提交
            if ($this->db->trans_status() === FALSE){//失败
                return "保存失败，请重试";
            }else{//成功
                return $id;
            }
        }
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
            $sql = "SELECT * FROM `bay_archive` WHERE id = {$row}";
            $rel = $this->db->query($sql)->result_array();
            if(isset($rel[0]['child_id'])){
                $temp = $rel[0]['child_id'];
                $new_str = $temp ? $temp . ",{$cid}" : $cid;
                $sql = "UPDATE  `bay_archive` SET child_id = '{$new_str}' WHERE id = {$row}";
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
            $sql = "SELECT * FROM `bay_archive` WHERE id = {$row}";
            $rel = $this->db->query($sql)->row_array();
            if(isset($rel['child_id']) && $rel['child_id']){
                $temp = $rel['child_id'];
                $temp = explode(',',$temp);
                $new = array_diff($temp,$child_id);
                sort($new);
                $new_str = implode(',',$new);
                $sql = "UPDATE  `bay_archive` SET child_id = '{$new_str}' WHERE id = {$row}";
                $this->db->query($sql);//更新子节点
            }else{//写错误日志
                fun::log("修改父节点fid={$fid}的时候，找不到这个id下面有子ID记录",'archive');
            }
        }
    }

    /**
     * 删除子ID
     * @param $child_id
     * @date
     */
    private function _delChild($child_id){
        $sql = "UPDATE `bay_archive` set del = 1 WHERE id IN ({$child_id})";
        $this->db->query($sql);
    }


    /**
     * 获取归档项下面的快捷回复
     * @param $aid
     * @param string $lang
     * @return bool
     * @date
     */
    public function getAdviseFast($aid,$lang = '',$fuid = 0){
        if(!$aid) return false;
        $w = array('aid' => $aid);
        if($lang) $w['lang'] = $lang;
        if(!$fuid){
            $this->db->where_in('fuid',array(0,$this->_uid));
        }elseif($fuid != '-1'){
            $this->db->where_in('fuid',array(0, (int) $fuid));
        }
        $ret = $this->db->where($w)->get('advise_fast')->result_array();
        if($ret){
            $user_data = fun::getUserData();
            foreach($ret as &$row){
                $row['name'] = $user_data[$row['fuid']]['name'];
                $row['ename'] = $user_data[$row['editor']]['name'];
            }
        }
        return $ret;
    }


    /**
     * 新增或者更新/删除（del设为1）一条快捷回复
     * @param array $field
     * @param array $val
     * @return bool|string
     * @date
     */
    public function setAdviseFast($field = array(),$val = array()){
        $w = $this->_getValue($field,$val,$this->db);
        $time = time();
        $this->db->set('etime',$time);
        $this->db->set('editor',$this->_uid);
        if(isset($val['id'])){//编辑
            $id = $val['id'];
            $del = $val['del'];
            if(isset($del)){//删除/恢复 操作
                $ret = $this->db->set('del',$del)->where('id',$id)->update('advise_fast');
            }else{//更新
                $ret = $this->db->where('id',$id)->update('advise_fast');
            }
        }else{//新增
            $ret = $this->db->insert('advise_fast');
        }
        return $ret;
    }

    /**
     * 检查归档项
     * @param $gid
     * @param $to_sid
     * @return string
     * @date
     */
    public function checkCopyArchive($gid,$to_sid){
        $ret = '';
        foreach($to_sid as $row){
            $sql = "select sid from bay_archive WHERE gid = {$gid} and sid = {$row}";
            $rel = $this->db->query($sql)->row_array();
            if($rel){
                $ret .= $row . ',';
            }
        }
        return rtrim($ret,',');
    }

    /**
     * 复制归档项,复制前已经做已有归档项查询
     * @param $gid
     * @param $sid
     * @param $to_sid
     * @return bool
     * @date
     */
    public function copyArchive($gid,$sid,$to_sid,$to_gid = 0){
        if(!$gid || !$sid || !$to_sid || !is_array($to_sid)) return false;
        if(!$to_gid) $to_gid = $gid;
        //先把源归档项查出来
        $sql = "select * from bay_archive WHERE gid = {$gid} and sid = {$sid} AND del = 0";
        $source = $this->db->query($sql)->result_array();
//        var_dump($sql);
//        var_dump($source);die;
        if($source){
            //查找出表里面最后一条记录的ID，锁表然后进行操作
            $sql = "select id from bay_archive ORDER BY id DESC limit 1";
            $last_id = $this->db->query($sql)->row_array();
            $last_id = (int) $last_id['id'];
//            var_dump($last_id);die;
            //给表加写锁
            $this->db->query("LOCK TABLE `bay_archive` WRITE");
            //处理数据
            $sql = array();
            $time = time();
            foreach($to_sid as $nsid){
                $tmp = $source;//复制一份
                $ref = array();
                //删除原有的
                $sql[] = "update bay_archive set del = 1 WHERE gid = {$to_gid} AND sid = {$nsid}";
                foreach($tmp as &$rows){//建立新ID
                    $rows['nid'] = ++$last_id;
                    $ref[$rows['id']] = $rows['nid'];
                }
//                var_dump($tmp);die;
//                echo '----------------';
//                reset($tmp);
//                var_dump($tmp);die;
                //在循环处理，将旧ID都替换成新ID
                foreach($tmp as $key => $row){
//                    var_dump($key);
//                    var_dump($row['title']);
                    //处理fid
                    if($row['fid']){
                        $n_fid = $ref[$row['fid']];
                    }else{
                        $n_fid = 0;
                    }
                    //处理node
                    $n_tmp = explode(',',$row['node']);
                    $n_node = array();
                    foreach($n_tmp as $r){
                        $n_node[] = $ref[$r];
                    }
                    $n_node = implode(',',$n_node);
                    //处理child_id
                    $c_tmp = explode(',',$row['child_id']);
                    $n_child = array();
                    foreach($c_tmp as $r){
                        $n_child[] = $ref[$r];
                    }
                    $n_child = implode(',',$n_child);
                    $sql[] = "insert into `bay_archive` set id = {$row['nid']},gid = {$to_gid},sid = {$nsid},title = '{$row['title']}',title_tl = '{$row['title_tl']}',title_en = '{$row['title_en']}',fid = '{$n_fid}',child_id = '{$n_child}',node = '{$n_node}',etime = '{$time}',editor = '{$this->_uid}'";
                }
            }
            //事务插入目标站点
            $this->db->trans_start();
            foreach($sql as $r){
                $rel = $this->db->query($r);
            }
            $this->db->trans_complete();//事务提交
            //最后解锁表
            $this->db->query("UNLOCK TABLES");
            //检查状态
            if ($this->db->trans_status() === FALSE){
                return false;
            }
            return true;
        }else{//源站点没数据
            return "源站点没归档项数据";
        }

    }

    /**
     * 会话归档统计
     * @param array $val
     * @param array $field
     * @return array
     * @date
     */
    public function online($val = array(),$field = array()){

        //测试语句
        /*$arr  = array(
            51,
            113,
            114,
            115,
            116,
            117,
            119,
            140,
            141
        );
        for($i = 178838;$i < 182968;$i++){
            $n = rand(0,8);
            $sql = "update boyimdb.tmp_chat_session set archive_category = {$arr[$n]} WHERE id = $i AND site_id = 117";
            $this->db->query($sql);
        }
        echo 'done';*/


        $odb = $this->load->database('online',true);
        $w = $this->_getValue($field,$val);
        $where = "gid = {$w['gid']} and site_id in ({$w['sid']}) and session_type = 0 and del = 0";
        if(isset($w['type'])){
            $where .= " and archive_class ={$w['type']}";
        }
        $sql1 = "select * from tmp_chat_session WHERE {$where} and clock >= {$w['ast']} and clock < {$w['aed']}";
        $sql2 = "select * from tmp_chat_session WHERE {$where} and clock >= {$w['bst']} and clock < {$w['bed']}";
        //处理归档
        $sql = "select * from bay_archive  WHERE gid = {$w['gid']} and sid = {$w['sid']} and del = 0";
        $rel = $this->db->query($sql)->result_array();
        $aindx = array();
        $archive = array();
        $rindex = array();
        if($rel){
            foreach($rel as $row){
                $aindx[$row['id']] = $row['node'];
                $archive[$row['id']] = $row;
                $key = substr_count($row['node'],',');
                $rindex[$row['id']] = $key;
            }
        }
        /*echo '<pre/>';
        var_dump($rindex);die;*/
        $rel1 = $odb->query($sql1)->result_array();
        $rel2 = $odb->query($sql2)->result_array();
        $ret = array();
        $fdata = array();
        $fnum = 0;
        $snum = 0;
        $sdata = array();
        $data_key = array();
        if($rel1){
            $data_key['fdata'] = 'rel1';
            $fnum = count($rel1);
        }
        if($rel2){
            $data_key['sdata'] = 'rel2';
            $snum = count($rel2);;
        }
        //数据统计
        foreach($data_key as $s => $d){
            $data = $$d;
            $store =  &$$s;
            foreach($data as $row){
                $order_flag = false;
//                var_dump($row['archive_category']);die;
                if(isset($aindx[$row['archive_category']])){
                    $store[$row['archive_category']]['chat'] = isset($store[$row['archive_category']]['chat']) ?  $store[$row['archive_category']]['chat'] + 1 : 1;
                    if($row['upgraded']){
                        $order_flag = true;
                        $store[$row['archive_category']]['order'] = isset($store[$row['archive_category']]['order']) ?  $store[$row['archive_category']]['order'] + 1 : 1;
                    }
                    $tmp = explode(',',$aindx[$row['archive_category']]);
                    array_pop($tmp);
//                    $child = array($row['archive_category']);
                    if($tmp){
                        foreach($tmp as $r){//循环为祖先节点添加数据
                            $store[$r]['chat'] = isset($store[$r]['chat']) ?  $store[$r]['chat'] + 1 : 1;
                            /*foreach($child as $c){
                                if(!isset($store[$r]['child']) || !in_array($c,$store[$r]['child'])) $store[$r]['child'][] = $c;
                            }
                            $child[] = $r;*/
                            if($order_flag){
                                $store[$r]['order'] = isset($store[$r]['order']) ?  $store[$r]['order'] + 1 : 1;
                            }
                        }
                    }
                }else{//写日志记录归档错误？

                }
            }
        }
        //处理数据
        if($fdata){
            foreach($fdata as $key => &$row){
                if($row['chat']){
                    $row['rate'] = round(($row['chat'] / $fnum * 100),2);
                }else{
                    $row['rate'] = 0;
                }
                if(!isset($row['order'])){//补全工单
                    $row['order'] = 0;
                }
                if(isset($archive[$key])){
                    $row['fid'] = $archive[$key]['fid'];
                    $row['child_id'] = $archive[$key]['child_id'];
                    $row['node'] = $archive[$key]['node'];
                    $row['lv'] = $rindex[$key];
                    $row['id'] = $key;
                    $row['name'] = $archive[$key]['title'];
                }
                if(!isset($sdata[$key])){
                    $sdata[$key] = array(
                        'order' => 0,
                        'chat' => 0,
                        'rate' => 0
                    );
                    if(isset($archive[$key])){
                        $sdata[$key]['fid'] = $archive[$key]['fid'];
                        $sdata[$key]['child_id'] = $archive[$key]['child_id'];
                        $sdata[$key]['node'] = $archive[$key]['node'];
                        $sdata[$key]['lv'] = $rindex[$key];
                        $sdata[$key]['name'] = $archive[$key]['title'];
                    }
                }
            }
        }
        if($sdata){//第二次的要补缺第一次的数据(没有的归档加上)
            foreach($sdata as $key => &$row){
                if($row['chat']){
                    $row['rate'] = round(($row['chat'] / $snum * 100),2);
                }else{
                    $row['rate'] = 0;
                }
                if(!isset($row['order'])){//补全工单
                    $row['order'] = 0;
                }
                if(isset($archive[$key])){
                    $row['fid'] = $archive[$key]['fid'];
                    $row['child_id'] = $archive[$key]['child_id'];
                    $row['node'] = $archive[$key]['node'];
                    $row['lv'] = $rindex[$key];
                    $row['id'] = $key;
                    $row['name'] = $archive[$key]['title'];
                }

//                $ret['second'][$rindex[$key]][$key] = $row;

                if(!isset($fdata[$key])){
                    $fdata[$key] = array(
                        //                        //                        'child' => isset($row['child']) ? $row['child'] : array(),  
                        'order' => 0,
                        'chat' => 0,
                        'rate' => 0
                    );
                    if(isset($archive[$key])){
                        $fdata[$key]['fid'] = $archive[$key]['fid'];
                        $fdata[$key]['child_id'] = $archive[$key]['child_id'];
                        $fdata[$key]['node'] = $archive[$key]['node'];
                        $fdata[$key]['lv'] = $rindex[$key];
                        $fdata[$key]['id'] = $key;
                        $fdata[$key]['name'] = $archive[$key]['title'];
                    }
                }
            }
        }

        $ret['first'] = array_values($fdata);
        $ret['second'] = array_values($sdata);
        return $ret;
    }

    /**
     * 获取对话内容
     * @param $val
     * @param $field
     * @param int $type
     * @return array
     * @date
     */
    public function getChat($val,$field = array(),$type =  0){
        $odb = $this->load->database('online',true);
        $w = $this->_getValue($field,$val);
        $where = '1';
        if(isset($w['aid'])){
            //获取aid的子
            $asql = "select * from bay_archive WHERE id in ({$w['aid']})";
            $rel = $this->db->query($asql)->result_array();
            $tmp = explode(',',$w['aid']);
            if($rel){
                foreach($rel as $row){
                    if($row['child_id']){
                        $t = explode(',',$row['child_id']);
                        foreach($t as $r){
                            if(!in_array($row['id'],$tmp)){
                                $tmp[] = $row['id'];
                            }
                        }
                    }
                }
            }
            $aid = implode(',',$tmp);
            $where .= " and session_type = {$type} and del = 0 and archive_category in ({$aid})";
        }else{
            $str = '';
            if(isset($w['gid'])){
                $str .= " and gid = {$w['gid']}";
            }
            if(isset($w['sid'])){
                $str .= " and sid in ({$w['gid']})";
            }
            $asql = "select * from bay_archive WHERE 1 {$str} AND title = '{$w['name']}'";
//        var_dump($asql);die;
            $ids = '';
            $arel = $this->db->query($asql)->result_array();
            if($arel){
                foreach($arel as $row){
                    $ids .= $row['id'] . ',';
                }
            }
            $ids = rtrim($ids,',');
            if($ids){
                $where .= " and archive_category in ({$ids})";
            }
            $where .= " and session_type = {$type} and del = 0";
        }

        if(isset($w['gid'])){
            $where .= " and gid = {$w['gid']}";
            if(isset($w['sid'])){
                $where .= " and site_id in ({$w['sid']})";
            }
        }
        if(isset($w['type'])){
            $where .= " and archive_class = {$w['type']}";
        }
        $limit  = $this->getLimit('','','',1);

        $sql = "select * from tmp_chat_session WHERE {$where}  ORDER BY id DESC {$limit}";
        $_sql = "select count(*) as total from t_chat_session WHERE {$where}";
        $rel = $odb->query($sql)->result_array();
        $num = 0;
//        var_dump($sql);
//        var_dump($rel);die;
        if($rel){
            $_rel = $odb->query($_sql)->row_array();
            $num = $_rel['total'];
            $session_id = array();
            $sdata = array();
            foreach($rel as $row){
                $session_id[] = $row['session_id'];
                $sdata[$row['session_id']] = $row;
            }
            $str = implode(',',$session_id);
            $sql1 = "select * from tmp_chat_content WHERE session_id in ($str) ORDER BY id ASC";
            $rel1 = $odb->query($sql1)->result_array();
            if($rel1){
                foreach($rel1 as $row){
                    $sdata[$row['session_id']]['content'][] = $row;
                }
            }
        }
        return array(array_values($sdata),$num);
    }

    /**
     * 根据条件获取工单(要等新后台工单做好)
     * @param $val
     * @param $field
     * @return array
     * @date
     */
    public function getOnlineOrder($val,$field){
        $odb = $this->load->database('online',true);
        $w = $this->_getValue($field,$val);
        $asql = "select * from bay_archive WHERE id in ({$w['aid']})";
        $rel = $this->db->query($asql)->result_array();
        $tmp = explode(',',$w['aid']);
        if($rel){
            foreach($rel as $row){
                if($row['child_id']){
                    $t = explode(',',$row['child_id']);
                    foreach($t as $r){
                        if(!in_array($row['id'],$tmp)){
                            $tmp[] = $row['id'];
                        }
                    }
                }
            }
        }
        $aid = implode(',',$tmp);
        $where = "gid = {$w['gid']} and site_id in ({$aid}) and session_type = 0 and del = 0 and upgraded = 1 and archive_category in ({$w['aid']}) ";
        if(isset($w['type'])){
            $where .= " and archive_class = {$w['type']}";
        }
        $sql = "select * from t_chat_session WHERE {$where}";
        $rel = $odb->query($sql)->result_array();
        $ret = array();
        if($rel){
            $session_id = array();
            $sdata = array();
            foreach($rel as $row){
                $session_id[] = $row['session_id'];
            }
            $str = implode(',',$session_id);
            $sql1 = "select * from bay_order WHERE session_id in ($str)";
            $ret = $odb->query($sql1)->result_array();
        }
        return $ret;
    }

    /**
     * 获取留言统计
     * @param array $val
     * @param array $field
     * @return array
     * @date
     */
    public function advise($val = array(),$field = array()){
        $odb = $this->load->database('online',true);
        $w = $this->_getValue($field,$val);
        $where = " a.gid = {$w['gid']} and a.site_id in ({$w['sid']})  and r.archive_category != 0";
        if(isset($w['type'])){
            $where .= " and r.archive_class ={$w['type']}";
        }
        $sql1 = "select r.* from t_advise_replies r LEFT JOIN t_advise a ON a.id = r.advise_id WHERE {$where} and r.archive_clock >= {$w['ast']} and r.archive_clock < {$w['aed']}";
        $sql2 = "select r.* from t_advise_replies r LEFT JOIN t_advise a ON a.id = r.advise_id WHERE {$where} and r.archive_clock >= {$w['bst']} and r.archive_clock < {$w['bed']}";
        //处理归档





        $sql = "select * from bay_archive  WHERE gid = {$w['gid']} and sid = {$w['sid']} and del = 0";
        $rel = $this->db->query($sql)->result_array();
        $aindx = array();
        $rindex = array();
        $archive = array();
        if($rel){
            foreach($rel as $row){
                $aindx[$row['id']] = $row['node'];
                $archive[$row['id']] = $row;
                $key = substr_count($row['node'],',');
                $rindex[$row['id']] = $key;
            }
        }

        $rel1 = $odb->query($sql1)->result_array();
        $rel2 = $odb->query($sql2)->result_array();
        $ret = array();
        $fdata = array();
        $sdata = array();
        $fnum = 0;
        $snum = 0;
        $data_key = array();
        if($rel1){
            $data_key['fdata'] = 'rel1';
            $fnum = count($rel1);
        }
        if($rel2){
            $data_key['sdata'] = 'rel2';
            $snum = count($rel2);;
        }
//        var_dump($rel1);
//        var_dump($rel2);die;
        //数据统计
        foreach($data_key as $s => $d){
            $data = $$d;
            $store =  &$$s;
            foreach($data as $row){
                if(isset($aindx[$row['archive_category']])){
                    $store[$row['archive_category']]['chat'] = isset($store[$row['archive_category']]['chat']) ?  $store[$row['archive_category']]['chat'] + 1 : 1;
                    $tmp = explode(',',$aindx[$row['archive_category']]);
                    array_pop($tmp);
//                    $child = array($row['archive_category']);
                    if($tmp){
                        foreach($tmp as $r){//循环为祖先节点添加数据
                            $store[$r]['chat'] = isset($store[$r]['chat']) ?  $store[$r]['chat'] + 1 : 1;
                            /*foreach($child as $c){
                                if(!isset($store[$r]['child']) || !in_array($c,$store[$r]['child'])) $store[$r]['child'][] = $c;
                            }
                            $child[] = $r;*/
                        }
                    }
                }else{//写日志记录归档错误？

                }
            }
        }



        //处理数据
        if($fdata){
            foreach($fdata as $key => &$row){
                if($row['chat']){
                    $row['rate'] = round(($row['chat'] / $fnum * 100),2);
                }else{
                    $row['rate'] = 0;
                }
                if(!isset($row['order'])){//补全工单
                    $row['order'] = 0;
                }
                if(isset($archive[$key])){
                    $row['fid'] = $archive[$key]['fid'];
                    $row['child_id'] = $archive[$key]['child_id'];
                    $row['node'] = $archive[$key]['node'];
                    $row['lv'] = $rindex[$key];
                    $row['id'] = $key;
                    $row['name'] = $archive[$key]['title'];
                }
                if(!isset($sdata[$key])){
                    $sdata[$key] = array(
                        'order' => 0,
                        'chat' => 0,
                        'rate' => 0
                    );
                    if(isset($archive[$key])){
                        $sdata[$key]['fid'] = $archive[$key]['fid'];
                        $sdata[$key]['child_id'] = $archive[$key]['child_id'];
                        $sdata[$key]['node'] = $archive[$key]['node'];
                        $sdata[$key]['lv'] = $rindex[$key];
                        $sdata[$key]['name'] = $archive[$key]['title'];
                    }
                }
            }
        }
        if($sdata){//第二次的要补缺第一次的数据(没有的归档加上)
            foreach($sdata as $key => &$row){
                if($row['chat']){
                    $row['rate'] = round(($row['chat'] / $snum * 100),2);
                }else{
                    $row['rate'] = 0;
                }
                if(!isset($row['order'])){//补全工单
                    $row['order'] = 0;
                }
                if(isset($archive[$key])){
                    $row['fid'] = $archive[$key]['fid'];
                    $row['child_id'] = $archive[$key]['child_id'];
                    $row['node'] = $archive[$key]['node'];
                    $row['lv'] = $rindex[$key];
                    $row['id'] = $key;
                    $row['name'] = $archive[$key]['title'];
                }

//                $ret['second'][$rindex[$key]][$key] = $row;

                if(!isset($fdata[$key])){
                    $fdata[$key] = array(
                        //                        //                        'child' => isset($row['child']) ? $row['child'] : array(),  
                        'order' => 0,
                        'chat' => 0,
                        'rate' => 0
                    );
                    if(isset($archive[$key])){
                        $fdata[$key]['fid'] = $archive[$key]['fid'];
                        $fdata[$key]['child_id'] = $archive[$key]['child_id'];
                        $fdata[$key]['node'] = $archive[$key]['node'];
                        $fdata[$key]['lv'] = $rindex[$key];
                        $fdata[$key]['id'] = $key;
                        $fdata[$key]['name'] = $archive[$key]['title'];
                    }
                }
            }
        }

        $ret['first'] = array_values($fdata);
        $ret['second'] = array_values($sdata);

        return $ret;




        if($fdata){
            foreach($fdata as $key => &$row){
                if($row['chat']){
                    $row['rate'] = round(($row['chat'] / $fnum * 100),2);
                }else{
                    $row['rate'] = 0;
                }
                if(isset($archive[$key])){
                    $row['fid'] = $archive[$key]['fid'];
                    $row['child_id'] = $archive[$key]['child_id'];
                    $row['node'] = $archive[$key]['node'];
                    $row['lv'] = $rindex[$key];
                    $row['id'] = $key;
                    $row['name'] = $archive[$key]['title'];
                }
            }
        }
        if($sdata){//第二次的要补缺第一次的数据(没有的归档加上)
            foreach($sdata as $key => &$row){
                if($row['chat']){
                    $row['rate'] = round(($row['chat'] / $snum * 100),2);
                }else{
                    $row['rate'] = 0;
                }
                if(isset($archive[$key])){
                    $row['fid'] = $archive[$key]['fid'];
                    $row['child_id'] = $archive[$key]['child_id'];
                    $row['node'] = $archive[$key]['node'];
                    $row['lv'] = $rindex[$key];
                    $row['id'] = $key;
                    $row['name'] = $archive[$key]['title'];
                }

//                $ret['second'][$rindex[$key]][$key] = $row;

                if(!isset($fdata[$key])){
                    $fdata[$key] = array(
                        //                        'child' => isset($row['child']) ? $row['child'] : array(), 
                        'order' => 0,
                        'chat' => 0,
                        'rate' => 0
                    );
                    if(isset($archive[$key])){
                        $fdata[$key]['fid'] = $archive[$key]['fid'];
                        $fdata[$key]['child_id'] = $archive[$key]['child_id'];
                        $fdata[$key]['node'] = $archive[$key]['node'];
                        $fdata[$key]['lv'] = $rindex[$key];
                        $fdata[$key]['id'] = $key;
                        $fdata[$key]['name'] = $archive[$key]['title'];
                    }
                }
            }
        }

        $ret['first'] = $fdata;
        $ret['second'] = $sdata;
        return $ret;


        //处理数据
        if($fdata){
            foreach($fdata as $key => $row){
                $ret['first'][$rindex[$key]][$key] = $row;
            }
        }
        if($sdata){//第二次的要补缺第一次的数据(没有的归档加上)
            foreach($sdata as $key => $row){
                $ret['second'][$rindex[$key]][$key] = $row;
                if(!isset($ret['first'][$rindex[$key]][$key])) $ret['first'][$rindex[$key]][$key] = array(
                    //                        'child' => isset($row['child']) ? $row['child'] : array(), 
                    'chat' => 0
                );
            }
        }
        return $ret;
    }

    /**
     * 获取留言
     * @param $val
     * @param $field
     * @return array
     * @date
     */
    public function getAdvise($val,$field = array()){
        $odb = $this->load->database('online',true);
        $w = $this->_getValue($field,$val);
        $where = "r.archive_category in ({$w['aid']})";
        if(isset($w['gid'])){
            $where .= " and a.gid = {$w['gid']}";
            if(isset($w['sid'])){
                $where .= " and a.site_id in ({$w['sid']})";
            }
        }
        if(isset($w['type'])){
            $where .= " and r.archive_class = {$w['type']}";
        }
        $limit  = $this->getLimit('','','',1);

        $sql = "select r.* from t_advise_replies r INNER JOIN t_advise a  ON r.advise_id = a.id WHERE {$where}  ORDER BY id DESC {$limit}";
        $_sql = "select count(*) as total from t_advise_replies r INNER JOIN t_advise a  ON r.advise_id = a.id WHERE {$where}";
        $rel = $odb->query($sql)->result_array();
        $num = 0;
        $ret = array();
//        var_dump($rel);die;
        if($rel){
            $_rel = $odb->query($_sql)->row_array();
            $num = $_rel['total'];
            $advise_id = array();
            foreach($rel as $row){
                if(!in_array($row['advise_id'],$advise_id))$advise_id[] = $row['advise_id'];
            }
            $str = implode(',',$advise_id);
            $sql1 = "select * from t_advise a INNER JOIN t_advise_replies r  WHERE a.id = r.advise_id AND a.id in ($str) ORDER BY reply_id ASC";
            $rel1 = $odb->query($sql1)->result_array();
//            var_dump($rel1);die;
            $tmp = array();
            if($rel1){
                foreach($rel1 as $row){
                    if(!isset($tmp[$row['advise_id']])){//第一行类型
                        $tmp[$row['advise_id']][] = array(
                            'from_client' => 1,
                            'msg' => $row['content'],
                            'reply_id' => 0
                        );
                    }
                    $tmp[$row['advise_id']][] = array(
                        'from_client' => $row['from_client'],
                        'msg' => $row['reply'],
                        'reply_id' => $row['reply_id']
                    );
                }
            }
            foreach($rel as $row){
                $ret[$row['reply_id']]['content'] = isset($tmp[$row['advise_id']]) ? $tmp[$row['advise_id']] : array();
                $ret[$row['reply_id']]['reply_id'] = $row['reply_id'];
                $ret[$row['reply_id']]['other'] = $row;
            }
        }
        return array(array_values($ret),$num);
    }


    /**
     * 推送归档统计
     * @param array $val
     * @param array $field
     * @return array
     * @date
     */
    public function send($val = array(),$field = array()){
        $odb = $this->load->database('online',true);
        $w = $this->_getValue($field,$val);
        $where = "gid = {$w['gid']} and site_id in ({$w['sid']}) and session_type = 1 and del = 0";
        if(isset($w['type'])){
            $where .= " and archive_class ={$w['type']}";
        }
        $sql1 = "select * from tmp_chat_session WHERE {$where} and clock >= {$w['ast']} and clock < {$w['aed']}";
        $sql2 = "select * from tmp_chat_session WHERE {$where} and clock >= {$w['bst']} and clock < {$w['bed']}";
        //处理归档
        $sql = "select * from bay_archive  WHERE gid = {$w['gid']} and sid = {$w['sid']} and del = 0";
        $rel = $this->db->query($sql)->result_array();
        $aindx = array();
        $archive = array();
        $rindex = array();
        if($rel){
            foreach($rel as $row){
                $aindx[$row['id']] = $row['node'];
                $archive[$row['id']] = $row;
                $key = substr_count($row['node'],',');
                $rindex[$row['id']] = $key;
            }
        }
        $rel1 = $odb->query($sql1)->result_array();
        $rel2 = $odb->query($sql2)->result_array();
        $ret = array();
        $fdata = array();
        $fnum = 0;
        $snum = 0;
        $sdata = array();
        $data_key = array();
        if($rel1){
            $data_key['fdata'] = 'rel1';
            $fnum = count($rel1);
        }
        if($rel2){
            $data_key['sdata'] = 'rel2';
            $snum = count($rel2);;
        }
        //数据统计
        foreach($data_key as $s => $d){
            $data = $$d;
            $store =  &$$s;
            foreach($data as $row){
                $order_flag = false;
//                var_dump($row['archive_category']);die;
                if(isset($aindx[$row['archive_category']])){
                    $store[$row['archive_category']]['chat'] = isset($store[$row['archive_category']]['chat']) ?  $store[$row['archive_category']]['chat'] + 1 : 1;
                    if($row['upgraded']){
                        $order_flag = true;
                        $store[$row['archive_category']]['order'] = isset($store[$row['archive_category']]['order']) ?  $store[$row['archive_category']]['order'] + 1 : 1;
                    }
                    $tmp = explode(',',$aindx[$row['archive_category']]);
                    array_pop($tmp);
//                    $child = array($row['archive_category']);
                    if($tmp){
                        foreach($tmp as $r){//循环为祖先节点添加数据
                            $store[$r]['chat'] = isset($store[$r]['chat']) ?  $store[$r]['chat'] + 1 : 1;
                            /*foreach($child as $c){
                                if(!isset($store[$r]['child']) || !in_array($c,$store[$r]['child'])) $store[$r]['child'][] = $c;
                            }
                            $child[] = $r;*/
                            if($order_flag){
                                $store[$r]['order'] = isset($store[$r]['order']) ?  $store[$r]['order'] + 1 : 1;
                            }
                        }
                    }
                }else{//写日志记录归档错误？

                }
            }
        }


        //处理数据
        if($fdata){
            foreach($fdata as $key => &$row){
                if($row['chat']){
                    $row['rate'] = round(($row['chat'] / $fnum * 100),2);
                }else{
                    $row['rate'] = 0;
                }
                if(!isset($row['order'])){//补全工单
                    $row['order'] = 0;
                }
                if(isset($archive[$key])){
                    $row['fid'] = $archive[$key]['fid'];
                    $row['child_id'] = $archive[$key]['child_id'];
                    $row['node'] = $archive[$key]['node'];
                    $row['lv'] = $rindex[$key];
                    $row['id'] = $key;
                    $row['name'] = $archive[$key]['title'];
                }
                if(!isset($sdata[$key])){
                    $sdata[$key] = array(
                        'order' => 0,
                        'chat' => 0,
                        'rate' => 0
                    );
                    if(isset($archive[$key])){
                        $sdata[$key]['fid'] = $archive[$key]['fid'];
                        $sdata[$key]['child_id'] = $archive[$key]['child_id'];
                        $sdata[$key]['node'] = $archive[$key]['node'];
                        $sdata[$key]['lv'] = $rindex[$key];
                        $sdata[$key]['name'] = $archive[$key]['title'];
                    }
                }
            }
        }
        if($sdata){//第二次的要补缺第一次的数据(没有的归档加上)
            foreach($sdata as $key => &$row){
                if($row['chat']){
                    $row['rate'] = round(($row['chat'] / $snum * 100),2);
                }else{
                    $row['rate'] = 0;
                }
                if(!isset($row['order'])){//补全工单
                    $row['order'] = 0;
                }
                if(isset($archive[$key])){
                    $row['fid'] = $archive[$key]['fid'];
                    $row['child_id'] = $archive[$key]['child_id'];
                    $row['node'] = $archive[$key]['node'];
                    $row['lv'] = $rindex[$key];
                    $row['id'] = $key;
                    $row['name'] = $archive[$key]['title'];
                }

//                $ret['second'][$rindex[$key]][$key] = $row;

                if(!isset($fdata[$key])){
                    $fdata[$key] = array(
                        //                        'child' => isset($row['child']) ? $row['child'] : array(), 
                        'order' => 0,
                        'chat' => 0,
                        'rate' => 0
                    );
                    if(isset($archive[$key])){
                        $fdata[$key]['fid'] = $archive[$key]['fid'];
                        $fdata[$key]['child_id'] = $archive[$key]['child_id'];
                        $fdata[$key]['node'] = $archive[$key]['node'];
                        $fdata[$key]['lv'] = $rindex[$key];
                        $fdata[$key]['id'] = $key;
                        $fdata[$key]['name'] = $archive[$key]['title'];
                    }
                }
            }
        }

        $ret['first'] = array_values($fdata);
        $ret['second'] = array_values($sdata);

        return $ret;


        //处理数据
        if($fdata){
            foreach($fdata as $key => &$row){
                if($row['chat']){
                    $row['rate'] = round(($row['chat'] / $fnum * 100),2);
                }else{
                    $row['rate'] = 0;
                }
                if(isset($archive[$key])){
                    $row['fid'] = $archive[$key]['fid'];
                    $row['child_id'] = $archive[$key]['child_id'];
                    $row['node'] = $archive[$key]['node'];
                    $row['lv'] = $rindex[$key];
                }
            }
        }
        if($sdata){//第二次的要补缺第一次的数据(没有的归档加上)
            foreach($sdata as $key => &$row){
                if($row['chat']){
                    $row['rate'] = round(($row['chat'] / $snum * 100),2);
                }else{
                    $row['rate'] = 0;
                }
                if(isset($archive[$key])){
                    $row['fid'] = $archive[$key]['fid'];
                    $row['child_id'] = $archive[$key]['child_id'];
                    $row['node'] = $archive[$key]['node'];
                    $row['lv'] = $rindex[$key];
                }

//                $ret['second'][$rindex[$key]][$key] = $row;

                if(!isset($fdata[$key])){
                    $fdata[$key] = array(
                        //                        'child' => isset($row['child']) ? $row['child'] : array(), 
                        'order' => 0,
                        'chat' => 0,
                        'rate' => 0
                    );
                    if(isset($archive[$key])){
                        $fdata[$key]['fid'] = $archive[$key]['fid'];
                        $fdata[$key]['child_id'] = $archive[$key]['child_id'];
                        $fdata[$key]['node'] = $archive[$key]['node'];
                        $fdata[$key]['lv'] = $rindex[$key];
                        $fdata[$key]['id'] = $key;
                        $fdata[$key]['name'] = $archive[$key]['title'];
                    }
                }
            }
        }
        $ret['first'] = $fdata;
        $ret['second'] = $sdata;
        return $ret;
    }


    /**
     * 会话归档报表
     * @param array $val
     * @param array $field
     * @return array
     * @date
     */
    public function onlineChart($val = array(),$field = array()){
        $odb = $this->load->database('online',true);
        $w = $this->_getValue($field,$val);
        $where = "gid = {$w['gid']} and site_id in ({$w['sid']}) and session_type = 0 and del = 0";
        if(isset($w['type'])){
            $where .= " and archive_class ={$w['type']}";
        }
        if($w['aid']){
//            $where .= " and archive_category in ({$w['aid']})";
        }
        $sql1 = "select * from tmp_chat_session WHERE {$where} and clock >= {$w['ast']} and clock < {$w['aed']}";
        $sql2 = "select * from tmp_chat_session WHERE {$where} and clock >= {$w['bst']} and clock < {$w['bed']}";
        //处理归档
        $sql = "select * from bay_archive  WHERE gid = {$w['gid']} and sid in ({$w['sid']}) and del = 0";
        $rel = $this->db->query($sql)->result_array();
        $aindx = array();
//        $rindex = array();
        if($rel){
            foreach($rel as $row){
                $aindx[$row['id']]['node'] = $row['node'];
                $aindx[$row['id']]['title'] = $row['title'];
//                $key = substr_count($row['node'],',');
//                $rindex[$key] = $row['id'];
            }
        }
        $rel1 = $odb->query($sql1)->result_array();
        $rel2 = $odb->query($sql2)->result_array();
        $ret = array();
        $fdata = array();
        $sdata = array();
        $adata = array();
        $idata = array();
        $data_key = array();
        if($rel1){
            $data_key[1]['data'] = 'rel1';
            $data_key[1]['store1'] = 'fdata';
            $data_key[1]['store2'] = 'adata';
        }
        if($rel2){
            $data_key[2]['data'] = 'rel2';
            $data_key[2]['store1'] = 'sdata';
            $data_key[2]['store2'] = 'idata';
        }
        //数据统计
        foreach($data_key as  $d){
            $data = $$d['data'];
            $store =  &$$d['store1'];
            foreach($data as $row){
                /*if($row['client_info']){
                    $detail = json_decode($row['client_info'],true);*/


                //测试数据
                if(1){
                    $ce = rand(0,1);
                    $nei = array('android','ios');
                    $detail['deviceType'] = $nei[$ce];
                //测试数据

                if(isset($detail) && $detail['deviceType']){
                        if($detail['deviceType'] == 'android' || $detail['deviceType'] == 'Android' || $detail['deviceType'] == 'ANDROID'){
                            $store[$aindx[$row['archive_category']]['title']]['android'] = isset($store[$aindx[$row['archive_category']]['title']]['android']) ? $store[$aindx[$row['archive_category']]['title']]['android'] + 1 : 1;
                        }
                        if($detail['deviceType'] == 'ios' || $detail['ios'] == 'Ios' || $detail['deviceType'] == 'IOS'){
                            $store[$aindx[$row['archive_category']]['title']]['ios'] = isset($store[$aindx[$row['archive_category']]['title']]['ios']) ? $store[$aindx[$row['archive_category']]['title']]['ios'] + 1 : 1;
                        }
                    }
                }

//                $ret['chart']['total'] = array(
//                    'series'=> array(
//                        array('name' => '接通总数','data' => $accept_total), name:iso android 第一个月 第二个月
//                        array('name' => '呼入总数','data' => $access_total),
//                        array('name' => '接通百分比','data' => $rate_total),
//                    ),
//                    'categories' => $categories // 归档分类
//                );

                $order_flag = false;
//                var_dump($aindx[$row['archive_category']]['title']);
//                var_dump($aindx[$row['archive_category']]);die;
                if(isset($aindx[$row['archive_category']])){
                    $store[$aindx[$row['archive_category']]['title']]['chat'] = isset($store[$aindx[$row['archive_category']]['title']]['chat']) ?  $store[$aindx[$row['archive_category']]['title']]['chat'] + 1 : 1;
                    if($row['upgraded']){
//                        $order_flag = true;
                        $store[$aindx[$row['archive_category']]['title']]['order'] = isset($store[$aindx[$row['archive_category']]['title']]['order']) ?  $store[$aindx[$row['archive_category']]['title']]['order'] + 1 : 1;
                    }
                    /*$tmp = explode(',',$aindx[$row['archive_category']]);
                    array_pop($tmp);
                    $child = array($row['archive_category']);
                    if($tmp){
                        foreach($tmp as $r){//循环为祖先节点添加数据
                            $store[$r]['chat'] = isset($store[$r]['chat']) ?  $store[$r]['chat'] + 1 : 1;
                            foreach($child as $c){
                                if(!isset($store[$r]['child']) || !in_array($c,$store[$r]['child'])) $store[$r]['child'][] = $c;
                            }
                            $child[] = $r;
                            if($order_flag){
                                $store[$r]['order'] = isset($store[$r]['order']) ?  $store[$r]['order'] + 1 : 1;
                            }
                        }
                    }*/
                }else{//写日志记录归档错误？

                }
            }
        }
//        var_dump($fdata);die;
        //处理数据

        //        series : [
//        {
//            name:'访问来源',
//            type:'pie',
//            radius : '55%',
//            center: ['50%', '50%'],
//            data:[
//                {value:335, name:'直接访问'},
//                {value:310, name:'邮件营销'},
//                {value:274, name:'联盟广告'},
//                {value:235, name:'视频广告'},
//                {value:400, name:'搜索引擎'}
//            ]
//         }

        $ccate = array();
        if($fdata){
            $cate = array();
            $data = array();
            $type = array();
            $type_total = array();
            $type_total['a'] = 0;
            $type_total['i'] = 0;
            $type_all = array();
            foreach($fdata as $name => $row){
                $ccate[] = $name;
                $data[] = array(
                    'name' => $name,
                    'value' => $row['chat'],
                );
                if(isset($row['android']) || isset($row['ios']) ){
                    $cate[] = $name;
                    /*$type['a'][] = isset($row['android']) ? $row['android'] : 0;
                    $type['i'][] = isset($row['ios']) ? $row['ios'] : 0;*/
                    if(isset($row['android'])){
                        $a = $type['a'][] = $row['android'];
                        $type_total['a'] = $type_total['a'] + $row['android'];
                    }else{
                        $a = $type['a'][] = 0;
                    }
                    if(isset($row['ios'])){
                        $b = $type['i'][] = $row['ios'];
                        $type_total['i'] = $type_total['i'] + $row['ios'];
                    }else{
                        $b = $type['i'][] = 0;
                    }
                    $type_all[] = $a + $b;

                }
            }
            $ret['pie']['series'][] = array(
                'name'  => '第一个月',
                'data' => $data,
            );

//            'series'=> array(
//                        array('name' => '接通总数','data' => $accept_total), name:iso android 第一个月 第二个月
//                        array('name' => '呼入总数','data' => $access_total),
//                        array('name' => '接通百分比','data' => $rate_total),
//                    ),
//                    'categories' => $categories // 归档分类

            //总分类
            $ret['type_total'] =  array(
                'series'=> array(
                    array('name' => '游戏归档量对比','type' => 'bar','barWidth' => '15%','data' => array($type_total['a'],$type_total['i']))
                ),
                'categories' => array('android','ios')
            );
            //分类
            if($cate){
                $ret['type_chart'] =  array(
                    'series'=> array(
                        array('name' => '总数','yAxixIndex' => 1,'type' => 'line','data' => $type_all),
                        array('name' => 'android','type' => 'bar','data' => $type['a'] ? $type['a'] : 0),
                        array('name' => 'ios','type' => 'bar','data' => $type['i'] ? $type['i'] : 0)
                    ),
                    'categories' => $cate
                );
            }

        }

        if($sdata){
            $data = array();
            foreach($sdata as $name => $row){
                if(!in_array($name,$ccate)){
                    $ccate[] = $name;
                    //补全第一个月的
                    $da['first'][$name] = array(

                    );
                }
                $data[] = array(
                    'name' => $name,
                    'value' => $row['chat'],
                );
            }
            $ret['pie']['series'][] = array(
                'name'  => '第二个月',
                'data' => $data,
            );
        }

        //柱行数据
        $fd = array();
        $fo = array();
        $sd = array();
        $so = array();
        $ccate_name = array();
//        var_dump($aindx[49]['title']);die;
        if($ccate){
            foreach($ccate as $name){
                //{
                //    name:'直接访问',
                //    type:'bar',
                //    data:[320, 332, 301, 334, 390, 330, 320]
                //},
                if(isset($fdata[$name])){
                    $fd[] = $fdata[$name]['chat'] ? $fdata[$name]['chat'] : 0;
                }else{
                    $fd[] = 0;
                }
                if(isset($fdata[$name]['order'])){
                    $fo[] = $fdata[$name]['order'] ? $fdata[$name]['order'] : 0;
                }else{
                    $fo[] = 0;
                }
                if(isset($sdata[$name])){
                    $sd[] = $sdata[$name]['chat'] ? $sdata[$name]['chat'] : 0;
                }else{
                    $sd[] = 0;
                }
                if(isset($sdata[$name]['order'])){
                    $so[] = $sdata[$name]['order'] ? $sdata[$name]['order'] : 0;
                }else{
                    $so[] = 0;
                }

            }


        }
//        var_dump($sd);die;
        if($ccate){
            $ret['chart'] = array(
                'series' => array(
                    array(
                        'name' => '首月数量',
                        'stack' => 'first',
                        'type' => 'bar',
                        'data' => $sd
                    ),
                    array(
                        'name' => '首月订单',
                        'stack' => 'first',
                        'type' => 'bar',
                        'data' => $so
                    ),
                    array(
                        'name' => '次月数量',
                        'stack' => 'second',
                        'type' => 'bar',
                        'data' => $fd
                    ),
                    array(
                        'name' => '次月订单',
                        'stack' => 'second',
                        'type' => 'bar',
                        'data' => $fo
                    ),
                ),
                'categories' => $ccate
            );
        }
        return $ret;
    }



    /**
     * 根据归档类型获取折线图表数据(在线)
     * @param $time
     * @param $_time
     * @param $val
     * @param array $field
     * @return array
     * @date
     */
    public function getOChart($time,$_time,$val,$field = array()){
        $odb = $this->load->database('online',true);
        $w = $this->_getValue($field,$val);
        //先缩小归档
//        $asql = "select * from bay_archive WHERE gid = {$w['gid']} and sid in ({$w['sid']})";
        $asql = "select * from bay_archive WHERE gid = {$w['gid']} and sid in ({$w['sid']}) AND title = 'hhhhh'";
//        var_dump($asql);die;
        $ids = '';
        $arel = $this->db->query($asql)->result_array();
//        var_dump($arel);die;
        if($arel){
            foreach($arel as $row){
                $ids .= $row['id'] . ',';
            }
        }
        $ids = rtrim($ids,',');
        if($ids){
            $a_str = " and archive_category in ({$ids})";
        }else{
            $a_str = "";
        }
//        var_dump($arel);die;


        $sql = "select * from tmp_chat_session WHERE gid = {$w['gid']} and site_id in ({$w['sid']}) and session_type = 0 and del = 0 {$a_str} and clock >= {$w['st']} and clock < {$w['ed']}";
//        var_dump($sql);die;
        $rel = $odb->query($sql)->result_array();
//        var_dump($rel);die;
        $ret = array();
        if($rel){
            $i = 0;
            $data = array();
            $len = count($_time) -1;
            foreach($rel as $row){
                $t = $row['clock'];
                if($t >= $_time[$i] &&  $t < $_time[$i+1]){
                    $str = $_time[$i] . '-' . $_time[$i+1];
                    $data[$str]['num'] = isset($data[$str]['num']) ? $data[$str]['num'] + 1 : 1;
//                    var_dump($str);die;
                }else{
                    for($n = 0;$n < $len;$n++){
                        if($t >= $_time[$n] &&  $t < $_time[$n+1]){
                            $str = $_time[$n] . '-' . $_time[$n+1];
                            $data[$str]['num'] = isset($data[$str]['num']) ? $data[$str]['num'] + 1 : 1;
                            $i = $n;
                            break;
                        }
                    }
                }
            }
//            die;
            //数据处理
            if($data){
                $series = array();
                for($n = 0;$n < $len;$n++){
                    $str = $_time[$n] . '-' . $_time[$n+1];
//                        var_dump($row);die;
                    if(isset($row[$str])){
                        $score = $row[$str]['num'];
                        $series[] = $score;
                    }else{
                        $series[] = 0;
                    }
                }
                //组织数据
                array_pop($time);
                $ret['series']['categories'] = $time;
                $ret['series']['series'] = array(
                    'name' => $w['name'],
                    'type' => 'line',
                    'data' => $series
                );
            }
        }
        return $ret;
    }



    /**
     * 留言归档报表
     * @param array $val
     * @param array $field
     * @return array
     * @date
     */
    public function adviseChart($val = array(),$field = array()){



        /*$odb = $this->load->database('online',true);
        $w = $this->_getValue($field,$val);
        $where = "gid = {$w['gid']} and site_id in ({$w['sid']}) and session_type = 0 and del = 0";
        if(isset($w['type'])){
            $where .= " and archive_class ={$w['type']}";
        }
        if($w['aid']){
//            $where .= " and archive_category in ({$w['aid']})";
        }
        $sql1 = "select * from tmp_chat_session WHERE {$where} and clock >= {$w['ast']} and clock < {$w['aed']}";
        $sql2 = "select * from tmp_chat_session WHERE {$where} and clock >= {$w['bst']} and clock < {$w['bed']}";
        //处理归档
        $sql = "select * from bay_archive  WHERE gid = {$w['gid']} and sid in ({$w['sid']}) and del = 0";
        $rel = $this->db->query($sql)->result_array();
        $aindx = array();
//        $rindex = array();
        if($rel){
            foreach($rel as $row){
                $aindx[$row['id']]['node'] = $row['node'];
                $aindx[$row['id']]['title'] = $row['title'];
//                $key = substr_count($row['node'],',');
//                $rindex[$key] = $row['id'];
            }
        }
        $rel1 = $odb->query($sql1)->result_array();
        $rel2 = $odb->query($sql2)->result_array();*/


        $odb = $this->load->database('online',true);
        $w = $this->_getValue($field,$val);
        $where = "a.gid = {$w['gid']} and a.site_id in ({$w['sid']})";
        if(isset($w['type'])){
            $where .= " and r.archive_class ={$w['type']}";
        }
        $sql1 = "select r.* from tmp_advise_replies r LEFT JOIN tmp_advise a ON a.id = r.advise_id WHERE {$where} and r.archive_clock >= {$w['ast']} and r.archive_clock < {$w['aed']}";
        $sql2 = "select r.* from tmp_advise_replies r LEFT JOIN tmp_advise a ON a.id = r.advise_id WHERE {$where} and r.archive_clock >= {$w['bst']} and r.archive_clock < {$w['bed']}";

        //处理归档
        $sql = "select * from bay_archive  WHERE gid = {$w['gid']} and sid in ({$w['sid']}) and del = 0";
        $rel = $this->db->query($sql)->result_array();
        $aindx = array();
        $archive = array();
        $rindex = array();
        if($rel){
            foreach($rel as $row){
                $aindx[$row['id']]['node'] = $row['node'];
                $aindx[$row['id']]['title'] = $row['title'];
                /*$aindx[$row['id']] = $row['node'];
                $archive[$row['id']] = $row;
                $key = substr_count($row['node'],',');
                $rindex[$row['id']] = $key;*/
            }
        }
        $rel1 = $odb->query($sql1)->result_array();
        $rel2 = $odb->query($sql2)->result_array();




        $ret = array();
        $fdata = array();
        $sdata = array();
        $adata = array();
        $idata = array();
        $data_key = array();
        if($rel1){
            $data_key[1]['data'] = 'rel1';
            $data_key[1]['store1'] = 'fdata';
            $data_key[1]['store2'] = 'adata';
        }
        if($rel2){
            $data_key[2]['data'] = 'rel2';
            $data_key[2]['store1'] = 'sdata';
            $data_key[2]['store2'] = 'idata';
        }
        //数据统计
        foreach($data_key as  $d){
            $data = $$d['data'];
            $store =  &$$d['store1'];
            foreach($data as $row){
                /*if($row['client_info']){
                    $detail = json_decode($row['client_info'],true);*/


                //测试数据
                if(1){
                    $ce = rand(0,1);
                    $nei = array('android','ios');
                    $detail['deviceType'] = $nei[$ce];
                    //测试数据

                    if(isset($detail) && $detail['deviceType']){
                        if($detail['deviceType'] == 'android' || $detail['deviceType'] == 'Android' || $detail['deviceType'] == 'ANDROID'){
                            $store[$aindx[$row['archive_category']]['title']]['android'] = isset($store[$aindx[$row['archive_category']]['title']]['android']) ? $store[$aindx[$row['archive_category']]['title']]['android'] + 1 : 1;
                        }
                        if($detail['deviceType'] == 'ios' || $detail['ios'] == 'Ios' || $detail['deviceType'] == 'IOS'){
                            $store[$aindx[$row['archive_category']]['title']]['ios'] = isset($store[$aindx[$row['archive_category']]['title']]['ios']) ? $store[$aindx[$row['archive_category']]['title']]['ios'] + 1 : 1;
                        }
                    }
                }

//                $ret['chart']['total'] = array(
//                    'series'=> array(
//                        array('name' => '接通总数','data' => $accept_total), name:iso android 第一个月 第二个月
//                        array('name' => '呼入总数','data' => $access_total),
//                        array('name' => '接通百分比','data' => $rate_total),
//                    ),
//                    'categories' => $categories // 归档分类
//                );

                $order_flag = false;
//                var_dump($aindx[$row['archive_category']]['title']);
//                var_dump($aindx[$row['archive_category']]);die;
                if(isset($aindx[$row['archive_category']])){
                    $store[$aindx[$row['archive_category']]['title']]['chat'] = isset($store[$aindx[$row['archive_category']]['title']]['chat']) ?  $store[$aindx[$row['archive_category']]['title']]['chat'] + 1 : 1;
                    if($row['upgraded']){
//                        $order_flag = true;
                        $store[$aindx[$row['archive_category']]['title']]['order'] = isset($store[$aindx[$row['archive_category']]['title']]['order']) ?  $store[$aindx[$row['archive_category']]['title']]['order'] + 1 : 1;
                    }
                    /*$tmp = explode(',',$aindx[$row['archive_category']]);
                    array_pop($tmp);
                    $child = array($row['archive_category']);
                    if($tmp){
                        foreach($tmp as $r){//循环为祖先节点添加数据
                            $store[$r]['chat'] = isset($store[$r]['chat']) ?  $store[$r]['chat'] + 1 : 1;
                            foreach($child as $c){
                                if(!isset($store[$r]['child']) || !in_array($c,$store[$r]['child'])) $store[$r]['child'][] = $c;
                            }
                            $child[] = $r;
                            if($order_flag){
                                $store[$r]['order'] = isset($store[$r]['order']) ?  $store[$r]['order'] + 1 : 1;
                            }
                        }
                    }*/
                }else{//写日志记录归档错误？

                }
            }
        }
//        var_dump($fdata);die;
        //处理数据

        //        series : [
//        {
//            name:'访问来源',
//            type:'pie',
//            radius : '55%',
//            center: ['50%', '50%'],
//            data:[
//                {value:335, name:'直接访问'},
//                {value:310, name:'邮件营销'},
//                {value:274, name:'联盟广告'},
//                {value:235, name:'视频广告'},
//                {value:400, name:'搜索引擎'}
//            ]
//         }

        $ccate = array();
        if($fdata){
            $cate = array();
            $data = array();
            $type = array();
            $type_total = array();
            $type_total['a'] = 0;
            $type_total['i'] = 0;
            $type_all = array();
            foreach($fdata as $name => $row){
                $ccate[] = $name;
                $data[] = array(
                    'name' => $name,
                    'value' => $row['chat'],
                );
                if(isset($row['android']) || isset($row['ios']) ){
                    $cate[] = $name;
                    /*$type['a'][] = isset($row['android']) ? $row['android'] : 0;
                    $type['i'][] = isset($row['ios']) ? $row['ios'] : 0;*/
                    if(isset($row['android'])){
                        $a = $type['a'][] = $row['android'];
                        $type_total['a'] = $type_total['a'] + $row['android'];
                    }else{
                        $a = $type['a'][] = 0;
                    }
                    if(isset($row['ios'])){
                        $b = $type['i'][] = $row['ios'];
                        $type_total['i'] = $type_total['i'] + $row['ios'];
                    }else{
                        $b = $type['i'][] = 0;
                    }
                    $type_all[] = $a + $b;

                }
            }
            $ret['pie']['series'][] = array(
                'name'  => '第一个月',
                'data' => $data,
            );

//            'series'=> array(
//                        array('name' => '接通总数','data' => $accept_total), name:iso android 第一个月 第二个月
//                        array('name' => '呼入总数','data' => $access_total),
//                        array('name' => '接通百分比','data' => $rate_total),
//                    ),
//                    'categories' => $categories // 归档分类

            //总分类
            $ret['type_total'] =  array(
                'series'=> array(
                    array('name' => '游戏归档量对比','type' => 'bar','barWidth' => '15%','data' => array($type_total['a'],$type_total['i']))
                ),
                'categories' => array('android','ios')
            );
            //分类
            if($cate){
                $ret['type_chart'] =  array(
                    'series'=> array(
                        array('name' => '总数','yAxixIndex' => 1,'type' => 'line','data' => $type_all),
                        array('name' => 'android','type' => 'bar','data' => $type['a'] ? $type['a'] : 0),
                        array('name' => 'ios','type' => 'bar','data' => $type['i'] ? $type['i'] : 0)
                    ),
                    'categories' => $cate
                );
            }

        }

        if($sdata){
            $data = array();
            foreach($sdata as $name => $row){
                if(!in_array($name,$ccate)){
                    $ccate[] = $name;
                    //补全第一个月的
                    $da['first'][$name] = array(

                    );
                }
                $data[] = array(
                    'name' => $name,
                    'value' => $row['chat'],
                );
            }
            $ret['pie']['series'][] = array(
                'name'  => '第二个月',
                'data' => $data,
            );
        }

        //柱行数据
        $fd = array();
        $fo = array();
        $sd = array();
        $so = array();
        $ccate_name = array();
//        var_dump($aindx[49]['title']);die;
        if($ccate){
            foreach($ccate as $name){
                //{
                //    name:'直接访问',
                //    type:'bar',
                //    data:[320, 332, 301, 334, 390, 330, 320]
                //},
                if(isset($fdata[$name])){
                    $fd[] = $fdata[$name]['chat'] ? $fdata[$name]['chat'] : 0;
                }else{
                    $fd[] = 0;
                }
                if(isset($fdata[$name]['order'])){
                    $fo[] = $fdata[$name]['order'] ? $fdata[$name]['order'] : 0;
                }else{
                    $fo[] = 0;
                }
                if(isset($sdata[$name])){
                    $sd[] = $sdata[$name]['chat'] ? $sdata[$name]['chat'] : 0;
                }else{
                    $sd[] = 0;
                }
                if(isset($sdata[$name]['order'])){
                    $so[] = $sdata[$name]['order'] ? $sdata[$name]['order'] : 0;
                }else{
                    $so[] = 0;
                }

            }


        }
//        var_dump($sd);die;
        if($ccate){
            $ret['chart'] = array(
                'series' => array(
                    array(
                        'name' => '首月数量',
                        'stack' => 'first',
                        'type' => 'bar',
                        'data' => $sd
                    ),
                    array(
                        'name' => '首月订单',
                        'stack' => 'first',
                        'type' => 'bar',
                        'data' => $so
                    ),
                    array(
                        'name' => '次月数量',
                        'stack' => 'second',
                        'type' => 'bar',
                        'data' => $fd
                    ),
                    array(
                        'name' => '次月订单',
                        'stack' => 'second',
                        'type' => 'bar',
                        'data' => $fo
                    ),
                ),
                'categories' => $ccate
            );
        }
        return $ret;




        $odb = $this->load->database('online',true);
        $w = $this->_getValue($field,$val);
        $where = "a.gid = {$w['gid']} and a.site_id in ({$w['sid']})";
        if(isset($w['type'])){
            $where .= " and r.archive_class ={$w['type']}";
        }
        $sql1 = "select r.* from tmp_advise_replies r LEFT JOIN t_advise a ON a.id = r.advise_id WHERE {$where} and r.archive_clock >= {$w['ast']} and r.archive_clock < {$w['aed']}";
        $sql2 = "select r.* from tmp_advise_replies r LEFT JOIN t_advise a ON a.id = r.advise_id WHERE {$where} and r.archive_clock >= {$w['bst']} and r.archive_clock < {$w['bed']}";

        //处理归档
        $sql = "select * from bay_archive  WHERE gid = {$w['gid']} and sid = {$w['sid']} and del = 0";
        $rel = $this->db->query($sql)->result_array();
        $aindx = array();
        $archive = array();
        $rindex = array();
        if($rel){
            foreach($rel as $row){
                $aindx[$row['id']] = $row['node'];
                $archive[$row['id']] = $row;
                $key = substr_count($row['node'],',');
                $rindex[$row['id']] = $key;
            }
        }
        $rel1 = $odb->query($sql1)->result_array();
        $rel2 = $odb->query($sql2)->result_array();
        $ret = array();
        $fdata = array();
        $fnum = 0;
        $snum = 0;
        $sdata = array();
        $data_key = array();
        if($rel1){
            $data_key['fdata'] = 'rel1';
            $fnum = count($rel1);
        }
        if($rel2){
            $data_key['sdata'] = 'rel2';
            $snum = count($rel2);;
        }
        //数据统计
        foreach($data_key as $s => $d){
            $data = $$d;
            $store =  &$$s;
            foreach($data as $row){
                $order_flag = false;
//                var_dump($row['archive_category']);die;
                if(isset($aindx[$row['archive_category']])){
                    $store[$row['archive_category']]['chat'] = isset($store[$row['archive_category']]['chat']) ?  $store[$row['archive_category']]['chat'] + 1 : 1;
                    if($row['upgraded']){
                        $order_flag = true;
                        $store[$row['archive_category']]['order'] = isset($store[$row['archive_category']]['order']) ?  $store[$row['archive_category']]['order'] + 1 : 1;
                    }
                    $tmp = explode(',',$aindx[$row['archive_category']]);
                    array_pop($tmp);
//                    $child = array($row['archive_category']);
                    if($tmp){
                        foreach($tmp as $r){//循环为祖先节点添加数据
                            $store[$r]['chat'] = isset($store[$r]['chat']) ?  $store[$r]['chat'] + 1 : 1;
                            /*foreach($child as $c){
                                if(!isset($store[$r]['child']) || !in_array($c,$store[$r]['child'])) $store[$r]['child'][] = $c;
                            }
                            $child[] = $r;*/
                            if($order_flag){
                                $store[$r]['order'] = isset($store[$r]['order']) ?  $store[$r]['order'] + 1 : 1;
                            }
                        }
                    }
                }else{//写日志记录归档错误？

                }
            }
        }
        //处理数据
        if($fdata){
            foreach($fdata as $key => &$row){
                if($row['chat']){
                    $row['rate'] = round(($row['chat'] / $fnum * 100),2);
                }else{
                    $row['rate'] = 0;
                }
                if(isset($archive[$key])){
                    $row['fid'] = $archive[$key]['fid'];
                    $row['child_id'] = $archive[$key]['child_id'];
                    $row['node'] = $archive[$key]['node'];
                    $row['lv'] = $rindex[$key];
                    $row['id'] = $key;
                    $row['name'] = $archive[$key]['title'];
                }
                if(!isset($sdata[$key])){
                    $sdata[$key] = array(
                        'order' => 0,
                        'chat' => 0,
                        'rate' => 0
                    );
                    if(isset($archive[$key])){
                        $sdata[$key]['fid'] = $archive[$key]['fid'];
                        $sdata[$key]['child_id'] = $archive[$key]['child_id'];
                        $sdata[$key]['node'] = $archive[$key]['node'];
                        $sdata[$key]['lv'] = $rindex[$key];
                        $sdata[$key]['name'] = $archive[$key]['title'];
                    }
                }
            }
        }
        if($sdata){//第二次的要补缺第一次的数据(没有的归档加上)
            foreach($sdata as $key => &$row){
                if($row['chat']){
                    $row['rate'] = round(($row['chat'] / $snum * 100),2);
                }else{
                    $row['rate'] = 0;
                }
                if(isset($archive[$key])){
                    $row['fid'] = $archive[$key]['fid'];
                    $row['child_id'] = $archive[$key]['child_id'];
                    $row['node'] = $archive[$key]['node'];
                    $row['lv'] = $rindex[$key];
                    $row['id'] = $key;
                    $row['name'] = $archive[$key]['title'];
                }

//                $ret['second'][$rindex[$key]][$key] = $row;

                if(!isset($fdata[$key])){
                    $fdata[$key] = array(
                        //                        'child' => isset($row['child']) ? $row['child'] : array(), 
                        'order' => 0,
                        'chat' => 0,
                        'rate' => 0
                    );
                    if(isset($archive[$key])){
                        $fdata[$key]['fid'] = $archive[$key]['fid'];
                        $fdata[$key]['child_id'] = $archive[$key]['child_id'];
                        $fdata[$key]['node'] = $archive[$key]['node'];
                        $fdata[$key]['lv'] = $rindex[$key];
                        $fdata[$key]['id'] = $key;
                        $fdata[$key]['name'] = $archive[$key]['title'];
                    }
                }
            }
        }
        $ret['first'] = $fdata;
        $ret['second'] = $sdata;
        return $ret;
    }


    /**
     * 根据归档类型获取折线图表数据(留言)
     * @param $time
     * @param $_time
     * @param $val
     * @param array $field
     * @return array
     * @date
     */
    public function getAChart($time,$_time,$val,$field = array()){
        $odb = $this->load->database('online',true);
        $w = $this->_getValue($field,$val);
        //先缩小归档
        $asql = "select * from bay_archive WHERE gid = {$w['gid']} and sid in ({$w['sid']}) AND title = '{$w['name']}'";
        $ids = '';
        $arel = $this->db->query($asql)->result_array();
        if($arel){
            foreach($arel as $row){
                $ids .= $row['id'] . ',';
            }
        }
        $ids = rtrim($ids,',');
        if($ids){
            $a_str = " and r.archive_category in ({$ids})";
        }else{
            $a_str = "";
        }
        $sql = "select r.* from tmp_advise_replies r INNER JOIN tmp_advise a ON r.advise = a.id WHERE a.gid = {$w['gid']} and a.site_id in ({$w['sid']})  and a.del = 0 and r.archive_category != 0  {$a_str} and r.reply_clock >= {$w['st']} and r.reply_clock < {$w['ed']}";
        $rel = $odb->query($sql)->result_array();
        $ret = array();
        if($rel){
            $i = 0;
            $data = array();
            $len = count($_time) -1;
            foreach($rel as $row){
                $t = $row['reply_clock'];
                if($t >= $_time[$i] &&  $t < $_time[$i+1]){
                    $str = $_time[$i] . '-' . $_time[$i+1];
                    $data[$str]['num'] = isset($data[$str]['num']) ? $data[$str]['num'] + 1 : 1;
                }else{
                    for($n = 0;$n < $len;$n++){
                        if($t >= $_time[$n] &&  $t < $_time[$n+1]){
                            $str = $_time[$n] . '-' . $_time[$n+1];
                            $data[$str]['num'] = isset($data[$str]['num']) ? $data[$str]['num'] + 1 : 1;
                            $i = $n;
                            break;
                        }
                    }
                }
            }
            //数据处理
            if($data){
                $series = array();
                for($n = 0;$n < $len;$n++){
                    $str = $_time[$n] . '-' . $_time[$n+1];
                    if(isset($row[$str])){
                        $score = $row[$str]['num'];
                        $series[] = $score;
                    }else{
                        $series[] = 0;
                    }
                }
                //组织数据
                array_pop($time);
                $ret['series']['categories'] = $time;
                $ret['series']['series'] = array(
                    'name' => $w['name'],
                    'data' => $series
                );
            }
        }
        return $ret;
    }
}


