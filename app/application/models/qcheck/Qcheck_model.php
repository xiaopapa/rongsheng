<?php
/**
 * 质检model
 * class Qcheck_model
 * Created by PhpStorm.
 * User: HarveyYang
 * Date: 2017/10/11
 * Time: 14:11
 */

class Qcheck_model extends Bmax_Model {
    private $odb;
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->odb = $this->load->database('online',true);
    }

    /**
     * 新建一条考核
     * @param $title
     * @param array $v
     * @return bool|string
     * @date
     */
    public function newQcate($title,$v = array()){
        $data = array(
            'title' => $title,
            'etime' => time(),
            'editor' => $this->_uid,
        );
        $this->db->trans_start();
        if(!$v){//一级目录
            $data['type'] = 1;
            $this->db->set($data)->insert("qcheckcate");
            $new_id = $id = $this->db->insert_id();
            $this->db->set('node',$new_id)->where('id',$new_id)->update("qcheckcate");
        }else{//二级目录
            $data['fid'] = $v['fid'];
            $data['content'] = $v['content'];
            $data['type'] = 2;
            $data['major'] = $v['major'];

            $this->db->set($data)->insert('qcheckcate');
            $new_id = $id = $this->db->insert_id();
            $sql = "SELECT * FROM `bay_qcheckcate` WHERE id = {$v['fid']}";
            $rel = $this->db->query($sql)->row_array();
            if(!$rel) return false;
            $node = $rel['node'];
            if($node){//更新祖先节点子ID
                $this->_addNode($node,$id);
            }
            //更新新插入的node
            $child_node = fun::appendSome($node,$id);
            $sql = "UPDATE `bay_qcheckcate` set node = '{$child_node}' WHERE id = {$id}";
            $this->db->query($sql);
        }
        $this->db->trans_commit();
        if ($this->db->trans_status() === FALSE){//失败
            return "操作失败，请重试";
        }else{//成功
            return array('id' =>$new_id);
        }
    }

    /**
     * 编辑、删除
     * @param array $filed
     * @param array $val
     * @return bool|string
     * @date
     */
    public function editCate($filed = array(),$val = array()){
        $w = $this->_getValue($filed,$val);
        $this->db->trans_start();
        $data = array(
            'etime' => time(),
            'editor' => $this->_uid,
        );
        $id = $w['id'];
        if(isset($w['del']) && $w['del'] == 1){//删除操作,事务
            $data['del'] = 1;
            $this->db->set($data)->where('id',$id)->update('qcheckcate');
            //删除该ID下面所有子ID
            $rel = $this->db->where('id',$id)->get('qcheckcate')->row_array();
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
            //一级更新、二级更新
            $data['title'] = $w['title'];
            if(isset($w['content'])){
                $data['content'] = $w['content'];
            }
            if(isset($w['major'])){
                $data['major'] = $w['major'];
            }
            $this->db->set($data)->where('id',$id)->update('qcheckcate');
        }
        $this->db->trans_complete();
        if ($this->db->trans_status() === FALSE){//失败
            return "操作失败，请重试";
        }else{//成功
            return true;
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
            $sql = "SELECT * FROM `bay_qcheckcate` WHERE id = {$row}";
            $rel = $this->db->query($sql)->row_array();
            if(isset($rel['child_id'])){
                $new_str = fun::appendSome($rel['child_id'],$cid);
                $sql = "UPDATE  `bay_qcheckcate` SET child_id = '{$new_str}' WHERE id = {$row}";
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
            $sql = "SELECT * FROM `bay_qcheckcate` WHERE id = {$id}";
            $rel = $this->db->query($sql)->row_array();
            if(isset($rel['child_id']) && $rel['child_id']){
                $new_str = fun::delSome($rel['child_id'],$child_id);
                $sql = "UPDATE  `bay_qcheckcate` SET child_id = '{$new_str}' WHERE id = {$id}";
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
        $sql = "UPDATE `bay_qcheckcate` set del = 1 WHERE id IN ({$child_id})";
        $this->db->query($sql);
    }

    //考核标准列表
    public function qcateList(){
        $ret = $this->db->where('del','0')->get("qcheckcate")->result_array();
        return $ret;
    }

    //在线质检
    public function onlineQcheck($field = array(),$val = array()){
        $w = $this->_getValue($field,$val);
        $where = " s.qcheck = 0 and s.clock >= {$w['st']} and s.clock < {$w['ed']}";
        if(isset($w['uid'])){
            $where .= " and s.service_id = {$w['uid']}";
        }
        if(isset($w['npt'])){
            $where .= " and l.respond_rating IS null";
        }else{
            if(isset($w['rs'])){
                $where .= " and l.respond_rating = {$w['rs']}";
            }
            if(isset($w['at'])){
                $where .= " and l.attitude_rating = {$w['at']}";
            }
            if(isset($w['ex'])){
                $where .= " and l.experience_rating = {$w['ex']}";
            }
        }
        if(isset($w['type'])){
            if($w['type'] == 1){
                $where .= " and s.invalid = 0";
            }elseif($w['type'] == 2){
                $where .= " and s.invalid = 1";
            }
        }
        if(isset($w['sort'])){
            $sort = " order by s.id";
        }else{
            $sort = " order by s.id";
        }
//        var_dump($where);die;
        $limit = $this->getLimit('','','',1);
        $sql = "select s.*,l.respond_rating,l.attitude_rating,l.experience_rating from t_chat_session s LEFT JOIN t_rating_log l ON s.session_id = l.session_id WHERE {$where} {$sort} {$limit}";
        $rel = $this->odb->query($sql)->result_array();
        $ret = array();
        $num = 0;
        if($rel){
            $c_sql = "select count(*) as total from t_chat_session s LEFT JOIN t_rating_log l ON s.session_id = l.session_id WHERE {$where}";
            $c_rel = $this->odb->query($c_sql)->row_array();
            $num = $c_rel['total'];
            $s_id = array();
            $aids = array();
            foreach($rel as $row){
                $s_id[] = $row['session_id'];
                $aids[] = $row['archive_category'];
            }
            $aids = implode(',',$aids);
            $archive = $this->_getArchive($aids);
//            var_dump($archive);die;
            $s_id = implode(',',$s_id);
            $csql = "select * from t_chat_content WHERE session_id in ({$s_id})";
            $osql = "select * from bay_order WHERE session_id in ({$s_id})";
            $crel = $this->odb->query($csql)->result_array();
            $orel = $this->db->query($osql)->result_array();
            $content = array();
            $order = array();
            if($crel){
                foreach($crel as $r){
                    $content[$r['session_id']][] = $r;
                }
            }
            if($orel){
                foreach($orel as $r){
                    $order[$r['session_id']][] = $r['id'];
                }
            }
            foreach($rel as &$r){
                if(isset($content[$r['session_id']])){
                    $r['detail'] = $content[$r['session_id']];
                }
                if(isset($order[$r['session_id']])){
                    $r['order_id'] = $order[$r['session_id']];
                }
                $r['archive_name'] = implode('',fun::reflect($r['archive_category'],$archive));
            }
            $ret = $rel;
        }
        return array($ret,$num);
    }

    //留言质检
    public function adviseQcheck($field = array(),$val = array()){
        $w = $this->_getValue($field,$val);
        $where = " r.qcheck = 0 and r.from_client = 0 and r.archive_clock >= {$w['st']} and r.archive_clock < {$w['ed']}";
        if(isset($w['uid'])){
            $where .= " and r.service_id = {$w['uid']}";
        }
        if(isset($w['npt'])){
            $where .= " and r.rating = 0";
        }elseif(isset($w['pt'])){
            $where .= " and r.rating = {$w['pt']}";
        }
        if(isset($w['type'])){
            if($w['type'] == 1){
                $where .= " and r.invalid = 0";
            }elseif($w['type'] == 2){
                $where .= " and r.invalid = 1";
            }
        }
        if(isset($w['sort'])){
            $sort = " order by r.reply_id";
        }else{
            $sort = " order by r.reply_id";
        }
        $limit = $this->getLimit('','','',1);
        $sql = "select * from t_advise_replies r WHERE {$where} {$sort} {$limit}";
//        var_dump($sql);
        $rel = $this->odb->query($sql)->result_array();
        $ret = array();
        $num = 0;
        if($rel){
            $c_sql = "select count(*) as total from t_advise_replies r WHERE {$where}";
            $c_rel = $this->odb->query($c_sql)->row_array();
            $num = $c_rel['total'];
            $r_id = array();
            $adata = array();
            $aids = array();
            foreach($rel as $row){
                if(!in_array($row['advise_id'],$r_id)) $r_id[] = $row['advise_id'];
                $aids[] = $row['archive_category'];
            }
            $aids = implode(',',$aids);
            $archive = $this->_getArchive($aids);
            $r_id = implode(',',$r_id);
            $csql = "select a.content,a.clock as ftime,pic,a.client_id,r.* from t_advise a INNER JOIN t_advise_replies r ON a.id = r.advise_id  WHERE id in ({$r_id}) ORDER BY r.reply_id ASC ";
            $asql = "select a.* from t_advise a WHERE id in ({$r_id})";
            $crel = $this->odb->query($csql)->result_array();
            $arel = $this->odb->query($asql)->result_array();
            if($arel){
                foreach($arel as $r){
                    $adata[$r['id']] = $r;
                }
            }
            if($crel){
                foreach($crel as $r){
                    //第一句
//                    var_dump($r);die;
                    if(!isset($adata[$r['advise_id']]['detail'])){
                        $adata[$r['advise_id']]['detail'][] = array(
                            'reply' => $r['content'],
                            'reply_clock' => $r['ftime'],
                            'client_id' => $r['client_id'],
                            'service_id' => $r['service_id'],
                            "from_client" => 1,
                            "pic" => $adata[$r['advise_id']]['pic']
                        );
                    }
                    $adata[$r['advise_id']]['detail'][] = $r;
                }
            }
            foreach($rel as &$r){
                $adivs_id = $r['advise_id'];
                $c_id = $r['reply_id'];
                $data = $adata[$adivs_id];
                $data['check_id'] = $c_id;
                $data['service_id'] = $r['service_id'];
                $data['archive_name'] = fun::reflect($r['archive_category'],$archive);
                $ret[] = $data;
            }
        }
        return array($ret,$num);
    }

    //举报质检
    public function reportQcheck($field = array(),$val = array()){
        $w = $this->_getValue($field,$val);
        $where = " status != 0 and  qcheck = 0 and archive_clock >= {$w['st']} and archive_clock < {$w['ed']}";
        if(isset($w['uid'])){
            $where .= " and service_id = {$w['uid']}";
        }
        if(isset($w['type'])){
            if($w['type'] == 1){
                $where .= " and invalid = 0";
            }elseif($w['type'] == 2){
                $where .= " and invalid = 1";
            }
        }
        if(isset($w['sort'])){
            $sort = " order by id";
        }else{
            $sort = " order by id";
        }
//        var_dump($where);die;
        $limit = $this->getLimit('','','',1);
        $sql = "select * from t_report WHERE {$where} {$sort} {$limit}";
        $c_sql = "select count(*) as total from t_report WHERE {$where}";
        $c_rel = $this->odb->query($c_sql)->row_array();
        $num = $c_rel['total'];
        $ret = $this->odb->query($sql)->result_array();
        if($ret){
            $aids = array();
            foreach($ret as $r){
                $aids[] = $r['archive_category'];
            }
            $aids = implode(',',$aids);
            $archive = $this->_getArchive($aids);
            foreach($ret as &$r){
                $r['archive_name'] = implode('',fun::reflect($r['archive_category'],$archive));
            }

        }
        return array($ret,$num);
    }

    //获取今日质检
    public function getCheckNum(){
        list($uids) = $this->getCheckUid();
        if(in_array($this->_uid,$uids)){//当前员工是质检员
            $st = strtotime(date("Y-m-d"));
            $ed = $st + 3600*24 - 1;
            $online = 0;
            $advise = 0;
            $report = 0;
            $sql1 = "select count(*) as total from  bay_checkinfo where type = 1 and uid = {$this->_uid}";
            $sql2 = "select count(*) as total from  bay_checkinfo where type = 2 and uid = {$this->_uid}";
            $sql3 = "select count(*) as total from  bay_checkinfo where type = 3 and uid = {$this->_uid}";
            $rel1 = $this->db->query($sql1)->result_array();
            if($rel1){
                $online = $rel1['total'];
            }
            $rel2 = $this->db->query($sql2)->result_array();
            if($rel1){
                $advise = $rel1['total'];
            }
            $rel3 = $this->db->query($sql3)->result_array();
            if($rel1){
                $report = $rel1['total'];
            }
            return array(
                "online" => $online,
                "advise" => $advise,
                "report" => $report
            );
        }else{
            return "当前用户不是质检员";
        }
    }

    //获取质检组组员工ID
    public function getCheckUid(){
        $where = array(
            'type' => 1,
            'del' => 0
        );
        $rel = $this->db->where($where)->get('group')->result_array();
        $ids = array();
        if($rel){
            foreach($rel as $row){
                $ids[] = $row['id'];
            }
        }
        if(!$ids) return array(array(),array());
        $group = array();
        $leader = array();
        foreach($ids as $id){
            $sql = "select * from bay_users WHERE find_in_set({$id},grp)";
            $_sql = "select * from bay_users WHERE find_in_set({$id},leader)";
            $rel = $this->db->query($sql)->result_array();
            if($rel){
                foreach($rel as $r){
                    $group[] = $r['uid'];
                }
            }
            $_rel = $this->db->query($_sql)->result_array();
            if($_rel){
                foreach($_rel as $r){
                    $leader[] = $r['uid'];
                }
            }
        }
        return array($group,$leader);
    }


    //质检一条在线
    public function checkOnline($val,$field = array()){
        $w = $this->_getValue($field,$val);
        //先把内容取出来
        $sql = "select * from t_chat_session WHERE session_id = '{$w['session_id']}'";
        $rel = $this->odb->query($sql)->row_array();
        if($rel){
            $major = 0;
            $not_major = 0;
            $_cids = array();
            //取反馈时间
            $fsql = "select clock as ftime from t_chat_content WHERE session_id = '{$w['session_id']}' AND  from_client = 0 and service_id != 9527 ORDER BY id limit 1";
            $frel = $this->odb->query($fsql)->row_array();
            $ftime = $frel['ftime'];
            $ctype = 1;
            if(isset($w['cids'])  && $w['type'] == 2){
                $csql = "select * from bay_qcheckcate WHERE id in ({$w['cids']}) AND del = 0 AND `type` = 2";
                $crel = $this->db->query($csql)->result_array();
                if($crel){
                    foreach($crel as $r){
                        if($r['major'] == 1){
                            $major++;
                        }elseif($r['major'] == 2){
                            $not_major++;
                        }
                        $_cids[] = $r['id'];
                    }
                    $ctype = 2;
                }
            }
            $data = array(
                "type" => 1,
                "tid" => $w['session_id'],
                "major" => $major,
                "not_major" => $not_major,
                "cids" => implode(',',$_cids),
                "ftime" => $ftime,
                "archive_clock" => $rel['clock'],
                "remark" => isset($w['remark']) ? $w['remark'] : '',
                'cuid' => $rel['service_id'],
                'qtime' => time(),
                'uid' => $this->_uid,
                'invalid' => $rel['invalid'],
                'ctype' => $ctype
            );
            //开启事务
            $this->odb->trans_start();
            $irel = $this->db->set($data)->insert("qcheckinfo");
            if(!$irel) return "质检提交失败，清重试~";
            $usql = "update t_chat_session set qcheck = 1 WHERE session_id = '{$w['session_id']}'";
            $this->odb->query($usql);
            $this->odb->trans_complete();
            if ($this->db->trans_status() === FALSE){//失败
                return "保存失败，请重试";
            }else{//成功
                return true;
            }
        }else{
            $ret = "没有这么一个会话ID:{$w['session_id']}";
        }
        return $ret;
    }

    //质检一条留言
    public function checkAdvise($val,$field = array()){
        $w = $this->_getValue($field,$val);
        //先把内容取出来
        $sql = "select * from t_advise_replies WHERE reply_id = '{$w['reply_id']}'";
        $rel = $this->odb->query($sql)->row_array();
        if($rel){
            $major = 0;
            $not_major = 0;
            $_cids = array();
            //取反馈时间
            $fsql = "select a.clock as ftime,r.* from t_advise a INNER JOIN t_advise_replies r  ON a.id = r.advise_id  WHERE a.id = '{$rel['advise_id']}' ORDER BY reply_id";
            $frel = $this->odb->query($fsql)->result_array();
//            var_dump($frel);die;
            if(count($frel) == 1){//只有1条记录，表示没有追问，反馈时间肯定是在t_advise表
                $ftime = $frel[0]['ftime'];
                $archive_clock = $frel[0]['archive_clock'];
                $invalid = $frel[0]['invalid'];
                $service_id = $frel[0]['service_id'];
            }else{
                foreach($frel as $key => $r){

                    if($key == 0){
//                        var_dump($r);die;
                        $ftime = $r['ftime'];
                        //第一条还是要到t_advise表里取
                        $archive_clock = $r['archive_clock'];
                        $invalid = $r['invalid'];
                        $service_id = $r['service_id'];
                    }else{
                        //如果是到了质检的这一条，取出归档和无效，可以停掉了
                        if($r['reply_id'] == $w['reply_id']){
                            $archive_clock = $r['archive_clock'];
                            $invalid = $r['invalid'];
                            $service_id = $r['service_id'];
                            break;
                        }
                    }
                    if($r['from_client'] == 1){
                        $ftime = $r['reply_clock'];
                    }
                }
            }
            $ctype = 1;
            if(isset($w['cids']) && $w['type'] == 2){
                $csql = "select * from bay_qcheckcate WHERE id in ({$w['cids']}) AND del = 0 AND `type` = 2";
                $crel = $this->db->query($csql)->result_array();
                if($crel){
                    foreach($crel as $r){
                        if($r['major'] == 1){
                            $major++;
                        }elseif($r['major'] == 2){
                            $not_major++;
                        }
                        $_cids[] = $r['id'];
                    }
                    $ctype = 2;
                }
            }
            $data = array(
                "type" => 2,
                "tid" => $w['reply_id'],
                "major" => $major,
                "not_major" => $not_major,
                "cids" => implode(',',$_cids),
                "ftime" => $ftime,
                "archive_clock" => $archive_clock,
                "remark" => isset($w['remark']) ? $w['remark'] : '',
                'cuid' => $service_id,
                'qtime' => time(),
                'uid' => $this->_uid,
                'invalid' => $invalid,
                'ctype' => $ctype
            );
            //开启事务
            $this->odb->trans_start();
            $irel = $this->db->set($data)->insert("qcheckinfo");
            if(!$irel) return "质检提交失败，清重试~";
            $usql = "update t_advise_replies set qcheck = 1 WHERE reply_id = '{$w['reply_id']}'";
            $this->odb->query($usql);
            $this->odb->trans_complete();
            if ($this->db->trans_status() === FALSE){//失败
                return "保存失败，请重试";
            }else{//成功
                return true;
            }
        }else{
            $ret = "没有这么一个会话ID:{$w['session_id']}";
        }
        return $ret;
    }

    //质检一条举报
    public function checkReport($val,$field = array()){
        $w = $this->_getValue($field,$val);
        //先把会话取出来
        $sql = "select * from t_report WHERE id = '{$w['id']}'";
        $rel = $this->odb->query($sql)->row_array();
        if($rel){
            $major = 0;
            $not_major = 0;
            $_cids = array();
            $ctype = 1;
            if(isset($w['cids']) && $w['type'] == 2){
                $csql = "select * from bay_qcheckcate WHERE id in ({$w['cids']}) AND del = 0 AND `type` = 2";
                $crel = $this->db->query($csql)->result_array();
                if($crel){
                    foreach($crel as $r){
                        if($r['major'] == 1){
                            $major++;
                        }elseif($r['major'] == 2){
                            $not_major++;
                        }
                        $_cids[] = $r['id'];
                    }
                    $ctype = 2;
                }
            }
            $data = array(
                "type" => 3,
                "tid" => $w['id'],
                "major" => $major,
                "not_major" => $not_major,
                "cids" => implode(',',$_cids),
                "ftime" => $rel['clock'],
                "archive_clock" => $rel['archive_clock'],
                "remark" => isset($w['remark']) ? $w['remark'] : '',
                'cuid' => $rel['service_id'],
                'qtime' => time(),
                'uid' => $this->_uid,
                'invalid' => $rel['invalid'],
                'ctype' => $ctype
            );
            //开启事务
            $this->odb->trans_start();
            $irel = $this->db->set($data)->insert("qcheckinfo");
            if(!$irel) return "质检提交失败，清重试~";
            $usql = "update t_report set qcheck = 1 WHERE id = '{$w['id']}'";
            $this->odb->query($usql);
            $this->odb->trans_complete();
            if ($this->db->trans_status() === FALSE){//失败
                return "保存失败，请重试";
            }else{//成功
                return true;
            }
        }else{
            $ret = "没有这么一个举报ID:{$w['id']}";
        }
        return $ret;
    }

    //根据归档ID获取归档名字
    private function _getArchive($aids){
        $sql = "select * from bay_archive WHERE id in ({$aids})";
        $rel = $this->db->query($sql)->result_array();
        $ret = array();
        if($rel){
            foreach($rel as $r){
                $ret[$r['id']] = $r['title'];
            }
        }
        return $ret;
    }

    //我的质检结果
    public function myQcheck($tid,$val = array(),$field = array()){
        $w = $this->_getValue($field,$val);
        $where = "type = {$tid} and ctype = 2 and ftime >= {$w['st']} and ftime < {$w['ed']} and cuid = {$this->_uid}";
//        $where = "ctype = 2  and cuid = 8039";
        if(isset($w['cid'])){
            $where .= " and find_in_set({$w['cid']},cids)";
        }elseif(isset($w['major'])){
            if($w['major'] ==1){
                $where .= " and major > 0";
            }elseif($w['major'] ==2){
                $where .= " and not_major > 0";
            }
        }
        $limit = $this->getLimit('','','',1);
        $sql = "select * from bay_qcheckinfo WHERE {$where} {$limit}";
        $rel = $this->db->query($sql)->result_array();
        $num = 0;
        $ret = array();
        if($rel){
            $_sql = "select count(*) as total from bay_qcheckinfo WHERE {$where} ";
            $_rel = $this->db->query($_sql)->row_array();
            $num = $_rel['total'];
            $qcate = array();
            foreach($rel as $r){
                if($r['cids']){
                    $tmp = explode(',',$r['cids']);
                    foreach($tmp as $id){
                        if(!in_array($id,$qcate)){
                            $qcate[] = $id;
                        }
                    }
                }
            }
            if($qcate){
                $cids = implode(',',$qcate);
                $csql = "select * from bay_qcheckcate WHERE id in ({$cids})";
                $crel = $this->db->query($csql)->result_array();
                if($crel){
                    foreach($crel as $c){
                        $qcate[$c['id']] = $c['title'];
                    }
                }
                foreach($rel as &$row){
                    $row['c_name'] = fun::reflect($row['cids'],$qcate);
                }
            }
            $ret = $rel;
        }
        return array($ret,$num);
    }

    //根据session_id获取在线对话内容
    public function getOnlineById($id){
        $sql = "select s.*,l.respond_rating,l.attitude_rating,l.experience_rating from t_chat_session s LEFT JOIN t_rating_log l ON s.session_id = l.session_id WHERE s.session_id = '{$id}'";
        $rel = $this->odb->query($sql)->row_array();
        $ret = array();
        if($rel){
            $aids = $rel['archive_category'];
            $archive = $this->_getArchive($aids);
//            var_dump($archive);die;
            $csql = "select * from t_chat_content WHERE session_id = '{$id}'";
            $osql = "select * from bay_order WHERE session_id = '{$id}'";
            $crel = $this->odb->query($csql)->result_array();
            $orel = $this->db->query($osql)->result_array();
            $content = array();
            $order = array();
            if($crel){
                foreach($crel as $r){
                    $content[] = $r;
                }
            }
            if($orel){
                foreach($orel as $r){
                    $order[] = $r['id'];
                }
            }
            if($content){
                $rel['detail'] = $content;
            }
            if($order){
                $rel['order_id'] = $order;
            }
            $rel['archive_name'] = implode('',fun::reflect($rel['archive_category'],$archive));
            $ret = $rel;
        }
        return $ret;
    }

    //根据reply_id获取留言内容
    public function getAdviseById($id){
        $sql = "select * from t_advise_replies r WHERE reply_id = {$id}";
//        var_dump($sql);
        $rel = $this->odb->query($sql)->row_array();
        $ret = array();
        $num = 0;
        if($rel){
            $aid = $rel['archive_category'];
            $archive = $this->_getArchive($aid);
            $csql = "select a.content,a.clock as ftime,pic,a.client_id,r.* from t_advise a INNER JOIN t_advise_replies r ON a.id = r.advise_id  WHERE id = {$rel['advise_id']} ORDER BY r.reply_id ASC ";
            $asql = "select a.* from t_advise a WHERE  id = {$rel['advise_id']} ";
            $crel = $this->odb->query($csql)->result_array();
            $arel = $this->odb->query($asql)->row_array();
            if($arel){
                $ret = $arel;
            }
            if($crel){
                foreach($crel as $r){
                    //第一句
//                    var_dump($r);die;
                    if(!isset($ret['detail'])){
                        $ret['detail'][] = array(
                            'reply' => $r['content'],
                            'reply_clock' => $r['ftime'],
                            'client_id' => $r['client_id'],
                            'service_id' => $r['service_id'],
                            "from_client" => 1,
                            "pic" => $ret['pic']
                        );
                    }
                    $ret['detail'][] = $r;
                }
            }
            $ret['check_id'] = $id;
            $ret['archive_name'] = fun::reflect($rel['archive_category'],$archive);
        }
        return $ret;
    }

    //根据举报id获取举报内容
    public function getReportById($id){
        $sql = "select * from t_report WHERE id = {$id}";
        $ret = $this->odb->query($sql)->row_array();
        if($ret){
            if($ret['archive_category']){
                $archive = $this->_getArchive($ret['archive_category']);
                $ret['archive_name'] = implode('',fun::reflect($ret['archive_category'],$archive));
            }
        }
        return $ret;
    }

    //质检申述
    public function appealQcheck($id,$content){
        $rel = $this->db->where('id',$id)->get('qcheckinfo')->row_array();
        if($rel && $rel['cuid'] == $this->_uid){
            $data = array(
                'appeal' => 1,
                'a_time' => time(),
                'a_content' => $content
            );
            $ret = $this->db->set($data)->where('id',$id)->update("qcheckinfo");
            if($ret){
                fun::RTX("质检申述审核","你有一单质检申述要审核",$rel['uid']);
                return true;
            }
        }
        return false;
    }


    //质检成绩
    public function checkResult($val,$filed = array()){
        $w = $this->_getValue($filed,$val);
        $where = " archive_clock >= {$w['st']} and archive_clock < {$w['ed']} ";
        if(isset($w['cuid'])){
            $where .= " and cuid in ({$w['uid']})";
        }
        $sql = "select * from bay_qcheckinfo WHERE {$where}";
        $rel = $this->db->query($sql)->result_array();
        $ret = array();
        $data = array();
        if($rel){
            foreach($rel as $row){
                //总数
                if(!isset($data[$row['cuid']]['total'])){
                    $data[$row['cuid']]['total'] = 1;
                }else{
                    $data[$row['cuid']]['total']++;
                }

                if($row['ctype'] == 2 && !$row['r_status']){//有错
                    if(!isset($data[$row['cuid']]['wrong_num'])){
                        $data[$row['cuid']]['wrong_num'] = 1;
                    }else{
                        $data[$row['cuid']]['wrong_num']++;
                    }
                    //关键数
                    if(!isset($data[$row['cuid']]['major'])){
                        if($row['major']){
                            $data[$row['cuid']]['major'] = $row['major'];
                            $data[$row['cuid']]['major_num'] = 1;
                        }else{
                            $data[$row['cuid']]['major'] = 0;
                            $data[$row['cuid']]['major_num'] = 0;
                        }
                    }else{
                        if($row['major']){
                            $data[$row['cuid']]['major'] += $row['major'];
                            $data[$row['cuid']]['major_num']++;
                        }
                    }
                    //非关键数
                    if(!isset($data[$row['cuid']]['not_major'])){
                        if($row['not_major']){
                            $data[$row['cuid']]['not_major'] = $row['not_major'];
                            $data[$row['cuid']]['not_major_num'] = 1;
                        }else{
                            $data[$row['cuid']]['not_major'] = 0;
                            $data[$row['cuid']]['not_major_num'] = 0;
                        }
                    }else{
                        if($row['not_major']){
                            $data[$row['cuid']]['not_major'] += $row['not_major'];
                            $data[$row['cuid']]['not_major_num']++;
                        }
                    }

                }else{//没错
                    if(!isset($data[$row['cuid']]['wrong_num'])){
                        $data[$row['cuid']]['good_num'] = 1;
                    }else{
                        $data[$row['cuid']]['good_num']++;
                    }
                }
            }
            $total = 0;
            $wrong_num = 0;
            $major = 0;
            $major_num = 0;
            $not_major = 0;
            $not_major_num = 0;
            foreach($data as $uid => $row){
                $total = $total + $row['total'];
                $ret[$uid] = array(
                    'total' => $row['total'],
                    'major' => isset($row['major']) ? $row['major'] : 0,
                    'not_major' => isset($row['not_major']) ? $row['not_major'] : 0,
                    'uid' => $uid
                );
                if(isset($row['wrong_num'])){
                    $wrong_num = $wrong_num + $row['wrong_num'];
                    $ret[$uid]['wrong_num'] = $row['wrong_num'];
                    $ret[$uid]['wrong_rate'] = round($row['wrong_num'] / $row['total'],4) * 100 ;
                }else{
                    $ret[$uid]['wrong_num'] = 0;
                    $ret[$uid]['wrong_rate'] = 0;
                }
                if(isset($row['major'])){
                    $major += $row['major'];
                    $major_num += $row['major_num'];
                    $ret[$uid]['major_rate'] = round(($row['total'] - $row['major_num']) / $row['total'],4) * 100;
                }else{
                    $ret[$uid]['major_rate'] = 100;
                }
                if(isset($row['not_major'])){
                    $not_major += $row['not_major'];
                    $not_major_num += $row['not_major_num'];
                    $ret[$uid]['not_major_rate'] = round(($row['total'] - $row['not_major_num']) / $row['total'],4) * 100;
                }else{
                    $ret[$uid]['not_major_rate'] = 100;
                }
            }
            $ret['total'] = array(
                'uid' => 'total',
                'total' => $total,
                'major' => $major,
                'not_major' => $not_major,
                'wrong_num' => $wrong_num,
                'wrong_rate' => $wrong_num ? round($wrong_num / $total,2) * 100 : 100,
                "major_rate" => $major_num ? round(($total - $major_num) / $total,2) * 100 : 100,
                "not_major_rate" => $not_major_num ? round(($total - $not_major_num) / $total,4) * 100 : 100,
            );
        }
        return array_values($ret);
    }

    //质检详情
    public function resultDetail($st,$ed,$uid,$type){
        $sql = "select * from bay_qcheckinfo WHERE `archive_clock` >= {$st} AND `archive_clock` < {$ed} AND `cuid` = {$uid} AND type = {$type}";
        $rel = $this->db->query($sql)->result_array();
        $ret = array();
        if($rel){
            $crel = $this->db->where('del',0)->get("qcheckcate")->result_array();
            $cate = array();
            if($crel){
                foreach($crel as $r){
                    $cate[$r['id']] = $r;
                }
            }
            $total = 0;
            $wrong_num = 0;
            $major = 0;
            $major_num = 0;
            $not_major = 0;
            $not_major_num = 0;
            $chat = array();
            foreach($rel as $row){
                $total++;
                if($row['ctype'] == 2 && !$row['r_status']) $wrong_num++;
                if($row['major'] && !$row['r_status']){
                    $major += $row['major'];
                    $major_num++;
                }
                if($row['not_major'] && !$row['r_status']){
                    $not_major += $row['not_major'];
                    $not_major_num++;
                }
                if($row['cids']  && !$row['r_status']){
                    $tmp = explode(',',$row['cids']);
                    foreach($tmp as $r){
                        if(isset($cate[$r])){
                            if(!isset($cate[$r])){
                                $cate[$r]['num'] = 1;
                            }else{
                                $cate[$r]['num'] ++;
                            }
                            if(!isset($chat[$cate[$r]['fid']])){
                                $chat[$cate[$r]['fid']] = 1;
                            }else{
                                $chat[$cate[$r]['fid']]++;
                            }
                        }
                    }
                }
            }
            $ret['data'] = array(
                'uid' => $uid,
                'total' => $total,
                'major' => $major,
                'not_major' => $not_major,
                'wrong_num' => $wrong_num,
                'wrong_rate' => round($wrong_num/$total,4)*100
            );
            $ret['cate'] = array_values($cate);
            $ret['chat'] = array();

//            series : [
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

            if($chat){
                $pie = array();
                foreach($chat as $id => $r){
                    $pie[] = array(
                        'value' => $r,
                        'name' => $cate[$id]['title']
                    );
                }
                $ret['series'] = array(
                    array(
                        'name' => "大饼",
                        'type' => "pie",
                        'data' => $pie
                    )
                );
            }

        }
        return $ret;
    }

    //详情列表
    public function resultList($st,$ed,$uid,$type,$cid){
        $limit = $this->getLimit('','','',1);
        $sql = "select * from bay_qcheckinfo WHERE `archive_clock` >= {$st} AND `archive_clock` < {$ed} AND `cuid` = {$uid} AND type = {$type} AND find_in_set({$cid},cids) {$limit}";
        $rel = $this->db->query($sql)->result_array();
        $num = 0;
        if($rel){
            $_sql = "select count(*) as total from bay_qcheckinfo WHERE `archive_clock` >= {$st} AND `archive_clock` < {$ed} AND `cuid` = {$uid} AND type = {$type} AND find_in_set({$cid},cids)";
            $_rel = $this->db->query($_sql)->row_array();
            $num = $_rel['total'];
            $qcate = array();
            foreach($rel as &$r){
                if($type == 1){
                    $r['detail'] = $this->getOnlineById($r['tid']);
                }elseif($type == 2){
                    $r['detail'] = $this->getAdviseById($r['tid']);
                }elseif($type == 3){
                    $r['detail'] = $this->getReportById($r['tid']);
                }
                if($r['cids']){
                    $tmp = explode(',',$r['cids']);
                    foreach($tmp as $id){
                        if(!in_array($id,$qcate)){
                            $qcate[] = $id;
                        }
                    }
                }
            }
            if($qcate){
                $cids = implode(',',$qcate);
                $csql = "select * from bay_qcheckcate WHERE id in ({$cids})";
                $crel = $this->db->query($csql)->result_array();
                if($crel){
                    foreach($crel as $c){
                        $qcate[$c['id']] = $c['title'];
                    }
                }
                foreach($rel as &$row){
                    $row['c_name'] = fun::reflect($row['cids'],$qcate);
                }
            }
        }
        return array($rel,$num);
    }

    //申述列表
    public function appealList($st,$ed){
        list($checker,$leader) = $this->getCheckUid();
        $limit = $this->getLimit('','','',1);
        //查看有没有权限
        if(in_array($this->_uid,$leader)) {//质检组长
            $sql = "select * from bay_qcheckinfo WHERE a_time >= {$st} and a_time < {$ed} and appeal in (2,4,5) ORDER BY  appeal {$limit}";
            $_sql = "select count(*) as total from bay_qcheckinfo WHERE a_time >= {$st} and a_time < {$ed} and  appeal in (2,4,5)";
        }elseif(in_array($this->_uid,$checker)){//质检员
            $sql = "select * from bay_qcheckinfo WHERE a_time >= {$st} and a_time < {$ed} and  appeal in (1,2,3) ORDER BY  appeal {$limit}";
            $_sql = "select count(*) as total from bay_qcheckinfo WHERE a_time >= {$st} and a_time < {$ed} and  appeal in (1,2,3)";
        }else{//有权限查看的人
            $sql = "select * from bay_qcheckinfo WHERE a_time >= {$st} and a_time < {$ed} and  appeal > 0 ORDER BY  appeal {$limit}";
            $_sql = "select count(*) as total from bay_qcheckinfo WHERE a_time >= {$st} and a_time < {$ed} and  appeal > 0";
        }
        $rel = $this->db->query($sql)->result_array();
        $num = 0;
        if($rel){
            $_rel = $this->db->query($_sql)->row_array();
            $num = $_rel['total'];
        }
        return array($rel,$num);
    }

    /**
     * 初审一条
     * @param $id
     * @param string $content
     * @param int $type
     * @return bool|string
     * @date
     */
    public function checkFirst($id,$content = '',$type = 1){
        $where = array(
            'id' => $id,
            'appeal' => 1
        );
        $rel = $this->db->where($where)->get("qcheckinfo")->row_array();
        if($rel){
            //权限判断
            list($checker,$leader) = $this->getCheckUid();
            if($rel['uid'] != $this->_uid && in_array($this->_uid,$checker)) return "你没权限审核不属于你的质检";
            $data = array(
                'fc_time' => time(),
                'fc_content' => $content,
                'fc_uid' => $this->_uid
            );
            if($type == 1){//初审通过
                $data['appeal'] = 2;
            }else{//拒绝
                $data['appeal'] = 3;
            }
            $ret = $this->db->set($data)->where('id',$id)->update("qcheckinfo");
            if($ret){
                if($type == 1){//通过
                    fun::RTX('你有一条质检要复审','你有一条质检要复审',implode(',',$leader));
                }else{
                    fun::RTX('你的质检申述已经被拒绝','你的质检申述已经被拒绝',$rel['cuid']);
                }
                return true;
            }else{
                return false;
            }
        }else{
            return "没有这么一条要初审的质检";
        }
    }


    /**
     * 复审一条
     * @param $id
     * @param string $content
     * @param int $type
     * @param string $ptype
     * @return bool|string
     * @date
     */
    public function checkSecond($id,$content = '',$type = 1,$ptype = ''){
        $where = array(
            'id' => $id,
            'appeal' => 2
        );
        $rel = $this->db->where($where)->get("qcheckinfo")->row_array();
        if($rel){
            //权限判断
            list($checker,$leader) = $this->getCheckUid();
            if($rel['uid'] != $this->_uid && in_array($this->_uid,$leader)) return "你没权限审核不属于你的质检";
            $data = array(
                'sc_time' => time(),
                'sc_content' => $content,
                'sc_uid' => $this->_uid
            );
            if($type == 1){//复审通过
                $data['appeal'] = 4;
                $data['r_status'] = $ptype;
            }else{//拒绝
                $data['appeal'] = 5;
            }
            $ret = $this->db->set($data)->where('id',$id)->update("qcheckinfo");
            if($ret){
                if($type == 1){//通过
                    fun::RTX('你的质检申述已经被通过','你的质检申述已经被通过',$rel['cuid']);
                }else{
                    fun::RTX('你的质检申述已经被拒绝','你的质检申述已经被拒绝',$rel['cuid']);
                }
                return true;
            }else{
                return false;
            }
        }else{
            return "没有这么一条要复审的质检";
        }
    }

    //质检统计
    public function countList($val,$filed = array()){
        $w = $this->_getValue($filed,$val);
        $where = " qtime >= {$w['st']} and qtime < {$w['ed']} ";
        if(isset($w['uid'])){
            $where .= " and uid in ({$w['uid']})";
        }
        $sql = "select * from bay_qcheckinfo WHERE {$where}";
        $rel = $this->db->query($sql)->result_array();
        $ret = array();
        $data = array();
        if($rel){
            foreach($rel as $row){
                //总数
                if(!isset($data[$row['uid']]['total'])){
                    $data[$row['uid']]['total'] = 1;
                }else{
                    $data[$row['uid']]['total']++;
                }
                //无效归档
                if($row['invalid']){
                    if(!isset($data[$row['uid']]['invalid'])){
                        $data[$row['uid']]['invalid'] = 1;
                    }else{
                        $data[$row['uid']]['invalid']++;
                    }
                }else{//正常归档
                    if(!isset($data[$row['uid']]['normal'])){
                        $data[$row['uid']]['normal'] = 1;
                    }else{
                        $data[$row['uid']]['normal']++;
                    }
                }

                if($row['r_status']){//申述通过
                    //申述条数
                    if(!isset($data[$row['uid']]['appeal_num'])){
                        $data[$row['uid']]['appeal_num'] = 1;
                    }else{
                        $data[$row['uid']]['appeal_num']++;
                    }
                    if($row['r_status'] == 1){//普通通过(质检出错条数)
                        if(!isset($data[$row['uid']]['bad_num'])){
                            $data[$row['uid']]['bad_num'] = 1;
                        }else{
                            $data[$row['uid']]['bad_num']++;
                        }
                    }
                }else{//质检没错(没申述或者申述不成功)
                    if($row['ctype'] == 2){//质检的人有错
                        if(!isset($data[$row['uid']]['wrong_num'])){
                            $data[$row['uid']]['wrong_num'] = 1;
                        }else{
                            $data[$row['uid']]['wrong_num']++;
                        }
                    }
                    //关键数
                    if(!isset($data[$row['uid']]['major'])){
                        if($row['major']){
                            $data[$row['uid']]['major'] = $row['major'];
                            $data[$row['uid']]['major_num'] = 1;
                        }else{
                            $data[$row['uid']]['major'] = 0;
                            $data[$row['uid']]['major_num'] = 0;
                        }
                    }else{
                        if($row['major']){
                            $data[$row['uid']]['major'] += $row['major'];
                            $data[$row['uid']]['major_num']++;
                        }
                    }
                    //非关键数
                    if(!isset($data[$row['uid']]['not_major'])){
                        if($row['not_major']){
                            $data[$row['uid']]['not_major'] = $row['not_major'];
                            $data[$row['uid']]['not_major_num'] = 1;
                        }else{
                            $data[$row['uid']]['not_major'] = 0;
                            $data[$row['uid']]['not_major_num'] = 0;
                        }
                    }else{
                        if($row['not_major']){
                            $data[$row['uid']]['not_major'] += $row['not_major'];
                            $data[$row['uid']]['not_major_num']++;
                        }
                    }
                }
            }
            $total = 0;
            $normal = 0;
            $invalid = 0;
            $wrong_num = 0;
            $bad_num = 0;
            $appeal_num = 0;
            $major = 0;
            $not_major = 0;
            foreach($data as $uid => $row){
                $total = $total + $row['total'];
                $ret[$uid] = array(
                    'total' => $row['total'],
                    'uid' => $uid
                );
                if(isset($row['normal'])){
                    $ret[$uid]['normal'] = $row['normal'];
                    $normal += $row['normal'];
                }else{
                    $ret[$uid]['normal'] = 0;
                }
                if(isset($row['invalid'])){
                    $ret[$uid]['invalid'] = $row['invalid'];
                    $invalid += $row['invalid'];
                }else{
                    $ret[$uid]['invalid'] = 0;
                }
                if(isset($row['major'])){
                    $ret[$uid]['major'] = $row['major'];
                    $major += $row['major'];
                }else{
                    $ret[$uid]['major'] = 0;
                }
                if(isset($row['not_major'])){
                    $ret[$uid]['not_major'] = $row['not_major'];
                    $not_major += $row['not_major'];
                }else{
                    $ret[$uid]['not_major'] = 0;
                }
                if(isset($row['wrong_num'])){
                    $ret[$uid]['wrong_num'] = $row['wrong_num'];
                    $wrong_num += $row['wrong_num'];
                }else{
                    $ret[$uid]['wrong_num'] = 0;
                }
                if(isset($row['appeal_num'])){
                    $ret[$uid]['appeal_num'] = $row['appeal_num'];
                    $appeal_num += $row['appeal_num'];
                }else{
                    $ret[$uid]['appeal_num'] = 0;
                }
                if(isset($row['bad_num'])){
                    $ret[$uid]['accurate'] = round($row['bad_num'] / $row['total'],4) * 100;
                    $bad_num++;
                }else{
                    $ret[$uid]['accurate'] = 100;
                }
            }
            $ret['total'] = array(
                'uid' => 'total',
                'total' => $total,
                'invalid' => $invalid,
                'normal' => $normal,
                'major' => $major,
                'not_major' => $not_major,
                'wrong_num' => $wrong_num,
                'appeal_num' => $appeal_num,
                'accurate' => $bad_num ? round($bad_num / $total,4) * 100 : 100,
            );
        }
        return array_values($ret);
    }

    //质检统计详细列表
    public function countShow($st,$ed,$uid,$type,$w){
        $limit = $this->getLimit('','','',1);
        switch($w){
            case 'normal':
                $sql = "select * from bay_qcheckinfo WHERE `qtime` >= {$st} AND `qtime` < {$ed} AND `uid` = {$uid} AND type = {$type} AND invalid = 0 {$limit}";
                $_sql = "select count(*) as total from bay_qcheckinfo WHERE `qtime` >= {$st} AND `qtime` < {$ed} AND `uid` = {$uid} AND type = {$type} AND invalid = 0 ";
                break;
            case 'invalid':
                $sql = "select * from bay_qcheckinfo WHERE `qtime` >= {$st} AND `qtime` < {$ed} AND `uid` = {$uid} AND type = {$type} AND invalid = 1 {$limit}";
                $_sql = "select  count(*) as total  from bay_qcheckinfo WHERE `qtime` >= {$st} AND `qtime` < {$ed} AND `uid` = {$uid} AND type = {$type} AND invalid = 1 ";
                break;
            case 'major':
                $sql = "select * from bay_qcheckinfo WHERE `qtime` >= {$st} AND `qtime` < {$ed} AND `uid` = {$uid} AND type = {$type} AND major > 0  and r_status = 0  {$limit}";
                $_sql = "select  count(*) as total  from bay_qcheckinfo WHERE `qtime` >= {$st} AND `qtime` < {$ed} AND `uid` = {$uid} AND type = {$type} AND major > 0  and r_status = 0 ";
                break;
            case 'not_major':
                $sql = "select * from bay_qcheckinfo WHERE `qtime` >= {$st} AND `qtime` < {$ed} AND `uid` = {$uid} AND type = {$type} AND not_major > 0  and r_status = 0  {$limit}";
                $_sql = "select  count(*) as total  from bay_qcheckinfo WHERE `qtime` >= {$st} AND `qtime` < {$ed} AND `uid` = {$uid} AND type = {$type} AND not_major > 0  and r_status = 0";
                break;
            case 'wrong':
                $sql = "select * from bay_qcheckinfo WHERE `qtime` >= {$st} AND `qtime` < {$ed} AND `uid` = {$uid} AND type = {$type} AND ctype = 2  and r_status = 0  {$limit}";
                $_sql = "select  count(*) as total  from bay_qcheckinfo WHERE `qtime` >= {$st} AND `qtime` < {$ed} AND `uid` = {$uid} AND type = {$type} AND ctype = 2  and r_status = 0";
                break;
            case 'appeal':
                $sql = "select * from bay_qcheckinfo WHERE `qtime` >= {$st} AND `qtime` < {$ed} AND `uid` = {$uid} AND type = {$type} AND  r_status > 0  {$limit}";
                $_sql = "select  count(*) as total  from bay_qcheckinfo WHERE `qtime` >= {$st} AND `qtime` < {$ed} AND `uid` = {$uid} AND type = {$type} AND  r_status > 0";
                break;

        }
        if(!isset($sql)) return array("类型条件错误！");
        $rel = $this->db->query($sql)->result_array();
        $num = 0;
        if($rel){
            $_rel = $this->db->query($_sql)->row_array();
            $num = $_rel['total'];
            foreach($rel as &$r){
                if($type == 1){
                    $r['detail'] = $this->getOnlineById($r['tid']);
                }elseif($type == 2){
                    $r['detail'] = $this->getAdviseById($r['tid']);
                }elseif($type == 3){
                    $r['detail'] = $this->getReportById($r['tid']);
                }
            }
        }
        return array($rel,$num);
    }
}