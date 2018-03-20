<?php
/**
 * 登录相关类
 * Class SignIn_model
 * Created by PhpStorm.
 * User: HarveyYang
 * Date: 2017/9/5
 * Time: 10:05
 */

class SignIn_model extends Bmax_Model {
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /**
     * 记录退出日志
     * @param $uid
     * @param $plat
     * @return mixed
     * @date
     */
    public function loginOutLog($uid,$plat){
        $w = array(
            'uid' => $uid,
            'plat' => $plat,
            'off' => 0
        );
        $rel = $this->db->where($w)->order_by('id','desc')->get('login_log')->row_array();
        $time = time();
        if($rel){
            $id = $rel['id'];
            $last = $time - $rel['login'];
            $d = array(
                'off' => $time,
                'last' => $last
            );
            $ret = $this->db->where('id',$id)->set($d)->update('login_log');
        }else{
            $d = array(
                'uid' => $uid,
                'plat' => $plat,
                'off' => $time
            );
            $ret = $this->db->set($d)->insert('login_log');
        }
        return $ret;
    }

    //登录日志
    public function LoginLog($field,$plat = 1){
        $w = $this->_getValue($field);
        $where = "plat = {$plat}";
        $uid = array();
        if(isset($w['uid']) || isset($w['name'])){
            if(isset($w['uid'])){
                $uid = explode(',',$w['uid']);
            }else{
                $sql = "select * from bay_users WHERE cname like = '%{$w['name']}%'";
                $rel = $this->db->query($sql)->result_aray();
                if($rel){
                    foreach($rel as $row){
                        $uid[] = $row['uid'];
                    }
                }
            }

        }elseif(isset($w['set_id'])){
            $t = explode(',',$w['set_id']);
            foreach($t as $row){
                $sql = "select * from bay_users WHERE find_in_set({$row},set_id)";
                $rel = $this->db->query($sql)->result_array();
                if($rel){
                    foreach($rel as $r){
                        if(!in_array($r['uid'],$uid)) $uid[] = $r['uid'];
                    }
                }
            }
        }
        if($uid){
            $where .= " and uid in (" . implode(',',$uid) . ")";
        }
        $sort = false;
        if(isset($w['sort'])){
            switch($w['sort']){
                case 'lga':
                    $sort = "order by login asc";
                    $where .= " and login != 0";
                    break;
                case 'lgd':
                    $sort = "order by login desc";
                    break;
                case 'ofa':
                    $sort = "order by off asc";
                    $where .= " and off != 0";
                    break;
                case 'ofd':
                    $sort = "order by off desc";
                    break;
                case 'lta':
                    $sort = "order by last asc";
                    $where .= " and last != 0";
                    break;
                case 'ltd':
                    $sort = "order by last desc";
                    break;
            }
        }
        $limit = $this->getLimit('','','',1);
        $sql = "select * from bay_login_log WHERE {$where} {$sort} {$limit}";
        $ret = $this->db->query($sql)->result_array();
        $num = 0;
        if($ret){
            $_sql = "select count(*) as total from bay_login_log WHERE {$where}";
            $_rel = $this->db->query($_sql)->row_array();
            $num = $_rel['total'];
            foreach($ret as &$row){
                $sids = fun::getUserPer($row['uid'],'game_group');
                if($sids){
                    $row['grp'] = fun::getGameGroup($sids,'name');
                }else{
                    $row['grp'] = array();
                }

            }
        }

        return array($ret,$num);
    }
}