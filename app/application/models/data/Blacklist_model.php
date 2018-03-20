<?php
/**
 * 黑名单 model
 * Class Blacklist_model
 * Created by PhpStorm.
 * User: HarveyYang
 * Date: 2017/10/25
 * Time: 17:40
 */


class Blacklist_model extends Bmax_Model {
    private $odb;
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->odb = $this->load->database('online',true);

    }

    /**
     * 黑名单列表
     * @param array $filed
     * @param array $val
     * @return array
     * @date
     */
    public function blacklist($filed = array(),$val = array()){
        $w = $this->_getValue($filed,$val);
        if(isset($w['st'])){
            $this->odb->where("addtime >= {$w['st']}");
            if(isset($w['ed'])){
                $this->odb->where("addtime < {$w['ed']}");
            }
        }
        if(isset($w['gid'])){
            $this->odb->where("gid",$w['gid']);
            if(isset($w['sid'])){
                $this->odb->where("sid",$w['sid']);
            }
        }
        if(isset($w['mid'])){
            $this->odb->where("mid",$w['mid']);
        }
        $this->odb->from("kf_blacklist");
        $num = $this->odb->count_all_results('',false);
        $this->getLimit($this->odb);
        $rel = $this->odb->order_by("id desc")->get()->result_array();
        return array($rel,$num);

    }

    /**
     * 新加一条黑名单
     * @param $val
     * @param $days
     * @return mixed
     * @date
     */
    public function addList($val,$days){
        $w = $this->_getValue(array(),$val,$this->odb);
        $time = strtotime("+ {$days}");
        $data = array(
            'adduid' => $this->_uid,
            'addtime' => time(),
            'frozentime' => $time
        );
        $ret = $this->odb->set($data)->insert("kf_blacklist");
        return $ret;
    }

    /**
     * 解封黑名单
     * @param array $val
     * @param array $field
     * @return mixed
     * @date
     */
    public function delList($val =array(),$field = array()){
        $w = $this->_getValue($field,$val);
        $data = array(
            'deluid' => $this->_uid,
            'deltime' => time(),
            'del' => 1
        );
        $ret = $this->odb->where_in("id",explode(',',$w['id']))->set($data)->update("kf_blacklist");
        return $ret;
    }
}