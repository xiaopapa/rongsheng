<?php
/**
 * Class Order_model
 * Created by PhpStorm.
 * User: HarveyYang
 * Date: 2017/9/20
 * Time: 12:10
 */


class Order_model extends Bmax_Model {
    //工单超级管理员再这里设置
    private $superman = array(
        '1985'
    );

    public function __construct(){
        parent::__construct();
        $this->load->database();
    }

    /**
     * 订单列表
     * @param array $field
     * @param array $val
     * @param bool|true $limit
     * @return array
     * @date
     */
    public function getOrder($field = array(),$val = array(),$limit = true){
        $w = $this->_getValue($field,$val);
//        $field = "cst,ced,gid,sid,aid,mid,status,id,atuid";
        $where = "1";
        if(isset($w['cst'])){
            $where .= " and addtime >= {$w['cst']}";
            if(isset($w['ced'])){
                $where .= " and addtime < {$w['ced']}";
            }
        }
        if(isset($w['dst'])){
            $where .= " and etime >= {$w['dst']}";
            if(isset($w['ced'])){
                $where .= " and etime < {$w['ded']}";
            }
        }
        if(isset($w['gid'])){
            $where .= " and gid = {$w['gid']}";
            if(isset($w['sid'])){
                $where .= " and sid in ({$w['sid']})";
            }
        }
        if(isset($w['aid'])){
            $where .= " and aid = {$w['aid']}";
        }
        if(isset($w['mid'])){
            $where .= " and mid = {$w['mid']}";
        }
        if(isset($w['first'])){
            $where .= " and status = 0";
        }elseif(isset($w['status'])){
            $where .= " and status = {$w['status']}";
        }
        if(isset($w['id'])){
            $where .= " and id = {$w['id']}";
        }
        if(isset($w['source'])){
            $where .= " and source = {$w['source']}";
        }

        if(isset($w['my'])){//我的工单
            $where .= " and uids like '%,{$this->_uid},%'";
        }
        if(isset($w['md'])){
            $where .= " and atuid = {$this->_uid} and status in (1,3)";
        }elseif(isset($w['atuid'])){
            $where .= " and atuid = {$w['atuid']}";
        }
        if(isset($w['sort'])){
            $sort = "addtime desc";
        }else{
            $sort = "addtime desc";
        }
        if($where !== '1'){
            $this->db->where($where);
        }
        $this->db->from('order');
        if($limit){//分页
            $num = $this->db->count_all_results('',false);
            $this->getLimit($this->db);
        }else{
            $num = 0;
        }
        $ret = $this->db->order_by($sort)->get()->result_array();
        //获取详情
        if($ret){
            $id = array();
            $aid = array();
            $aids = array();
            foreach($ret as $row){
                $id[] = $row['id'];
                if(!in_array($row['aid'],$aid)) $aid[] = $row['aid'];
            }
            if($aid){
                $tmp = implode(',',$aid);
                $sql = "select * from bay_archive where id in ({$tmp})";
                $arel = $this->db->query($sql)->result_array();
                if($arel){
                    foreach($arel as $r){
                        $aids[$r['id']] = $r['title'];
                    }
                }
            }
            if($id){
                $_rel = $this->db->where_in('fid',$id)->order_by('id')->get('order_list')->result_array();
                if($_rel){
                    $data = array();
                    foreach($_rel as $r){
                        $data[$r['fid']][] = $r;
                    }
                    foreach($ret as &$row){
                        $row['aname'] = implode(',',fun::reflect($row['aid'],$aids));
                        if(isset($data[$row['id']])) $row['detail'] = $data[$row['id']];
                    }
                }
            }

        }
        return array($ret,$num);
    }

    /**
     * 新建工单
     * @param array $field
     * @param array $val
     * @return mixed
     * @date
     */
    public function newOrder($field = array(),$val = array()){
        $w = $this->_getValue($field,$val,$this->db);
        if(isset($w['file'])){
            $this->db->set('file',stripslashes($w['file']));
        }
        if(isset($w['content'])){
            $this->db->set('content',stripslashes($w['content']));
        }
        $d = array(
            'adduid' => $this->_uid,
            'addtime' => time(),
            'uids' => ',' . $this->_uid . ','
        );
        $ret = $this->db->set($d)->insert("order");
        return $ret;
    }

    /**
     * 工单处理
     * @param array $field
     * @param array $val
     * @return bool|string
     * @date
     */
    public function dealOrder($field = array(),$val = array()){
        $w = $this->_getValue($field,$val);
        $time = time();
        $end = array(3,4,5,6);
        //权限
        $rel = $this->db->where("id",$w['id'])->get("order")->row_array();
        if($rel){
            if($rel['atuid'] != $this->_uid && !in_array($this->_uid,$this->superman) ){
                return "仅当前处理者及有特殊权限的人才可以处理工单！";
            }

            $d = array(
                'fid' => $w['id'],
                'typeid' => $w['deal'],
                'content' => isset($w['content']) ? stripslashes($w['content']) : '',
                'file' => isset($w['file']) ? stripslashes($w['file']) : '',
                'addtime' => time(),
                'uid' => $this->_uid,
                'uid2' => in_array($w['deal'],$end) ? 0 : $w['uid2'],
            );
            //开启事务
            $this->db->trans_start();
            $ret = $this->db->set($d)->insert("order_list");
            if($ret){
//            $db = o::db('order');
                $status = $this->fmtStatus($w['id'], in_array($w['deal'],$end));
                $atuid = in_array($w['deal'],$end) ? $this->_uid : $w['uid2'];
                $d = array(
                    'status' => $status,
                    'atuid' => $atuid,
                    'uids' => fun::appendSome(rtrim($rel['uids'],','),$this->_uid) . ',',
                    'etime' => $time,
                );
                if(isset($w['file'])){
                    $d['file'] = stripslashes($w['file']);
                }
                $ret = $this->db->set($d)->where('id',$w['id'])->update('order');
                if($ret && $atuid != $this->_uid){
                    $order_url = $this->config->item('sys_url') . '/order';
                    $title = "【工单处理】";
                    $content = '您有一条工单需要处理：['. $order_url .'|'. $order_url .']';
                    fun::RTX($title,$content,$atuid);
                }
            }
            $this->db->trans_complete();//事务提交
            if ($this->db->trans_status() === FALSE){
                return "处理失败，请重试";
            }else{//成功
                return true;
            }
        }
        return "没有这个id:{$w['id']}工单";
    }


    /**
     * 认领工单
     * @param $ids
     * @return int
     * @date
     */
    public function claimOrder($ids){
        $_id = explode(',',$ids);
        $time = time();
        $d= array(
            'status' => 1,
            'atuid' => $this->_uid,
            'etime' => $time
        );
        $rel = $this->db->where_in('id',$_id)->where('status',0)->get('order')->result_array();
        $num = 0;
        if($rel){
            foreach($rel as $row){
                $uids = fun::appendSome(rtrim($row['uids'],','),$this->_uid) . ',';
                $this->db->trans_start();//启动
                $r = $this->db->set($d)->set('uids',$uids)->where('id',$row['id'])->update('order');
                if($r){
                    $data = array(
                        'fid' => $row['id'],
                        'typeid' => 1,
                        'addtime' => $time,
                        'uid' => $this->_uid
                    );
                    $rr = $this->db->set($data)->insert('order_list');
                    if($rr){
                        $num ++;
                    }
                }
                $this->db->trans_complete();//提交
            }
        }
        return $num;
    }


    /**
     * 催单
     * @param $id
     * @return bool
     * @date
     */
    public function toFast($id){
        $ids = explode(',',$id);
        $rel = $this->db->where_in('id',$ids)->get('order')->result_array();
        if($rel){
            $uids = array();
            foreach($rel as $row){
                if(!in_array($row['atuid'],$uids)){
                    $uids[] = $row['atuid'];
                }
            }
            $uids = implode(',',$uids);
            $order_url = $this->config->item('client_url') . '/order';
            $title = "【工单处理】";
            $content = '您有一条工单需要处理：['. $order_url .'|'. $order_url .']';
            $ret = fun::RTX($title,$content,$uids);
            return true;
        }
        return false;
    }


    /**
     * 处理工单状态
     * @param $addtime
     * @param $end               bool  当前是否是结单操作
     * @return bool|int
     * @date
     */
    function fmtStatus($addtime, $end){
        //超过添加时间3天(去掉周末时间)
        $w = date("w",$addtime);
        $now = time();
        if(in_array($w,array(3,4,5)) ){//跳过周末
            $over = $now - $addtime > 86400*5;
        }elseif(in_array($w,array(0,6))){//周末的提单时间，超时时间为周4凌晨0点
            if($w == 6){//星期6
                $t = strtotime(date('Y-m-d',strtotime("+ 5 days",$addtime)));
            }else{//星期天
                $t = strtotime(date('Y-m-d',strtotime("+ 4 days",$addtime)));
            }
            $over = $now > $t;
        }else{
            $over =  $now - $addtime >  86400*3;
        }
        if($over){//超时状态
            if($end){//结单已超时
                $status = 4;
            }else{//还未结单
                $status = 3;
            }
        }else{
            if($end){//结单未超时
                $status = 2;
            }else{//还未结单
                $status = 1;
            }
        }
        return $status;
    }
}