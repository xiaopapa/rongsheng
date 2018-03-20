<?php

/**
 * 监控model
 * Class Monitor_model
 * @author harvey
 * @Date: 2017.8.9
 * @license  http://www.boyaa.com/
 */
class Monitor_model extends Bmax_Model {
    private $_gm_url;
    private $_sm_url;
    private $_cm_url;
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $onlie_url = $this->config->item('online_url');
        $this->_gm_url = $onlie_url . '/service/monitoronline/bysite';//游戏监控
        $this->_sm_url = $onlie_url . '/service/monitoronline/byuid';//客服监控
        $this->_cm_url = $onlie_url . '/service/monitorservicedetail/byuid';//客服详细列表
    }


    /**
     * 获取列游戏监控数据
     * @param $groups
     * @return array
     * @date
     */
    public function getGameMonitor($groups){
        $site = array();
        $sids = array();
        $g = array();//游戏组下面的站点
        foreach($groups as $key => $row){
            $sids[$key] = $row['sids'];
        }
        foreach($sids as $key => $row){
            $tmp = explode(',',$row);
            foreach($tmp as $r){
                $g[$key][] = $r;
                $_tmp = explode('.',$r);
                if($_tmp[0] && $_tmp[1]) $site[$_tmp[0]][] = $_tmp[1];
            }
        }
        $ret = array();
        if($site){
            $send = array();
            foreach($site  as $gid => $row){
                $send[] = array(
                    'gid' => (STRING) $gid,
                    'site_id' => implode(',',$row)
                );
            }
            $data = array(
                'json' => true,
                'url' => $this->_gm_url,
                'data' => json_encode(array('site_ids' =>$send))
            );
            $rel = fun::curlHttp($data,'post');
            $data = array();
            if($rel['code'] == 200){
                $rel = $rel['data'];
                $rel = json_decode($rel,true);
                if($rel['data']){
                    foreach($rel['data'] as $row){
                        $key = $row['gid'] . '.' . $row['site_id'];
                        foreach($g as $k => $r){
                            if(in_array($key,$r)){
                                $data[$k]['online'] = isset($data[$k]['online']) ? $data[$k]['online'] + $row["client_online_num"] : $row["client_online_num"];
                                $data[$k]['queueing'] = isset($data[$k]['queueing']) ? $data[$k]['queueing'] + $row["client_queueing_num"] : $row["client_queueing_num"];
                                $data[$k]['general'] = isset($data[$k]['general']) ? $data[$k]['general'] + $row["client_queueing_num_vip"] : $row["client_queueing_num_vip"];
                                $data[$k]['vip'] = isset($data[$k]['vip']) ? $data[$k]['vip'] + $row["client_queueing_num_vip"] : $row["client_queueing_num_vip"];
                                break;
                            }
                        }
                    }
                }
            }
            if($data){
                foreach($data as $key => $row){
                    $ret[] = array(
                        'name' => $groups[$key]['name'],
                        'online' => $row['online'],
                        'queueing' => $row['queueing'],
                        'general' => $row['general'],
                        'vip' => $row['vip']
                    );
                }
            }
        }
        return $ret;
    }

    /**
     * 获取客服监控数据
     * @param $groups
     * @return array
     * @date
     */
    public function getAttendMonitor($groups){
        $uids = array();
        foreach($groups as $key => $row){
            $sql = "select uid from bay_users WHERE find_in_set('{$key}',set_id)";
            $rel = $this->db->query($sql)->result_array();
            if($rel){
                foreach($rel as $r){
                    if(!in_array($r['uid'],$uids))$uids[] = $r['uid'];
                }
            }

        }

        $ret = array();
        if($uids){
            $send = array(
                'uid' => implode(',',$uids)
            );

            $data = array(
                'json' => true,
                'url' => $this->_sm_url,
                'data' => json_encode($send)
            );
            $rel = fun::curlHttp($data,'post');
            if($rel['code'] == 200){
                $rel = $rel['data'];
                $rel = json_decode($rel,true);
                $ret[] = array(
                    'group' => '全部',
                    'total' => count($uids),
                    'online' => $rel['data']["online_num"],
                    'busy' => $rel['data']["busy_num"],
                    'free' => $rel['data']["idle_num"],
                );
            }
        }
        return $ret;
    }

    /**
     * 客服监控
     * @param $groups
     * @return array
     * @date
     */
    public function getServiceMonitor($groups){
        $odb = $this->load->database('online',true);
        $uids = array();
        foreach($groups as $key => $row){
            $sql = "select uid from bay_users WHERE find_in_set('{$key}',set_id)";
            $rel = $this->db->query($sql)->result_array();
            if($rel){
                foreach($rel as $r){
                    if(!in_array($r['uid'],$uids))$uids[] = $r['uid'];
                }
            }
        }
        $ret = array();
        if($uids){
            $uid_str = implode(',',$uids);
            $send = array(
                'uid' => $uid_str
            );
            $data = array(
                'json' => true,
                'url' => $this->_cm_url,
                'data' => json_encode($send)
            );
            $rel = fun::curlHttp($data,'post');
            $back = array();
            if($rel['code'] == 200){
                $rel = $rel['data'];
                $rel = json_decode($rel,true);
                if($rel['data']){
                    foreach($rel['data'] as $row){
                        $back[$row['service_id']] = $row;
                    }
                }
                $st = strtotime(date('Y-m-d'));
                $now = time();
                $sql = "select set_id,uid from bay_users WHERE uid in ({$uid_str})";
                $rel = $this->db->query($sql)->result_array();
                $_sql = "select * from t_accept_log WHERE service_id in ({$uid_str})  AND access_clock > {$st}";
                $_rel = $odb->query($_sql)->result_array();
                $adata = array();
                if($_rel){
                    foreach($_rel as $row){
                        $adata['service_id'][] = $row;
                    }
                }

                $udata = array();
                $g_ids = array();
                foreach($rel as $row){
                    $udata[$row['uid']] = $row;
                    if($row['set_id']){
                        $g_ids = array_merge($g_ids,explode(',',$row['set_id']));
                    }
                }
                $sql = "select * from bay_statuslog WHERE time >= {$st} AND uid in({$uid_str}) ORDER BY id DESC ";
                $rel = $this->db->query($sql)->result_array();
//                $rel = $odb->query($sql)->result_array();
                $bdata = array();
                $bIndex = array();
                if($rel){
                    foreach($rel as $r){
                        $bdata[$r['uid']][$r['id']] = $r;
                        if($r['status'] == -1){
                            if(!isset($bIndex[$r['uid']]['last'])){
                                $bIndex[$r['uid']]['last'] = $r['id'];
                            }elseif(!isset($bIndex[$r['uid']]['first'])){
                                $bIndex[$r['uid']]['first'] = $r['id'];
                            }
                        }
                    }
                }
                $g_ids = array_unique($g_ids);//去重
                $g_ids = implode(',',$g_ids);
                $gdata = fun::getGameGroup($g_ids);
                foreach($uids as $uid){
                    $ids = $uids[$uid]['set_id'];
                    $tmp = explode(',',$ids);
                    //组别
                    //服务对象
                    foreach($tmp as $set_id){
                        if(isset($gdata[$set_id]['name'])) $ret[$uid]['group_name'][] = $gdata[$set_id]['name'];
                        if(isset($gdata[$set_id]['sids'])) $ret[$uid]['server_obj'][] = $gdata[$set_id]['sids'];
                    }
                    //首次上线时间
                    if(isset($bIndex[$uid]['last'])){//有最后一次离开状态
                        ksort($bdata[$uid]);
                        //离开后第一次上线
                        $last_id = $bIndex[$uid]['last'];
                        foreach($bdata[$uid] as $key =>  $row){
                            if($key <= $last_id) continue;
                            if($row['status'] >  0){
                                $ret[$uid]['first_online'] = $row['time'];
                                break;
                            }
                        }

                        //离开后没有上线了
                        if(!$ret[$uid]['first_online']){
                            $ret[$uid]['out'] = true;
                            //有两次以上离开状态
                            if(isset($ret[$uid]['first'])){
                                $first_id = $bIndex[$uid]['first'];
                                foreach($bdata[$uid] as $key =>  $row){
                                    if($key <= $first_id) continue;
                                    if($row['status'] >  0){
                                        $ret[$uid]['first_online'] = $row['time'];
                                        break;
                                    }
                                }
                                //到这里 按理来说一定会有个上线状态的
                            }else{//今天内没有两次离开操作
                                //去查找最后两次离开操作时间，然后去中间上线时间值
                                $sql = "select id from kf_statuslog WHERE uid = {$uid} AND status = -1 AND time > {$st} ORDER BY id DESC limit 2 ";
//                                $cdata = $this->db->query($sql)->result_array();
                                $cdata = $odb->query($sql)->result_array();
                                if($cdata && count($cdata) == 2){
                                    $s_id  = $cdata[0]['id'];
                                    $l_id  = $cdata[1]['id'];
                                    $sql = "select * from kf_statuslog WHERE uid = {$uid} AND status > 0 AND id > {$s_id} AND id < {$l_id} ORDER BY id limit 1";
//                                    $_cdata = $this->db->query($sql)->result_array();
                                    $_cdata = $odb->query($sql)->result_array();
                                    if($_cdata){
                                        $tmp_time = $_cdata[0]['time'];
                                        if($tmp_time < $st){//两次离开中间的上线时间，这个时间比凌晨0点要早，那么取0点
                                            $ret[$uid]['first_online'] = strtotime(date("Y-m-d"));
                                        }else{//就取这个
                                            $ret[$uid]['first_online'] = $tmp_time;
                                        }
                                    }
                                }
                                //如果这里还没有值，说明这个用户应该是真的没有上线
                                if(!isset($ret[$uid]['first_online'])) $ret[$uid]['first_online'] = 0;
                            }
                        }
                    }else{
                        //如果没有最后一次离开状态，但是服务器上面有在线的，那边上线时间位凌晨0点
                        if($back[$uid]['status'] > 0){
                            $ret[$uid]['first_online'] = strtotime(date("Y-m-d"));
                        }else{
                            if($back[$uid]['client_num'] > 0){
                                $ret[$uid]['first_online'] = strtotime(date("Y-m-d"));
                            }else{
                                $ret[$uid]['first_online'] = '0';
                            }
                        }

                    }
                    //示忙时间
                    if($ret[$uid]['first_online']) {//有首次上线时间
                        if ($ret[$uid]['out']) {//时间从首次上线时间到最后离开时间
                            $all_time = $bIndex[$uid]['last'] - $ret[$uid]['first_online'];
                        } else {//时间从首次上线时间到现在算起
                            $all_time = $now - $ret[$uid]['first_online'];
                        }
                        $time_flag = false;
                        $busy_time = 0;
                        if($bdata[$uid]){
                            foreach($bdata[$uid] as $row){
                                if($row['time'] < $ret[$uid]['first_online']) continue;
                                if($time_flag){//时间统计
                                    if($row['status'] > 0){
                                        $busy_time = $busy_time + ($row['time'] - $time_flag);
                                        $time_flag = false;
                                    }
                                }elseif($row['status'] == -2){//已经开始示忙了(真示忙)
                                    $time_flag = $row['time'];
                                }
                            }
                        }
                        //示忙时间
                        if($busy_time){
                            $ret[$uid]['busy_time'] = $busy_time;
                        }else{
                            $ret[$uid]['busy_time'] = 0;
                        }
                        //今日服务时长
                        $ret[$uid]['service_time'] = $all_time - $busy_time;
                    }else{
                        $ret[$uid]['service_time'] = 0;
                        $ret[$uid]['busy_time'] = 0;
                    }
                    //状态
                    $ret[$uid]['service_status'] = $back[$uid]['status'];
                    //在线人数
                    $ret[$uid]['online_num'] = $back[$uid]['client_num'];
                    $ret[$uid]['client'] = $back[$uid]['client_id'];
                    //今日服务人数
                    if(isset($adata[$uid])){
                        $ret[$uid]['service_num'] = count($adata[$uid]);
                    }else{
                        $ret[$uid]['service_num'] = 0;
                    }
                    //认领举报
                    $ret[$uid]['report'] = $back[$uid]['claim_appeal_num'];
                    //认领留言
                    $ret[$uid]['advise'] = $back[$uid]['claim_advise_num'];
                }
            }
        }
        //格式化数据
        if($ret){
            foreach($ret as $uid => &$row){
                $row['uid'] = $uid;
                if($udata[$uid]['set_id']){
                    $row['grp'] = fun::getGameGroup($udata[$uid]['set_id'],'name');
                }
            }
        }
        return array_values($ret);
    }

    /**
     * 实时监控
     * @param $group
     * @param $sid_str
     * @param $gp
     * @return array
     * @date
     */
    public function getMonitorReport($group,$sid_str,$gp){
        $ret = array();
        $sids = array();
        $tmp = explode(',',$sid_str);
        $st = strtotime(date('Y-m-d'));
//        var_dump($tmp);die;
        foreach($tmp as $row){
            $_t = explode('.',$row);
//            var_dump($_t);die;
            if(isset($_t[0]) && isset($_t[1])){
                if(!isset($sids[$_t[0]]) || !in_array($_t[1],$sids[$_t[0]])) $sids[$_t[0]][] = $_t[1];
            }
        }
        $str = '';
        if($sids){
            foreach($sids as $gid => $row){
                $sid = implode(',',$row);
                $str .= " (gid =  {$gid} and site_id in ({$sid})) OR";
            }
        }
        //1502812800
        //1502899199

        //修改测试数据
        /*$test = array(
            'gid = 1 ,sid = 67',
            'gid = 1 ,sid = 93',
            'gid = 1 ,sid = 104',
            'gid = 1 ,sid = 117',
            'gid = 4 ,sid = 5',
            'gid = 4 ,sid = 6',
            'gid = 4 ,sid = 104',
            'gid = 10 ,sid = 5503',
        );
        $odb = $this->load->database('online',true);
//        for($i = 13000;$i< 13281;$i++){
        for($i = 1;$i< 713;$i++){
            $t = rand(0,7);
            $s = rand(1504540800,1504627200);
//            $sql = "update tmp_queuelog set {$test[$t]},endtime = {$s} WHERE id = $i";
            $sql = "update tmp_accept_log set accept_clock = {$s} WHERE id = $i";
            $odb->query($sql);
        }
        die;*/

        $str = rtrim($str,'OR');
        $odb = $this->load->database('online',true);
        $sql1 = "select * from t_accept_log WHERE accept_clock > $st AND ({$str}) ";
        $sql2 = "select * from kf_queuelog WHERE endtime > $st AND (" . str_replace('site_id','sid',$str) . ") ";
//        var_dump($sql1);
//        var_dump($sql2);
        $adata = $odb->query($sql1)->result_array();//接入
        $qdata = $odb->query($sql2)->result_array();//排队未接入
//        var_dump($adata);
//        var_dump($qdata);
//        die;
        //时间分段
        $time = $this->cutTime($st);
        $t_num = count($time);
        $tmp_num = $t_num -1;
        for($i = 0;$i < $tmp_num;$i++){
            $categories[] = date("H:i:s",$time[$i]);
        }
        $a_data = array();
        $q_data = array();
        if($adata){
            $a_total = count($adata);
            $i = 0;
            foreach($adata as $row){
                if($row['accept_clock'] >= $time[$i] && $row['accept_clock'] < $time[$i+1]){
                    $a_data[$time[$i] . '-' . $time[$i+1]][] = $row;
                }else{
                    for($i = 0;$i < $t_num;$i++){
                        if($row['accept_clock'] >= $time[$i] && $row['accept_clock'] < $time[$i+1]){
                            $a_data[$time[$i] . '-' . $time[$i+1]][] = $row;
                            break;
                        }
                    }
                }
            }
        }
        if($qdata){
            $q_total = count($qdata);
            $i = 0;
            foreach($qdata as $row){
                if($row['endtime'] >= $time[$i] && $row['endtime'] < $time[$i+1]){
                    $q_data[$time[$i] . '-' . $time[$i+1]][] = $row;
                }else{
                    for($i = 0;$i < $t_num;$i++){
                        if($row['endtime'] >= $time[$i] && $row['endtime'] < $time[$i+1]){
                            $q_data[$time[$i] . '-' . $time[$i+1]][] = $row;
                            break;
                        }
                    }
                }
            }
        }
//        var_dump($q_data);die;
        //总统计
        $ret['total']['accept_num'] = isset($a_total) ? $a_total : 0;
        $ret['total']['queue_num'] = isset($q_total) ? $q_total : 0;
        $tmp = $ret['total']['accept_num'] + $ret['total']['queue_num'];
        $ret['total']['accept_rate'] = $tmp ?  round($ret['total']['accept_num']*100/$tmp,2) : 0;
//        var_dump($ret);die;

        $g_ret = array();
        $q_ret = array();
        $accept_total = array();
        $queue_total = array();
        $access_total = array();
        $rate_total = array();
        $table = array();
        for($i = 0;$i < $tmp_num;$i++){

            //分散统计(总)
            $accept_total[] = $table['total'][$time[$i] . '-' . $time[$i+1]]['accept_num'] = count($a_data[$time[$i] . '-' . $time[$i+1]]);
            $queue_total[] = $table['total'][$time[$i] . '-' . $time[$i+1]]['queue_num'] = count($q_data[$time[$i] . '-' . $time[$i+1]]);
            $tmp = $table['total'][$time[$i] . '-' . $time[$i+1]]['accept_num'] + $table['total'][$time[$i] . '-' . $time[$i+1]]['queue_num'];
            $access_total[] = $table['total'][$time[$i] . '-' . $time[$i+1]]['access_num'] = $tmp;
            $rate_total[] = $table['total'][$time[$i] . '-' . $time[$i+1]]['accept_rate'] = $tmp ? round($table['total'][$time[$i] . '-' . $time[$i+1]]['accept_num']*100/$tmp,2) : 0;
//            var_dump($accept_total);continue;


            //接入
            if($a_data[$time[$i] . '-' . $time[$i+1]]){
                foreach($a_data[$time[$i] . '-' . $time[$i+1]] as $row){
                    $str = $row['gid'] . '.' . $row['site_id'];
                    foreach($group as $set_id => $cp){
                        if(in_array($str,$cp)){
                            $g_ret[$set_id][$time[$i] . '-' . $time[$i+1]]['accept_num'] =  isset($g_ret[$set_id][$time[$i] . '-' . $time[$i+1]]['accept_num']) ? $g_ret[$set_id][$time[$i] . '-' . $time[$i+1]]['accept_num'] + 1 : 0;
                            break;
                        }
                    }
                }

            }
//            var_dump($g_ret);continue;
            //排队
            if($q_data[$time[$i] . '-' . $time[$i+1]]){
                foreach($q_data[$time[$i] . '-' . $time[$i+1]] as $row){
                    $str = $row['gid'] . '.' . $row['sid'];
                    foreach($group as $set_id => $cp){
                        if(in_array($str,$cp)){
                            $q_ret[$set_id][$time[$i] . '-' . $time[$i+1]]['queue_num'] =  isset($q_ret[$set_id][$time[$i] . '-' . $time[$i+1]]['queue_num']) ? $q_ret[$set_id][$time[$i] . '-' . $time[$i+1]]['queue_num'] + 1 : 0;
                            break;
                        }
                    }
                }

            }
        }

        //分散统计(图表总)
        $ret['chart'][0] = array(
            'series'=> array(
                array('name' => '接通总数','data' => $accept_total),
                array('name' => '呼入总数','data' => $access_total),
                array('name' => '接通百分比','data' => $rate_total),
            ),
            'categories' => $categories,
            'name' => '总计'
        );
        //分散(图表游戏组)
        $detail = array();
//        var_dump($g_ret);die;
        foreach($g_ret as $set_id => $row){
            for($i = 0;$i < $tmp_num;$i++){
                $str = $time[$i] . '-' . $time[$i+1];
                if(isset($row[$str]['accept_num'])){
                    $detail[$set_id]['accept_num'][] = $tmp_accept = $row[$str]['accept_num'];
                    $table[$set_id][$str]['accept_num'] = $row[$str]['accept_num'];
                }else{
                    $detail[$set_id]['accept_num'][] = $tmp_accept = 0;
                    $table[$set_id][$str]['accept_num'] = 0;
                }
                if(isset($q_ret[$set_id][$str]['queue_num'])){
                    $detail[$set_id]['access_num'][] = $tmp_total = $q_ret[$set_id][$str]['queue_num'] + $tmp_accept;
                    $table[$set_id][$str]['access_num'] = $q_ret[$set_id][$str]['queue_num'] + $tmp_accept;
                }else{
                    $detail[$set_id]['access_num'][] = $tmp_total = $tmp_accept + 0;
                    $table[$set_id][$str]['access_num'] = $tmp_accept + 0;

                }
                if($tmp_total){
                    $detail[$set_id]['accept_rate'][] = round($tmp_accept/$tmp_total*100,2);
                    $table[$set_id][$str]['accept_rate'] = round($tmp_accept/$tmp_total*100,2);
                }else{
                    $detail[$set_id]['accept_rate'][] = 0;
                    $table[$set_id][$str]['accept_rate'] = 0;

                }

            }
        }
        foreach($detail as $set_id => $row){
            $ret['chart'][$set_id] = array(
                'series'=> array(
                    array('name' => '接通总数','data' => $row['accept_num']),
                    array('name' => '呼入总数','data' => $row['access_num']),
                    array('name' => '接通百分比','data' => $row['accept_rate']),
                ),
                'categories' => $categories,
                'name' => $gp[$set_id]['name']
            );
        }
        if($table){
            foreach($table as $key => $row){
                foreach($row as $k => $r){
                    if($key == 'total'){
                        $name = "总计";
                    }else{
                        $name = $gp[$key]['name'];
                    }
                    $ret['table'][$name][] = array_merge(array('key' => $k),$r);
                }
            }
        }
        return $ret;
    }

    //时间切割
    public function cutTime($st,$ed = '',$type = 'h'){
        if($type == 'h'){
            $part = 3600;
        }elseif($type == 'd'){

        }
        if(!$ed) $ed = time();
        if($st > $ed) return false;
        $ret = array();
        $ret[] = $st;
        $_end = $st + $part;
        while($_end < $ed){
            $ret[] = $_end;
            $_end = $_end + $part;
        }
        $ret[] = $ed;
        return $ret;
    }

    //获取游戏组下面的用户
    public function getGameGroupService($set_id){
        if(!is_array($set_id)) $set_id = explode(',',$set_id);
        $uid = array();
        foreach($set_id as $id){
            $sql = "select * from bay_users WHERE find_in_set({$id},set_id)";
            $rel = $this->db->query($sql)->result_array();
            foreach($rel as $row){
                if(!in_array($row['uid'],$uid)) $uid[] = $row['uid'];
            }
        }
        return $uid;
    }

    //获取用户切换状态
    public function getStatusLog($uid,$status = false,$st = '',$ed = ''){
        if(!is_array($uid)) $uid = explode(',',$uid);
        $this->db->where_in('uid',$uid);
        if($status !== false){
            $this->db->where('status',$status);
        }
        if($st){
            $this->db->where('time >= ',$st);
            if($ed){
                $this->db->where('time <',$ed);
            }
        }
        $this->db->from('statuslog');
        $num = $this->db->count_all_results('',false);
        $this->getLimit($this->db);
        $rel = $this->db->get()->result_array();
        if($rel){
            $index = array();
            foreach($rel as &$row){
                if(isset($index[$row['uid']])){
                    $row['group_name'] = $index[$row['uid']];
                }else{
                    $game_group = fun::getUserPer($row['uid'],'game_group');
                    if($game_group){
                        $groups = fun::getGameGroup($game_group);
                        $name = array();
                        foreach($groups as $s_id => $r){
                            $name[] = $r['name'];
                        }
                        $index[$row['uid']] = $name;
                        $row['group_name'] = $name;
                    }else{
                        $row['group_name'] = [];
                    }

                }

            }
        }
        return  (array($rel,$num));
    }

}


