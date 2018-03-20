<?php

/**
 * 统计model
 * Class Robot_model
 * @author harvey
 * @Date: 17-8-16
 * @license  http://www.boyaa.com/
 */
class Statistics_model extends Bmax_Model {
    private $odb;
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->odb = $this->load->database("online",true);

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
            foreach($grps as $set_id => $row){
                $ret[$set_id] = $row['name'];
            }
        }
        return $ret;
    }


    /**
     *标识剔除/恢复一条在线记录
     * @param $val
     * @return mixed
     * @date
     */
    public function delSession($val){
        $ret = $this->odb->set('del',$val['del'])->where('session_id',$val['session_id'])->update('tmp_chat_session');
        return $ret;
    }


    /**
     * 细明查询
     * @param array $val
     * @param array $field
     * @return array
     * @date
     */
    public function getList($val = array(),$field = array()){
        $w = $this->_getValue($field,$val);
        $where = "s.clock >= {$w['st']} and s.clock < {$w['ed']}";
        if(isset($w['npt'])){
            $where .= " and l.respond_rating = 0";
        }else{
            if(isset($w['res'])){
                $where .= " and l.respond_rating < {$w['res']}";
            }
            if(isset($w['exp'])){
                $where .= " and l.respond_rating < {$w['exp']}";
            }
            if(isset($w['att'])){
                $where .= " and l.respond_rating < {$w['att']}";
            }
        }
        if(isset($w['del'])){
            $where .= " and s.del = 1";
        }
        if(isset($w['uid'])){
            $where .= " and s.service_id in ({$w['uid']})";
        }elseif(isset($w['set_id'])){
            $str = '';
            $grp = fun::getGameGroup($w['set_id']);
            if($grp){
                foreach($grp as $r){
                    $tmp = explode(',',$r['sids']);
                    $sids = array();
                    foreach($tmp as $t){
                        $s = explode('.',$t);
                        if(isset($s[0]) && isset($s[1])){
                            $sids[$s[0]][] = $s[1];
                        }
                    }

                    foreach($sids as $gid => $row){
                        $str .= " (s.gid = {$gid} and s.site_id in (" . implode(',',$row) .")) OR";
                    }
                }

            }
            $str = rtrim($str,'OR');
            if(!$str) return array("所选的游戏没有任何站点~",0);
            $where .= " and ({$str})";
        }
        if(isset($w['ord'])){
            $where .= " and s.del = 1";
        }else{
            $order = " ORDER BY s.clock DESC";
        }
        $limit = $this->getLimit('','','',1);
        $sql1 = "select s.*,l.respond_rating,l.attitude_rating,l.experience_rating from tmp_chat_session s LEFT JOIN  tmp_rating_log l ON s.session_id = l.session_id WHERE {$where} {$order} {$limit}";
        $sql2 = "select count(*) as total from tmp_chat_session s LEFT JOIN  tmp_rating_log l ON s.session_id = l.session_id WHERE {$where}";

//        var_dump($sql1);die;
        $count = $this->odb->query($sql2)->row_array();
        $num = $count['total'];
        $rel = $this->odb->query($sql1)->result_array();
//        var_dump($rel);die;
        $ret = array();
        if($rel){
            $s_id = array();
            $uids = array();
            foreach($rel as $row){
                $s_id[] = $row['session_id'];
                if(!in_array($row['service_id'],$uids)) $uids[] = $row['service_id'];
            }
            $s_id = implode("','",$s_id);
            $sql3 = "select * from tmp_chat_content WHERE session_id in ('{$s_id}') ORDER BY id ASC";
            $_rel = $this->odb->query($sql3)->result_array();
            $robot = array();
            $ser = array();
            //聊天记录
            if($_rel){
                foreach($_rel as $row){
                    if($row['service_id'] == '9527'){
                        $robot[$row['session_id']][] = $row;
                    }else{
                        $ser[$row['session_id']][] = $row;
                    }
                }
            }
            //客服游戏组处理
            $u_data = array();
//            var_dump($uids);die;
            foreach($uids as $uid){
                $srel = fun::getUserPer($uid,'game_group');
                if($srel){
                    $u_data[$uid]['set_id'] = explode(',',$srel);
                    $grps = fun::getGameGroup($srel);
                    foreach($grps as $row){
                        $u_data[$uid]['set_name'][] = $row['name'];
                    }
                }
            }
//            var_dump($u_data);die;
            //最后数据处理
            $adata = array();
            foreach($rel as $row){
                //后面加上玩家详细信息
                /*if($row['client_info']){
                    $tmp = json_decode($row['client_info'],true);
                }*/
                $s_num = 0;
                $r_num = 0;
                $start_time = 0;
                $end_time = 0;
                if($ser[$row['session_id']]){
                    $s_num = count($ser[$row['session_id']]);
                    $start_time = $ser[$row['session_id']][0]['clock'];
                    $i = $s_num -1;
                    $end_time = $ser[$row['session_id']][$i]['clock'];
                }
                if($robot[$row['session_id']]){
                    $r_num = count($robot[$row['session_id']]);
                }
                $gname = fun::getGameName($row['gid'],$row['site_id'],1);
                if(isset($gname[$row['gid'] . '-' . $row['site_id']])) $gname = $gname[$row['gid'] . '-' . $row['site_id']];
                if(!in_array($row['archive_category'],$adata)){
                    $adata[] = $row['archive_category'];
                }
                $data = array(
                    'id' => $row['session_id'],
                    'del' => $row['del'],
                    'archive_category' => $row['archive_category'],
                    'archive_class' => $row['archive_class'],
                    'group' => isset($u_data[$row['service_id']]['set_name']) ? $u_data[$row['service_id']]['set_name'] : '',
                    'service_id' => $row['service_id'],
                    'gid' => $row['gid'],
                    'sid' => $row['site_id'],
                    'game' => $gname,
                    'client_id' => $row['client_id'],
                    'vip' => isset($tmp['vip']) ? $tmp['vip'] : '',
                    'deviceType' => isset($tmp['deviceType']) ? $tmp['deviceType'] : '',
                    'score' =>  $row['respond_rating'] ? true : false,
                    'respond_rating' => $row['respond_rating'],
                    'attitude_rating' => $row['attitude_rating'],
                    'experience_rating' => $row['experience_rating'],
                    'end_type' => $row['end_type'],
//                    'queue_time' => $row['queue_time'],//排队时间
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'robot_num' => $r_num,
                    'service_num' => $s_num,
                    'robot_detail' => isset($robot[$row['session_id']]) ? $robot[$row['session_id']] : array(),
                    'service_detail' => isset($ser[$row['session_id']]) ? $ser[$row['session_id']] : array(),
                    'client_info' => isset($tmp) ? $tmp : array()
                );
                $ret[] = $data;
            }
            $aindex = array();
            if($adata){
                $str = implode(',',$adata);
                $sql = "select * from bay_archive WHERE id in ({$str})";
                $arel = $this->db->query($sql)->result_array();
                foreach($arel as $r){
                    $aindex[$r['id']] = $r['title'];
                }
            }
            if($aindex){
                foreach($ret as &$row){
                    if($row['archive_category']){
                        $row['cate_name'] = implode(',',fun::reflect($row['archive_category'],$aindex));
                    }else{
                        $row['cate_name'] = '';
                    }
                }
            }
        }
        return array($ret,$num);
    }




    /**
     * 客服数据统计
     * 满意度：只统计有评分的
     * @param $time
     * @param $_time
     * @param array $val
     * @param array $field
     * @return array|string
     * @date
     */
    public function serviceChart($time,$_time,$val = array(),$field = array()){
        $w = $this->_getValue($field,$val);
        $st = $w['st'];
        $ed = $w['ed'];
        $where = "s.clock >= {$st} and s.clock < {$ed}  and s.del = 0";
        //先把客服找出来
        $uids = array();
        $name_index = array();
        $ret = array();
        if(isset($w['uid'])){
            $uids = explode(',',$w['uid']);
            $sql = "select * from bay_users where uid in ({$w['uid']})";
            $rel = $this->db->query($sql)->result_array();
            if($rel){
                foreach($rel as $r){
                    $name_index[$r['uid']] = $r['cname'];
                }
            }
        }elseif(isset($w['set_id'])){
            $tmp = explode(',',$w['set_id']);
            foreach($tmp as $set_id){
                $sql = "select * from bay_users where find_in_set({$set_id},set_id)";
                $rel = $this->db->query($sql)->result_array();
                if($rel){
                    foreach($rel as $r){
                        if(!in_array($r['uid'],$uids)) $uids[] = $r['uid'];
                        if(!isset($name_index[$r['uid']])) $name_index[$r['uid']] = $r['cname'];
                    }
                }
            }
        }
        if(!$uids) return "所选的游戏组下面没有任何客服";
        $where .= " and s.service_id in (" . implode(',',$uids) . ")";
        $sql = "select s.clock,s.service_id,l.respond_rating,l.attitude_rating,l.experience_rating from tmp_chat_session s INNER JOIN  tmp_rating_log l ON s.session_id = l.session_id WHERE {$where}";
//        var_dump($sql);die;
        $rel = $this->odb->query($sql)->result_array();
        $data = array();
        if($rel){
            $i = 0;
            $len = count($_time) -1;
            foreach($rel as $row){
                $t = $row['clock'];
                if($t >= $_time[$i] &&  $t < $_time[$i+1]){
                    $str = $_time[$i] . '-' . $_time[$i+1];
                    $data[$row['service_id']][$str][] = $row['respond_rating'] + $row['attitude_rating'] + $row['experience_rating'];
//                    var_dump($str);die;
                }else{
                    for($n = 0;$n < $len;$n++){
                        if($t >= $_time[$n] &&  $t < $_time[$n+1]){
                            $str = $_time[$n] . '-' . $_time[$n+1];
                            $data[$row['service_id']][$str][] = $row['respond_rating'] + $row['attitude_rating'] + $row['experience_rating'];
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
                foreach($data as $uid => $row){
                    for($n = 0;$n < $len;$n++){
                        $str = $_time[$n] . '-' . $_time[$n+1];
//                        var_dump($row);die;
                        if(isset($row[$str])){
                            $score = round(array_sum($row[$str]) / count($row[$str]) / 15 * 100,2);
                            $series[$uid][] = $score;
                            $ret['table'][$uid][$_time[$n]] = array('score' => $score,'time' => $_time[$n],'uid' => $uid);
                        }else{
                            $series[$uid][] = 0;
                            $ret['table'][$uid][$_time[$n]] = array('score' => 0,'time' => $_time[$n],'uid' => $uid);
                        }
                    }
                }
                //组织数据
                array_pop($time);
                $ret['chart']['categories'] = $time;
                foreach($series as $uid => $arr){
                    $ret['chart']['series'][] = array(
                        'name' => $name_index[$uid],
                        'type' => 'line',
                        'data' => $arr
                    );
                }

            }
        }
        return $ret;
    }








    //满意度：只统计有评分的
    /**
     * 游戏分时段满意度统计
     * @param $time
     * @param $_time
     * @param array $val
     * @param array $field
     * @return array|string
     * @date
     */
    public function gameCount($time,$_time,$val = array(),$field = array()){
        $w = $this->_getValue($field,$val);
        $st = $w['st'];
        $ed = $w['ed'];
        $where = "s.clock >= {$st} and s.clock < {$ed}  and s.del = 0";
        //先把站点找出来
        $sid_arr = array();
        $sid_index = array();
        $ret = array();
//        $uids = array();
        if(isset($w['sids'])){
            $tmp = explode(',',$w['sids']);
            if($tmp){
                foreach($tmp as $row){
                    $t = explode('.',$row);
                    if($t[0] && $t[1]){
                        if(!isset($sid_arr[$t[0]]) || !in_array($t[1],$sid_arr[$t[0]])) $sid_arr[$t[0]][] = $t[1];
                    }

                }
            }
        }elseif(isset($w['set_id'])){
            $tmp = explode(',',$w['set_id']);
            /*foreach($tmp as $set_id){
                $sql = "select * from bay_users where find_in_set({$set_id},set_id)";
                $rel = $this->db->query($sql)->result_array();
                if($rel){
                    foreach($rel as $r){
                        if(!in_array($r['uid'],$uids)) $uids[] = $r['uid'];
                        if(!isset($name_index[$r['uid']])) $name_index[$r['uid']] = $r['cname'];
                    }
                }
            }*/
            $grp = fun::getGameGroup($w['set_id']);
            if($grp){
                foreach($grp as $row){
                    if($row['sids']){
                        $s = explode(',',$row['sids']);
                        foreach($s as $r){
                            $t = explode('.',$r);
                            if($t[0] && $t[1]){
                                if(!isset($sid_arr[$t[0]]) || !in_array($t[1],$sid_arr[$t[0]])) $sid_arr[$t[0]][] = $t[1];
                            }
                        }

                    }
                }
            }
        }
        if(!$sid_arr) return "所选的游戏组下面没有任何站点";
        $str = '';
        foreach($sid_arr as $gid => $row){
            $_t = implode(',',$row);
            $str .= "(s.gid = {$gid} and s.site_id in ({$_t})) OR";
        }
        $str = rtrim($str,'OR');
        $gsql = "select * from bay_site s WHERE " . str_replace('site_id','sid',$str);
        $grel = $this->db->query($gsql)->result_array();
        if($grel){
            foreach($grel as $row){
                $t = $row['gid'] . '-' . $row['sid'];
                $sid_index[$t] = $row['gname'] . '-' . $row['lname'];
            }
        }
        $where .= " and ({$str})";
        $sql = "select s.gid,s.site_id,s.clock,s.service_id,l.respond_rating,l.attitude_rating,l.experience_rating from tmp_chat_session s INNER JOIN  tmp_rating_log l ON s.session_id = l.session_id WHERE {$where}";
        $rel = $this->odb->query($sql)->result_array();
        $data = array();
        if($rel){
            $i = 0;
            $len = count($_time) -1;
            foreach($rel as $row){
                $t = $row['clock'];
                $_s = $row['gid'] . '-' . $row['site_id'];
                if($t >= $_time[$i] &&  $t < $_time[$i+1]){
                    $str = $_time[$i] . '-' . $_time[$i+1];
                    $data[$_s][$str][] = $row['respond_rating'] + $row['attitude_rating'] + $row['experience_rating'];
                }else{
                    for($n = 0;$n < $len;$n++){
                        if($t >= $_time[$n] &&  $t < $_time[$n+1]){
                            $str = $_time[$n] . '-' . $_time[$n+1];
                            $data[$_s][$str][] = $row['respond_rating'] + $row['attitude_rating'] + $row['experience_rating'];
                            $i = $n;
                            break;
                        }
                    }
                }
            }
//            var_dump($sid_index);die;
            //数据处理
            if($data){
                $series = array();
                foreach($data as $sid => $row){
                    for($n = 0;$n < $len;$n++){
                        $str = $_time[$n] . '-' . $_time[$n+1];
                        if(isset($row[$str])){
                            $score = round(array_sum($row[$str]) / count($row[$str]) / 15 * 100,2);
                            $series[$sid][] = $score;
                            $ret['table'][$sid][$_time[$n]] = array('score' => $score,'time' => $_time[$n],'site' => $sid_index[$sid]);
                        }else{
                            $series[$sid][] = 0;
//                            $ret['table'][$sid][$_time[$n]] = 0;
                            $ret['table'][$sid][$_time[$n]] = array('score' => 0,'time' => $_time[$n],'site' => $sid_index[$sid]);
                        }
                    }
                }
                //组织数据
                array_pop($time);//删掉最后一个时间，没用到
                $ret['chart']['categories'] = $time;
                foreach($series as $sid => $arr){
                    $ret['chart']['series'][] = array(
                        'name' => $sid_index[$sid],
                        'type' => 'line',
                        'data' => $arr
                    );
                }

            }
        }
        return $ret;
    }



    /**
     * 游戏整体满意度统计
     * @param array $val
     * @param array $field
     * @return array|string
     * @date
     */
    public function grpCount($val = array(),$field = array()){
        $w = $this->_getValue($field,$val);
        $st = $w['st'];
        $ed = $w['ed'];
        $where = "s.clock >= {$st} and s.clock < {$ed}  and s.del = 0";
        //先把站点找出来
        $sid_arr = array();
        $sid_index = array();
        $grp_index = array();
        $_index = array();
        $ret = array();
        $sidShow = false;
        if(isset($w['sids'])){//按站点展示
            $sidShow = true;
            $tmp = explode(',',$w['sids']);
            if($tmp){
                foreach($tmp as $row){
                    $t = explode('.',$row);
                    if($t[0] && $t[1]){
                        if(!isset($sid_arr[$t[0]]) || !in_array($t[1],$sid_arr[$t[0]])) $sid_arr[$t[0]][] = $t[1];
                    }

                }
            }
            //游戏名字
            $tmp = fun::getGameGroup($w['set_id']);
            foreach($tmp as $key => $row){
                $grp_index[$key] = $row['name'];
                if($row['sids']){
                    $t = explode(',',$row['sids']);
                    foreach($t as $r){
                        $_t = explode('.',$r);
                        $str = $_t[0] . '-' . $t[1];
                        $_index[$str] = $key;
                    }
                }
            }
        }elseif(isset($w['set_id'])){//按游戏组展示
            $grp = fun::getGameGroup($w['set_id']);
            if($grp){
                foreach($grp as $key => $row){
                    $grp_index[$key] = $row['name'];
                    $t = explode(',',$row['sids']);
                    foreach($t as $r){
                        $_t = explode('.',$r);
                        if($_t[0] && $_t[1]){
                            $str = $_t[0] . '-' . $_t[1];
                            $_index[$str] = $key;
                            if(!isset($sid_arr[$_t[0]]) || !in_array($_t[1],$sid_arr[$_t[0]])) $sid_arr[$_t[0]][] = $_t[1];
                        }
                    }


                }
            }
        }
        if(!$sid_arr) return "所选的游戏组下面没有任何站点";
        $str = '';
        foreach($sid_arr as $gid => $row){
            $_t = implode(',',$row);
            $str .= "(s.gid = {$gid} and s.site_id in ({$_t})) OR";
        }
        $str = rtrim($str,"OR");
        $where .= " and ({$str})";

        $gsql = "select * from bay_site s WHERE " . str_replace('site_id','sid',$str);
        $grel = $this->db->query($gsql)->result_array();
        if($grel){
            foreach($grel as $row){
                $t = $row['gid'] . '-' . $row['sid'];
                $sid_index[$t] = $row['gname'] . '-' . $row['lname'];
            }
        }

        $sql = "select s.gid,s.site_id,s.clock,s.service_id,l.respond_rating,l.attitude_rating,l.experience_rating from tmp_chat_session s INNER JOIN  tmp_rating_log l ON s.session_id = l.session_id WHERE {$where}";
        $rel = $this->odb->query($sql)->result_array();
        $data = array();
        if($rel){
            //总条数
            $data['total']['num'] = count($rel);
            foreach($rel as $row){
                $data['total']['rs'] = isset($data['total']['rs']) ? $data['total']['rs'] + $row['respond_rating'] : $row['respond_rating'];
                $data['total']['at'] = isset($data['total']['at']) ? $data['total']['at'] + $row['attitude_rating'] : $row['attitude_rating'];
                $data['total']['ex'] = isset($data['total']['ex']) ? $data['total']['ex'] + $row['experience_rating'] : $row['experience_rating'];
                //sid键名
                $str = $row['gid'] . '-' . $row['site_id'];
                $data[$_index[$str]][$str]['rs'] = isset($data[$_index[$str]][$str]['rs']) ? $data[$_index[$str]][$str]['rs'] + $row['respond_rating'] : $row['respond_rating'];
                $data[$_index[$str]][$str]['at'] = isset($data[$_index[$str]][$str]['at']) ? $data[$_index[$str]][$str]['at'] + $row['attitude_rating'] : $row['attitude_rating'];
                $data[$_index[$str]][$str]['ex'] = isset($data[$_index[$str]][$str]['ex']) ? $data[$_index[$str]][$str]['ex'] + $row['experience_rating'] : $row['experience_rating'];
                //数目
                $data[$_index[$str]][$str]['num'] = isset($data[$_index[$str]][$str]['num']) ? $data[$_index[$str]][$str]['num'] + 1 : 1;
            }
            //数据处理
            if($data){
                $ret[] = array(
                    'grp_name' => '总计',
                    'sid' => '全部',
                    'at' => round($data['total']['at'] / $data['total']['num'] / 5 * 100,2),
                    'ex' => round($data['total']['ex'] / $data['total']['num'] / 5 * 100,2),
                    'rs' => round($data['total']['rs'] / $data['total']['num'] / 5 * 100,2),
                    'all' => round((($data['total']['at'] + $data['total']['ex'] + $data['total']['rs']) / $data['total']['num'] / 3) * 15 * 100,2)
                );
                foreach($data as $set_id => $row){
                    if($set_id == 'total') continue;//总计数跳过
                    if(!$sidShow){//游戏展示
                        $rs = 0;
                        $ex = 0;
                        $at = 0;
                        $num = 0;
                        foreach($row as $sid => $r){
                            $rs = $rs + $r['rs'];
                            $ex = $ex + $r['ex'];
                            $at = $at + $r['at'];
                            $num = $num + $r['num'];
                        }
                        $total = $rs + $ex + $rs;
                        $ret[] = array(
                            'grp_name' => $grp_index[$set_id],
                            'sid' => '全部',
                            'at' => $at ? round($at / $num / 5 * 100,2) : 0,
                            'ex' => $ex ? round($ex / $num / 5 * 100,2) : 0,
                            'rs' => $rs ? round($rs / $num / 5 * 100,2) : 0,
                            'all' => $total ? round($total / $num / 3 / 5 * 100,2) : 0
                        );
                    }else{//站点展示
                        foreach($row as $sid => $r){
                            $total = $r['at'] + $r['ex'] + $r['rs'];
                            $ret[] = array(
                                'grp_name' => $grp_index[$set_id],
                                'sid' => $sid_index[$sid],
                                'at' => $r['at'] ? round($r['at'] / $r['num'] / 5 * 100,2) : 0,
                                'ex' => $r['ex'] ? round($r['ex'] / $r['num'] / 5 * 100,2) : 0,
                                'rs' => $r['rs'] ? round($r['rs'] / $r['num'] / 5 * 100,2) : 0,
                                'all' => $total ? round($total / $r['num'] / 3 / 5 * 100,2) : 0
                            );
                        }

                    }


                }
            }
        }
        return $ret;
    }



    /**
     * 客服整体满意度统计
     * @param array $val
     * @param array $field
     * @return array
     * @date
     */
    public function serCount($val = array(),$field = array()){
        $w = $this->_getValue($field,$val);
        $st = $w['st'];
        $ed = $w['ed'];
        $where = "s.clock >= {$st} and s.clock < {$ed}  and s.del = 0";
        //先把站点找出来
        $uids = array();
        $grp_index = array();
        $sid_arr = array();
        $_index = array();
        $ret = array();
        $serShow = false;
        $uGrpIndex = array();
        if(isset($w['uid'])){
            $serShow = true;
            $uids = explode(',',$w['uid']);
            $sql = "select * from bay_users where uid in ({$w['uid']})";
            $rel = $this->db->query($sql)->result_array();
            if($rel){
                foreach($rel as $r){
                    if($r['set_id']){
                        $uGrpIndex[$r['uid']] = fun::getGameGroup($r['set_id'],'name');
                    }
                }
            }
        }elseif(isset($w['set_id'])){
            $grp = fun::getGameGroup($w['set_id']);
            if($grp){
                foreach($grp as $key => $row){
                    $grp_index[$key] = $row['name'];
                    if($row['sids']){
                        $t = explode(',',$row['sids']);
                        foreach($t as $r){
                            $_t = explode('.',$r);
                            if($_t[0] && $_t[1]){
                                $str = $_t[0] . '-' . $_t[1];
                                $_index[$str] = $key;
                                if(!isset($sid_arr[$_t[0]]) || !in_array($_t[1],$sid_arr[$_t[0]])) $sid_arr[$_t[0]][] = $_t[1];
                            }
                        }
                    }
                }
            }
        }
//        var_dump($_index);die;
        if($serShow){
            if(!$uids) return "所选的游戏组下面没有设定任何客服！";
            $tmp = implode(',',$uids);
            $where .= " and s.service_id in ({$tmp})";
        }else{
            if(!$sid_arr) return "所选的游戏组下面没有设定任何站点！";
            $str = '';
            foreach($sid_arr as $gid => $row){
                $_t = implode(',',$row);
                $str .= " (s.gid = {$gid} and s.site_id in ({$_t})) OR";
            }
            $str = rtrim($str,"OR");
            $where .= " and ({$str})";
        }
        $sql = "select s.gid,s.site_id,s.clock,s.service_id,l.respond_rating,l.attitude_rating,l.experience_rating from tmp_chat_session s LEFT JOIN  tmp_rating_log l ON s.session_id = l.session_id WHERE {$where}";
        $rel = $this->odb->query($sql)->result_array();
//        var_dump($sql);die;
        $data = array();
        if($rel){

            //总条数
            $data['total']['num'] = count($rel);
//            var_dump(count($rel));die;
            $t0 = $t1 = $t2 = $t3 = $t4 = $t5 = 0;
            foreach($rel as $row){
                //uid键名
                $str = $row['service_id'];
                $key = $row['gid'] . '-' . $row['site_id'];
                if($row['attitude_rating']){//有评分
                    $data['total']['rs'] = isset($data['total']['rs']) ? $data['total']['rs'] + $row['respond_rating'] : $row['respond_rating'];
                    $data['total']['at'] = isset($data['total']['at']) ? $data['total']['at'] + $row['attitude_rating'] : $row['attitude_rating'];
                    $data['total']['ex'] = isset($data['total']['ex']) ? $data['total']['ex'] + $row['experience_rating'] : $row['experience_rating'];

                    $data[$_index[$key]][$str]['rs'] = isset($data[$_index[$key]][$str]['rs']) ? $data[$_index[$key]][$str]['rs'] + $row['respond_rating'] : $row['respond_rating'];
                    $data[$_index[$key]][$str]['at'] = isset($data[$_index[$key]][$str]['at']) ? $data[$_index[$key]][$str]['at'] + $row['attitude_rating'] : $row['attitude_rating'];
                    $data[$_index[$key]][$str]['ex'] = isset($data[$_index[$key]][$str]['ex']) ? $data[$_index[$key]][$str]['ex'] + $row['experience_rating'] : $row['experience_rating'];
                    //数目
                    $data[$_index[$key]][$str]['num'] = isset($data[$_index[$key]][$str]['num']) ? $data[$_index[$key]][$str]['num'] + 1 : 1;
                    //根据服务态度统计评分多少
                    switch($row['attitude_rating']){
                        case 5:
                            $data[$_index[$key]][$str]['five'] = isset($data[$_index[$key]][$str]['five']) ? $data[$_index[$key]][$str]['five'] + 1 : 1;
                            $t5++;
                            break;
                        case 4:
                            $data[$_index[$key]][$str]['four'] = isset($data[$_index[$key]][$str]['four']) ? $data[$_index[$key]][$str]['four'] + 1 : 1;
                            $t4++;
                            break;
                        case 3:
                            $data[$_index[$key]][$str]['three'] = isset($data[$_index[$key]][$str]['three']) ? $data[$_index[$key]][$str]['three'] + 1 : 1;
                            $t3++;
                            break;
                        case 2:
                            $data[$_index[$key]][$str]['two'] = isset($data[$_index[$key]][$str]['two']) ? $data[$_index[$key]][$str]['two'] + 1 : 1;
                            $t2++;
                            break;
                        case 1:
                            $data[$_index[$key]][$str]['one'] = isset($data[$_index[$key]][$str]['one']) ? $data[$_index[$key]][$str]['one'] + 1 : 1;
                            $t1++;
                            break;
                    }
                }else{//没评分
                    $data['total']['no'] = isset($data['total']['no']) ? $data['total']['no'] + 1 : 1;
                    $data[$_index[$key]][$str]['no'] = isset($data[$_index[$key]][$str]['no']) ? $data[$_index[$key]][$str]['no'] + 1 : 1;
                    $t0++;
                }

            }
            //数据处理
//            var_dump($data);die;
            if($data){
                $ret[] = array(
                    'grp_name' => '总计',
                    'sid' => '全部',
                    'at' => round($data['total']['at'] / $data['total']['num'] / 5 * 100,2),
                    'ex' => round($data['total']['ex'] / $data['total']['num'] / 5 * 100,2),
                    'rs' => round($data['total']['rs'] / $data['total']['num'] / 5 * 100,2),
                    'all' => round(($data['total']['at'] + $data['total']['ex'] + $data['total']['rs']) / $data['total']['num'] / 3 / 15 * 100,2),
                    'no' => $t0,
                    'one' => $t1,
                    'two' => $t2,
                    'three' => $t3,
                    'four' => $t4,
                    'five' => $t5,
                    'total' => $t1 + $t2 + $t3 + $t4 + $t5
                );
                foreach($data as $set_id => $row){
                    if($set_id == 'total') continue;//总计数跳过
                    if(!$serShow){//游戏组展示
                        $rs = 0;
                        $ex = 0;
                        $at = 0;
                        $num = 0;
                        $no = 0;
                        $one = 0;
                        $two = 0;
                        $three = 0;
                        $four = 0;
                        $five = 0;
//                        var_dump($row);die;
                        foreach($row as $sid => $r){
                            $rs = $rs + $r['rs'];
                            $ex = $ex + $r['ex'];
                            $at = $at + $r['at'];
                            $num = $num + $r['num'];
                            $no = $no + (isset($r['no']) ? $r['no'] : 0);
                            $one = $one + (isset($r['one']) ? $r['one'] : 0);
                            $two = $two + (isset($r['two']) ? $r['two'] : 0);
                            $three = $three + (isset($r['three']) ? $r['three'] : 0);
                            $four = $four + (isset($r['four']) ? $r['four'] : 0);
                            $five = $five + (isset($r['five']) ? $r['five'] : 0);
                        }
                        /*$total = $rs + $ex + $at;
                        if(!$rs || !$ex || !$at){
                            var_dump($row);
                            die;
                        }*/
                        $ret[] = array(
                            'grp_name' => $grp_index[$set_id],
                            'sid' => '全部',
                            'at' => round($at / $num / 5 * 100,2),
                            'ex' => round($ex / $num / 5 * 100,2),
                            'rs' => round($rs / $num / 5 * 100,2),
                            'all' => round(($rs + $ex + $rs) / $num / 3 / 5 * 100,2),
                            'no' => $no,
                            'one' => $one,
                            'two' => $two,
                            'three' => $three,
                            'four' => $four,
                            'five' => $five,
                            'total' => $one + $two + $three + $four + $five
                        );
                    }else{//客服展示
                        foreach($row as $uid => $r){
                            $ret[] = array(
                                'grp_name' => isset($uGrpIndex[$uid]) ? implode(',',$uGrpIndex[$uid]) : '',
                                'uid' => $uid,
                                'at' => round($r['at'] / $r['num'] / 5 * 100,2),
                                'ex' => round($r['ex'] / $r['num'] / 5 * 100,2),
                                'rs' => round($r['rs'] / $r['num'] / 5 * 100,2),
                                'all' => round(($r['at'] + $r['ex'] + $r['rs']) / $r['num'] / 3  / 5 * 100,2),
                                'one' => isset($r['one']) ? $r['one'] : 0,
                                'two' => isset($r['two']) ? $r['two'] : 0,
                                'three' => isset($r['three']) ? $r['three'] : 0,
                                'four' => isset($r['four']) ? $r['four'] : 0,
                                'five' => isset($r['five']) ? $r['five'] : 0,
                                'no' => isset($r['no']) ? $r['no'] : 0,
                                'total' => $r['one'] + $r['two'] + $r['three'] + $r['four'] + $r['five']
                            );
                        }

                    }


                }
            }
        }
        return $ret;
    }














}