<?php
/**
 * 示忙model
 * class ShowBusy_model
 * Created by PhpStorm.
 * User: HarveyYang
 * Date: 2017/9/6
 * Time: 10:33
 */

class ShowBusy_model extends Bmax_Model {
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /**
     * 切换客服状态
     * @param $val
     * @return mixed
     * @date
     */
    public  function changeStatus($val){
        $w = $this->_getValue(array(),$val);
        $d = array(
            'uid' => $w['uid'],
            'status' => $w['status'],
            'time' => time()
        );
        $ret = $this->db->set($d)->insert('statuslog');
        return $ret;
    }

    /**
     * 示忙列表
     * @param array $field
     * @param array $val
     * @return array
     * @date
     */
    public function statusList($field = array(),$val = array()){
        $w = $this->_getValue($field,$val);
        $where = '1';
        if(isset($w['st'])){
            $where .= " and time >= {$w['st']}";
            if(isset($w['st'])){
                $where .= " and time < {$w['ed']}";
            }
        }
        $uid = array();
        if(isset($w['uid'])){
            $uid = explode(',',$w['uid']);

        }elseif(isset($w['set_id'])){
            $t = explode(',',$w['set_id']);
            if($t){
                foreach($t as $s){
                    $sql = "select uid from bay_users WHERE find_in_set({$s},set_id)";
                    $rel = $this->db->query($sql)->result_array();
                    if($rel){
                        foreach($rel as $r){
                            if(!in_array($r['uid'],$uid)){
                                $uid[] = $r['uid'];
                            }
                        }
                    }
                }
            }
        }
        if($uid){
            $str = implode(',',$uid);
            $where .= " and uid in({$str})";
        }
//        $limit = $this->getLimit('','','',1);
        $sql = "select * from bay_statuslog WHERE {$where} ORDER BY uid,`time`";
//        var_dump($sql);die;
        $rel = $this->db->query($sql)->result_array();
//        var_dump($rel);die;
        $ret = array();
        $detail = array();
        if($rel){
//        var_dump($ret);die;
            $lock = 0;
            $t1 = 0;
            $t2 = 0;
            $data = array();
            foreach($rel as $key => $row){
                if($row['status'] == 0){//开始示忙
                    if(!$lock || $lock != $row['uid']){//上次的示忙时间统计完成，或者是下一个客服了，才记录下一个是忙时间
                        $t1 = $row['time'];
                        $lock = $row['uid'];
                    }
                    continue;
                }
                //示忙后的第一次接入状态
                if($lock == $row['uid'] && $row['status'] != 0 && $row['status'] != -2){
                    $t2 = $row['time'] - $t1;
                    if($t2 < (3600*8)){//如果示忙时间小于8小时，才计算（防止错误计算）
                        $last = $row['time'] - $t1;
                        $data[$lock][] = $last;
                        $detail[$lock][] = array(
                            'start' => $t1,
                            'end' => $row['time'],
                            'last' => fun::changTime($last),
                        );
                        $lock = 0;
                    }else{
                        $lock = 0;//超过8小时的，清空这次的是忙计算，开始计算下一次的
                    }
                }
            }
//        var_dump($data);die;
            list($start,$size) = $this->getLimit();
            $total = count($data);
            $count = 0;
            foreach($data as $key => $row){
                $time = 0;
                $num = 0;
                foreach($row as $v){
                    $time = $time + $v;
                    $num++;
                }
                if($count >=  $start && $count < ($start + $size)){
                    $ret[] = array(
                        'uid' => $key,
                        'set_name' => fun::getGameGroup($key,'name'),
                        'time' => fun::changTime($time),
                        'num' =>$num,
                        'detail' => isset($detail[$key]) ? $detail[$key] : array()
                    );
                }
                $count ++;
            }
        }
        return array($ret,$total);
    }
}