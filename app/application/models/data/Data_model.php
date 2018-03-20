<?php
/**
 * 业务资料model关类
 * Class Data_model
 * Created by PhpStorm.
 * User: HarveyYang
 * Date: 2017/9/6
 * Time: 15:33
 */


class Data_model extends Bmax_Model {
    private $odb;
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->odb = $this->load->database('online',true);

    }


    /**
     * 保存玩家信息
     * @param array $val
     * @param array $field
     * @return mixed
     * @date
     */
    public function savePlayer($val = array(),$field = array()){
        $w = $this->_getValue($field,$val,$this->db);
        $time = time();
        $data = array(
            'etime' => $time
        );
        $w = array(
            'gid' => $w['gid'],
            'sid' => $w['sid'],
            'mid' => $w['mid'],
        );
        $rel = $this->db->where($w)->get('player_info')->row_array();
        if($rel){//更新
            $ret = $this->db->set($data)->where($w)->update('player_info');
        }else{//新加
            $data['addtime'] = $time;
            $ret = $this->db->set($data)->insert('player_info');
        }
        return $ret;
    }

    /**
     * 玩家列表
     * @param array $val
     * @param array $field
     * @return mixed
     * @date
     */
    public function getPlayerList($val = array(),$field = array()){
        $w = $this->_getValue($field,$val);
        $where = "gid = {$w['gid']} and sid = {$w['sid']}";
        $field = "mid,st,ed,tname,phone,vip,dtype,os";

        if(isset($w['mid'])){
            $where .= " and mid = {$w['mid']}";
        }
        if(isset($w['st'])){
            $where .= " and etime >= {$w['st']}";
            if(isset($w['ed'])){
                $where .= " and etime < {$w['ed']}";
            }
        }
        if(isset($w['tname'])){
            $where .= " and tname = '{$w['tname']}'";
        }
        if(isset($w['phone'])){
            $where .= " and phone = '{$w['phone']}'";
        }
        if(isset($w['vip'])){
            $where .= " and vip = {$w['vip']}";
        }
        if(isset($w['dtype'])){
            $where .= " and device_detail = '{$w['dtype']}'";
        }
        if(isset($w['os'])){
            $where .= " and device_type like '%{$w['os']}%'";
        }
        $sort = '';
        if(isset($w['sort'])){//扩展
            $sort = " order by etime desc";
        }
        $limit = $this->getLimit('','','',1);
        $sql = "select * from bay_player_info WHERE {$where} {$sort} {$limit}";
        $ret = $this->db->query($sql)->result_array();
        $num = 0;
        if($ret){
            $_sql = "select count(*) as total from bay_player_info WHERE {$where}";
            $nrel = $this->db->query($_sql)->row_array();
            $num = $nrel['total'];
        }
        return array($ret,$num);

    }

    /**
     * 改变玩家信息
     * @param $val
     * @param $field
     * @return string
     * @date
     */
    public function changeInfo($val,$field){
        $w = $this->_getValue($field,$val);
        $where = array(
            'gid' => $w['gid'],
            'sid' => $w['sid'],
            'mid' => $w['mid'],
        );
        $d = array();
        if(isset($w['tname'])){
            $d['tname'] = $w['tname'];
        }else{
            $d['tname'] = '';
        }
        if(isset($w['phone'])){
            $d['phone'] = $w['phone'];
        }else{
            $w['phone'] = '';
        }
        if(!$d) return "没有信息要更新!";
        $ret = $this->db->where($where)->set($d)->update('player_info');
        return $ret;
    }


    //在线数据
    public function onlineData($val = array(),$field = array()){
//        $odb = $this->load->database('online',true);
        $w = $this->_getValue($field,$val);
        $where = "gid = {$w['gid']} and site_id in ({$w['sid']})";
        //请求
        $asql = "select count(*) as total from t_access_log where $where and access_clock >= {$w['st']} and  access_clock < {$w['ed']} ";
        $arel = $this->odb->query($asql)->row_array();
        $anum = $arel['total'];
        //接通
        $csql = "select count(*) as total from t_accept_log where $where and access_clock >= {$w['st']} and  access_clock < {$w['ed']} ";
        $crel = $this->odb->query($csql)->row_array();
        $cnum = $crel['total'];
        //排队未接通
        //归档数据
        $tsql = "select * from t_chat_session WHERE {$where} and session_type = 0 and clock >= {$w['st']} and  clock < {$w['ed']}";
        $trel = $this->odb->query($tsql)->result_array();
        $vip = 0;
        $total = 0;
        //初始化
        $type = array(0 => 0,1 => 0,2 => 0,3 => 0);
        $ret = array();
        $invalid = 0;
        if($trel){
            $total = count($trel);
            foreach($trel as $row){
                if($row['client_info']){
                    $detail = json_decode($row['client_info'],true);
                }else{
                    $detail = array();
                }
                if($detail){
                    if($detail['vip'] > 0){
                        $vip++;
                    }
                }
                if(in_array($row['archive_class'],array(0,1,2,3))){
                    $type[$row['archive_class']] = $type[$row['archive_class']] + 1;
                }
                if($row['invalid'] == 1) $invalid ++;
            }
            $ret = array(
                'type' => array(
                    'inv' => $type[0],
                    'com' => $type[1],
                    'adv' => $type[2],
                    'sug' => $type[3]
                ),
                'detail' => array(
                    'atotal' => $anum,
                    'accept' => $cnum,
                    'invalid' => $invalid,
                    'vip' => $vip,
                    'not_vip' => $total - $vip,
                    'total' => $total
                )
            );
        }
        return $ret;
    }


    //在线数据
    public function getOnlineChart($time,$_time,$val){
//        $odb = $this->load->database('online',true);
        $w = $val;
        $type = $w['type'];
        $where = "gid = {$w['gid']} and site_id in ({$w['sid']})";
        switch($type){
            case "access":
                $name = "请求分布图";
                $ttime = "access_clock";
                $sql = "select * from t_access_log where $where and access_clock >= {$w['st']} and  access_clock < {$w['ed']} ";
                break;
            case "accept":
                $name = "在线接通分布";
                $ttime = "access_clock";
                $sql = "select * from t_accept_log where $where and access_clock >= {$w['st']} and  access_clock < {$w['ed']} ";
                break;
            case "invalid":
                $name = "无效反馈分布";
                $ttime = "clock";
                $sql = "select * from t_chat_session where $where and invalid = 0 and clock >= {$w['st']} and  clock < {$w['ed']} ";
                break;
            case "vip":
                $name = "vip用户分布";
                $ttime = "clock";
                $sql = "select * from t_chat_session where $where and clock >= {$w['st']} and  clock < {$w['ed']} ";
                break;
            case "queue":
                $name = "排队未接通分布";
                $ttime = "endtime";
                $sql = "select * from kf_queuelog WHERE gid = {$w['gid']} and sid in ({$w['sid']}) AND endtime >= {$w['st']} and  endtime < {$w['ed']}";
                break;
        }
        if(!$sql){
            return "所选的类型不正确！";
        }
//        var_dump($sql);
        $rel = $this->odb->query($sql)->result_array();
//        var_dump($rel);
//        die;
        $ret = array();
        if($rel){
            $i = 0;
            $data = array();
            $len = count($_time) -1;
            foreach($rel as $row){
                $t = $row[$ttime];
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
                    'name' => $name,
                    'data' => $series
                );
            }
        }
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
//        $odb = $this->load->database('online',true);
        $w = $this->_getValue($field,$val);
        $where = '1';
        if(isset($w['gid'])){
            $where .= " and s.gid = {$w['gid']}";
        }
        if(isset($w['sid'])){
            $where .= " and s.site_id in ({$w['sid']})";
        }
        if(isset($w['st'])){
            $where .= " and s.clock >= ({$w['st']})";
            if(isset($w['ed'])){
                $where .= " and s.clock < ({$w['ed']})";
            }
        }
        $limit  = $this->getLimit('','','',1);

        $sql = "select s.*,r.respond_rating,r.attitude_rating,r.experience_rating from tmp_chat_session s LEFT JOIN t_rating_log r ON s.session_id = r.session_id WHERE {$where}  ORDER BY id DESC {$limit}";
        $_sql = "select count(*) as total from tmp_chat_session s WHERE {$where}";
//        var_dump($sql);die;
        $rel = $this->odb->query($sql)->result_array();
        $num = 0;
//        var_dump($sql);
//        var_dump($rel);die;
        $sdata = array();
        $num = 0;
        if($rel){
            $_rel = $this->odb->query($_sql)->row_array();
            $num = $_rel['total'];
            $session_id = array();
            foreach($rel as $row){
                $session_id[] = $row['session_id'];
                $sdata[$row['session_id']] = $row;
            }
            $str = implode(',',$session_id);
            $sql1 = "select * from tmp_chat_content WHERE session_id in ($str) ORDER BY id ASC";
            $rel1 = $this->odb->query($sql1)->result_array();
            if($rel1){
                foreach($rel1 as $row){
                    if(!isset($sdata[$row['session_id']]['content'])){//反馈时间
                        $sdata[$row['session_id']]['aclock'] = $row['clock'];
                    }
                    //服务时长
                    $sdata[$row['session_id']]['stime'] = $row['clock'] - $sdata[$row['session_id']]['aclock'];
                    $sdata[$row['session_id']]['content'][] = $row;
                }
            }
        }
        return array(array_values($sdata),$num);
    }

    /**
     * 举报数据
     * @param $val
     * @return array
     * @date
     */
    public function report($val){
//        $odb = $this->load->database('online',true);
        $w = $val;
        $where = "gid = {$w['gid']} and site_id in ({$w['sid']})";

        $asql = "select * from bay_archive  WHERE 1";
//        $asql = "select * from bay_archive  WHERE gid = {$w['gid']} and sid = {$w['sid']} and del = 0";
        $arel = $this->db->query($asql)->result_array();
        $aindx = array();
        if($arel){
            foreach($arel as $row){
                $aindx[$row['id']]['title'] = $row['title'];
            }
        }
        $sql = "select * from t_report where $where and clock >= {$w['st']} and  clock < {$w['ed']} ";
        $rel = $this->odb->query($sql . ' limit 100')->result_array();
        $ret = array();
        if($rel){
            $invalid = 0;
            $deal = 0;
            $type = array();
            $total = count($rel);
            foreach($rel as $row){

                if(!isset($type[$row['archive_category']])){
                    $type[$row['archive_category']] = 1;
                }else{
                    $type[$row['archive_category']] = $type[$row['archive_category']] + 1;
                }
                if($row['rely_clock']){
                    $deal++;
                }
                if($row['invalid']){
                    $invalid++;
                }

            }

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
//            var_dump($aindx);die;
            $data = array();
            if($type){
                foreach($type as $k => $r){
                    $data[] = array(
                        'value' => $r,
                        'name' => isset($aindx[$k]['title']) ? $aindx[$k]['title'] : ''
                    );
                }
            }

            $ret = array(
                'total' => $total,
                'invalid' => $invalid,
                'deal' => $deal,
                'series' => array(
                    'name' => '数据占比',
                    'data' => $data
                )
            );
        }
        return $ret;
    }


    //在线数据
    public function adviseChart($time,$_time,$val){
//        $odb = $this->load->database('online',true);
        $w = $val;
        $where = "a.gid = {$w['gid']} and a.site_id in ({$w['sid']})";
        $type = $w['type'];
        switch($type){
            case "total":
                $name = "留言总量";
                $sql = "select a.clock as reply_clock from t_advise as a WHERE {$where} AND clock >= {$w['st']} AND clock < {$w['ed']}";
                $sql2 =  "select r.reply_clock from t_advise_replies r INNER  JOIN t_advise a ON  r.advise_id  = a.id where $where and r.from_client = 0 and r.reply_clock >= {$w['st']} and  r.reply_clock < {$w['ed']}";
                break;
            case "invalid":
                $name = "无效总量";
                $sql = "select r.reply_clock from t_advise_replies r INNER  JOIN t_advise a ON  r.advise_id  = a.id where $where and r.invalid = 1 and r.reply_clock >= {$w['st']} and  r.reply_clock < {$w['ed']} ";
                break;
            case "deal":
                $name = "留言处理量";
                $sql = "select r.reply_clock from t_advise_replies r INNER  JOIN t_advise a ON  r.advise_id  = a.id where $where and r.from_client = 1 and r.reply_clock >= {$w['st']} and  r.reply_clock < {$w['ed']} ";
                break;
        }
        if(!$sql) return "所选类型不正确！";
        $rel = $this->odb->query($sql)->result_array();
        if($sql2){
            $rel2 = $this->odb->query($sql)->result_array();
            $rel = array_merge($rel,$rel2);
        }
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
            //数据处理
            if($data){
                $series = array();
                for($n = 0;$n < $len;$n++){
                    $str = $_time[$n] . '-' . $_time[$n+1];
//                        var_dump($row);die;
                    if(isset($data[$str])){
                        $score = $data[$str]['num'];
                        $series[] = $score;
                    }else{
                        $series[] = 0;
                    }
                }
                //组织数据
                array_pop($time);
                $ret['series']['categories'] = $time;
                $ret['series']['series'] = array(
                    'name' => $name,
                    'data' => $series
                );
            }
        }
        return $ret;
    }


    /**
     * 举报列表
     * @param $val
     * @param $field
     * @param int $type
     * @return array
     * @date
     */
    public function reportList($val,$field = array(),$type =  0){
//        $odb = $this->load->database('online',true);
        $w = $this->_getValue($field,$val);
        $where = '1';
        if(isset($w['gid'])){
            $where .= " and s.gid = {$w['gid']}";
        }
        if(isset($w['sid'])){
            $where .= " and s.site_id in ({$w['sid']})";
        }
        if(isset($w['st'])){
            $where .= " and s.clock >= ({$w['st']})";
            if(isset($w['ed'])){
                $where .= " and s.clock < ({$w['ed']})";
            }
        }
        $limit  = $this->getLimit('','','',1);

        $sql = "select s.* from t_report s WHERE {$where}  ORDER BY id DESC {$limit}";
        $_sql = "select count(*) as total from t_report s WHERE {$where}";
        $rel = $this->odb->query($sql)->result_array();
        $num = 0;
//        var_dump($sql);
//        var_dump($rel);die;
        if($rel){
            $_rel = $this->odb->query($_sql)->row_array();
            $num = $_rel['total'];
            /*$session_id = array();
            $sdata = array();
            foreach($rel as $row){
                $session_id[] = $row['session_id'];
                $sdata[$row['session_id']] = $row;
            }
            $str = implode(',',$session_id);
            $sql1 = "select * from tmp_chat_content WHERE session_id in ($str) ORDER BY id ASC";
            $rel1 = $this->odb->query($sql1)->result_array();
            if($rel1){
                foreach($rel1 as $row){
                    $sdata[$row['session_id']]['content'][] = $row;
                }
            }*/
        }
        return array(array_values($rel),$num);
    }


    //留言
    public function advise($val){
//        $odb = $this->load->database('online',true);
        $w = $val;
        $where = "a.gid = {$w['gid']} and a.site_id in ({$w['sid']})";
//        $rel = $this->db->query("select * from bay_archive where 1 limit 1")->result_array();die;

        $asql = "select * from bay_archive  WHERE gid = {$w['gid']} and sid in ({$w['sid']}) and del = 0";
        $arel = $this->db->query($asql)->result_array();
//        die;
//        var_dump($this->db);die;
//        $rel = $this->db->query("select * from bay_archive where 1 limit 1")->result_array();die;
//        $rel = $this->db->query("select * from bay_archive where 1 limit 1")->result_array();
        $aindx = array();
        $to = array();
        if($arel){
            foreach($arel as $row){
                $aindx[$row['id']]['title'] = $row['title'];
                $t = explode(',',$row['node']);
                $to[$row['id']] = $t[0];
            }
        }
        $ret = array();
        //反馈总量
        $total = 0;
        $sql = "select count(*) as total from t_advise as a WHERE {$where} AND clock >= {$w['st']} AND clock < {$w['ed']}";
        $rel = $this->odb->query($sql)->row_array();
        if($rel){
            $total = $total + $rel['total'];
        }
        $sql = "select count(*) as total from t_advise as a INNER JOIN t_advise_replies as r ON a.id = r.advise_id WHERE {$where} AND r.reply_clock >= {$w['st']} AND r.reply_clock < {$w['ed']} AND r.from_client = 1";
        $rel = $this->odb->query($sql)->row_array();
        if($rel){
            $total = $total + $rel['total'];
        }
        $ret['total'] = $total;

        //处理量
        $deal = 0;
        $sql = "select *  from t_advise as a INNER JOIN t_advise_replies as r ON a.id = r.advise_id WHERE {$where} AND r.reply_clock >= {$w['st']} AND r.reply_clock < {$w['ed']} AND r.from_client = 0
";
        $drel = $this->odb->query($sql)->result_array();
        if($drel){
            $deal = count($drel);
        }
        $ret['deal'] = $deal;
        //无效量
        $sql = "select count(*) as total from t_advise as a INNER JOIN t_advise_replies as r ON a.id = r.advise_id WHERE {$where} AND r.reply_clock >= {$w['st']} AND r.reply_clock < {$w['ed']} AND r.from_client = 0
";
        $invalid = 0;
        $rel = $this->odb->query($sql)->row_array();
        if($rel){
            $invalid = $total + $rel['total'];
        }
        $ret['invalid'] = $invalid;

        //超时
        $over = time() - 3600*24;
        $sql = "select r.reply_id from t_advise a INNER JOIN (select advise_id,reply_clock,reply_id from t_advise_replies order by reply_id desc) as  r ON a.id = r.advise_id WHERE r.reply_clock >= {$w['st']} and r.reply_clock < {$w['ed']} and r.reply_clock < {$over}  and a.archive_clock = 0 group by r.advise_id";
        $overTime = 0;
        $rel = $this->odb->query($sql);
        if($rel){
            $overTime = $overTime +count($rel);
        }
        $sql = "select * from t_advise a WHERE {$where} AND clock >= {$w['st']} AND clock < {$w['ed']} AND  clock < {$over}";
        $rel = $this->odb->query($sql)->result_array();
        if($rel){
            $overTime = $overTime +count($rel);
        }
        $ret['overTime'] = $overTime;

        //归档统计
        $cdata = array();
        if($drel){
            foreach($drel as $row){
                if(!isset($cdata[$to[$row['archive_category']]])){
                    $cdata[$to[$row['archive_category']]] = 1;
                }else{
                    $cdata[$to[$row['archive_category']]] = $cdata[$to[$row['archive_category']]] + 1;
                }
            }
        }

        if($cdata){
            $data = array();
            foreach($cdata as $key => $row){
                $data[] = array(
                    'value' => $row,
                    'name' => isset($aindx[$key]['title']) ? $aindx[$key]['title'] : ''
                );
            }
            $ret['pie'] = array(
                'name' => '数据占比',
                'data' => $data
            );
        }
        return $ret;
    }


    //在线数据
    /**
     *
     * @param $tt
     * @param $_time
     * @param $val
     * @return array
     * @date
     */
    public function reportChart($tt,$_time,$val){
//        $odb = $this->load->database('online',true);
        $w = $val;
        $where = "a.gid = {$w['gid']} and a.site_id in ({$w['sid']})";
        $type = $w['type'];
        $sql = array();
        $time = array();
        switch($type){
            case "total":
                $name = "投诉总量";
                $time[] = 'clock';
                $sql[] = "select *  from t_advise as a WHERE {$where} AND clock >= {$w['st']} AND clock < {$w['ed']}";
                $time[] = 'reply_clock';
                $sql2[] = "select *  from t_advise as a INNER JOIN t_advise_replies as r ON a.id = r.advise_id WHERE {$where} AND r.reply_clock >= {$w['st']} AND r.reply_clock < {$w['ed']} AND r.from_client = 1";
                break;
            case "invalid":
                $name = "无效举报";
                $time[] = 'reply_clock';
                $sql[] = "select *  from t_advise as a INNER JOIN t_advise_replies as r ON a.id = r.advise_id WHERE {$where} AND r.reply_clock >= {$w['st']} AND r.reply_clock < {$w['ed']} AND r.from_client = 0
";
                break;
            case "deal":
                $name = "处理量";
                $time[] = 'reply_clock';
                $sql[] = "select *  from t_advise as a INNER JOIN t_advise_replies as r ON a.id = r.advise_id WHERE {$where} AND r.reply_clock >= {$w['st']} AND r.reply_clock < {$w['ed']} AND r.from_client = 0
";
                break;
            case "over":
                $name = "超时反馈";
                $time[] = 'clock';
                $over = time() - 3600*24;
                $sql[] = "select * from t_advise as a WHERE {$where} AND clock >= {$w['st']} AND clock < {$w['ed']} AND  clock < {$over}";
                $time[] = 'reply_clock';
                $sql[] = "select * from t_advise as a INNER JOIN (select advise_id,reply_clock,reply_id from t_advise_replies order by reply_id desc) as  r ON a.id = r.advise_id WHERE r.reply_clock >= {$w['st']} and r.reply_clock < {$w['ed']} and r.reply_clock < {$over}  and a.archive_clock = 0 group by r.advise_id";
                break;
        }

        $data = array();
        $ret = array();
        if(!$sql) return "所选的类型不正确！";
        foreach($sql as $k => $s){
            $rel = $this->odb->query($s)->result_array();
            $ret = array();
            if($rel){
                $i = 0;
                $len = count($_time) -1;
                foreach($rel as $row){
                    $t = $row[$time[$k]];
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
            }
        }
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
            $ret['series']['categories'] = $tt;
            $ret['series']['series'] = array(
                'name' => $name,
                'data' => $series
            );
        }

        return $ret;
    }


    /**
     * 留言列表
     * @param $val
     * @param $field
     * @param int $type
     * @return array
     * @date
     */
    public function adviseList($val,$field = array(),$type =  0){
//        $odb = $this->load->database('online',true);
        $w = $this->_getValue($field,$val);
        $where = '1';
        if(isset($w['gid'])){
            $where .= " and a.gid = {$w['gid']}";
        }
        if(isset($w['sid'])){
            $where .= " and a.site_id in ({$w['sid']})";
        }
        if(isset($w['st']) && isset($w['ed'])){
            $where .= " and (( a.clock >= {$w['st']} and a.clock < {$w['ed']}) OR (r.reply_clock >= {$w['st']} and r.reply_clock < {$w['ed']}))";
        }
        $limit  = $this->getLimit('','','',1);

        $sql = "select a.* from t_advise a LEFT JOIN t_advise_replies r ON r.advise_id = a.id WHERE {$where}  ORDER BY id DESC {$limit}";
//        $sql = "select r.* from t_advise_replies r INNER JOIN t_advise a  ON r.advise_id = a.id WHERE {$where}  ORDER BY id DESC {$limit}";

        $_sql = "select count(*) as total from t_advise a LEFT JOIN t_advise_replies r ON r.advise_id = a.id WHERE {$where}";
        $rel = $this->odb->query($sql)->result_array();
//        var_dump($rel);die;
        $num = 0;
        $ret = array();
//        var_dump($rel);die;
        if($rel){
            $_rel = $this->odb->query($_sql)->row_array();
            $num = $_rel['total'];
            $advise_id = array();
            foreach($rel as $row){
                if(!in_array($row['advise_id'],$advise_id))$advise_id[] = $row['id'];
            }
            $str = implode(',',$advise_id);
//            var_dump($str);die;
            $sql1 = "select a.content,a.client_id,a.clock as fclock,a.archive_category as agory,a.archive_clock as aclock,r.* from t_advise a INNER JOIN t_advise_replies r  WHERE a.id = r.advise_id AND a.id in ($str) ORDER BY reply_id ASC";
            $rel1 = $this->odb->query($sql1)->result_array();
//            var_dump($rel1);die;
            $tmp = array();
            if($rel1){
                foreach($rel1 as $row){
                    if(!isset($tmp[$row['advise_id']])){//第一行类型
                        $tmp[$row['advise_id']][] = array(
                            'from_client' => 1,
                            'msg' => $row['content'],
                            'service_id' => 0,
                            'client_id' => $row['client_id'],
                            'clock' => $row['fclock'],
                            'reply_id' => 0,
                        );
                    }
                    $tmp[$row['advise_id']][] = array(
                        'from_client' => $row['from_client'],
                        'msg' => $row['reply'],
                        'service_id' => $row['service_id'],
                        'client_id' => $row['client_id'],
                        'clock' => $row['reply_clock'],
                        'reply_id' => $row['reply_id']
                    );
                }
            }
//            var_dump($rel);die;
            foreach($rel as $row){
                $ret[$row['id']] = $row;
                $ret[$row['id']]['content'] = isset($tmp[$row['id']]) ? $tmp[$row['id']] : array();
            }
        }
        return array(array_values($ret),$num);
    }

    //留言列表
    public function getAdvise($field,$val){
//        $odb = $this->load->database('online',true);
        $w = $this->_getValue($field,$val);
        $where = "a.gid = {$w['gid']}";
        if(isset($w['sid'])){
            $where .= "  and a.site_id in ({$w['sid']}) ";
        }
        if(isset($w['ast']) && isset($w['aed'])){
            $where .= " and (( a.clock >= {$w['ast']} and a.clock < {$w['aed']}) OR (r.reply_clock >= {$w['ast']} and r.reply_clock < {$w['aed']}) and r.from_client = 1)";
        }
        if(isset($w['rst']) && isset($w['red'])){
            $where .= " and ((r.reply_clock >= {$w['ast']} and r.reply_clock < {$w['aed']}) and r.from_client = 0)";
        }
        if(isset($w['id'])){
            $where .= " and a.id = {$w['id']}";
        }
        if(isset($w['mid'])){
            $where .= " and a.client_id = {$w['mid']}";
        }
        if(isset($w['vip'])){
            $where .= " and a.vip = {$w['vip']}";
        }
        if(isset($w['status'])){
            if($w['status'] == 'd'){//认领未处理
                $where .= " and a.status = 0 and a.claim_flag != 0";
            }elseif($w['status'] == 'n'){//认领已处理
                $where .= " and a.status != 0 and a.claim_flag != 0";
            }else{
                $where .= " and a.status = {$w['status']}";
            }
        }
        if(isset($w['my'])){
            $where .= " and r.service_id = {$this->_uid}";
        }elseif(isset($w['uid'])){
            $where .= " and r.service_id in ({$w['uid']})";
        }
        $limit  = $this->getLimit('','','',1);
        $source = "select DISTINCT (a.id) from t_advise a LEFT JOIN t_advise_replies r ON r.advise_id = a.id WHERE  {$where}";
        $sql = $source . " ORDER BY id DESC {$limit}";
        $_sql = "select count(*) as total from ($source) as s";


       /* $_sql = "select count(*) as total from t_advise a LEFT JOIN t_advise_replies r ON r.advise_id = a.id WHERE {$where}  ORDER BY id DESC {$limit}";*/
        $rel = $this->odb->query($sql)->result_array();
        $num = 0;
        $ret = array();
//        var_dump($rel);die;
        if($rel){
            $_rel = $this->odb->query($_sql)->row_array();
            $num = $_rel['total'];
            $advise_id = array();
            foreach($rel as $row){
                if(!in_array($row['id'],$advise_id))$advise_id[] = $row['id'];
            }
            $str = implode(',',$advise_id);

            $sql1 = "select a.*,r.reply as rr,r.reply_id,r.from_client,r.reply_clock as rc,r.service_id as rs from t_advise a LEFT JOIN t_advise_replies r ON a.id = r.advise_id WHERE  a.id in ($str) ORDER BY reply_id ASC";
            $rel1 = $this->odb->query($sql1)->result_array();
//            var_dump($rel1);die;
            if($rel1){
                $tmp = array();
                $client = array();
                foreach($rel1 as $r){
//                    var_dump($r);die;
                    if(!isset($tmp[$r['id']])){//第一行类型
                        $tmp[$r['id']][] = array(
                            'from_client' => 1,
                            'msg' => $r['content'],
                            'clock' => $r['reply_clock'],
                            'reply_id' => 0,
                            'service_id' => 0
                        );
                    }
                    if($r['reply_id']){//如果回复不空
                        $tmp[$r['id']][] = array(
                            'from_client' => $r['from_client'],
                            'msg' => $r['rr'],
                            'clock' => $r['rc'],
                            'reply_id' => $r['reply_id'],
                            'service_id' => $r['rs']
                        );
                    }
                    if(!isset($client[$r['id']])) $client[$r['id']] = $r;
                }
//                var_dump($rel);
//                var_dump($rel);die;
                $adata = array();
                foreach($rel as $row){
                    $ret[$row['id']]['content'] = $tmp[$row['id']];
                    $ret[$row['id']]['id'] = $row['id'];
                    $ret[$row['id']]['other'] = $client[$row['id']];
                    if($client[$row['id']]['archive_category']){
                        if(!in_array($client[$row['id']]['archive_category'],$adata)){
                            $adata[] = $client[$row['id']]['archive_category'];
                        }
                    }
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
                        if($row['other']['archive_category']){
                            $row['other']['cate_name'] = implode(',',fun::reflect($row['other']['archive_category'],$aindex));
                        }else{
                            $row['other']['cate_name'] = '';
                        }
                    }
                }
            }
        }
        return array(array_values($ret),$num);
    }


    //留言回复
    public function adviseReply($field = array(),$val = array()){
//        $odb = $this->load->database('online',true);
        $w = $this->_getValue($field,$val);

        $time = time();
        //replies 表新建一条数据
        $data = array(
            'advise_id' => $w['id'],
            'from_client' => 0,
            'reply' => $w['content'],
            'reply_clock' => $time,
            'service_id' => $this->_uid,
            'archive_class' => isset($w['class']) ? $w['class'] : 0,
            'archive_category' => $w['aid'],
            'archive_clock' => $time
        );
        $this->odb->trans_start();
        $this->odb->set($data)->insert('t_advise_replies');
        $d = array(
            'reply_clock' => $time,
            'archive_class' => isset($w['class']) ? $w['class'] : 0,
            'archive_category' => $w['aid'],
            'status' => 1,
            'invalid' => isset($w['invalid']) ? $w['invalid'] : 0
        );
        $this->odb->set($d)->where('id',$w['id'])->update('t_advise');
        $this->odb->trans_complete();
        if ($this->odb->trans_status() === FALSE){//失败
            return "操作失败，请重试";
        }else{//成功
            return true;
        }
    }

    /**
     * 留言修改
     * @param $val
     * @return mixed
     * @date
     */
    public function editAdvise($val){
//        $odb = $this->load->database('online',true);
        $w = $this->_getValue(array(),$val);
        $data = array(
            'reply' => $w['content'],
            'service_id' => $this->_uid,
            'reply_clock' => time()
        );
        $ret = $this->odb->set($data)->where('reply_id',$w['rid'])->update('t_advise_replies');
        return $ret;
    }

    /**
     * 忽略留言
     * @param $id
     * @return mixed
     * @date
     */
    public function ignoreAdvise($id){
//        $odb = $this->load->database('online',true);
        $time = time();
        $id = explode(',',$id);
        $d = array(
            'reply_clock' => $time,
            'archive_clock' => $time,
            'status' => 2,
            'invalid' => 1,
            'service_id' => $this->_uid,
        );
        $ret = $this->odb->set($d)->where_in('id',$id)->update('t_advise');
        return $ret;
    }

    /**
     * 举报列表
     * @param $field
     * @param $val
     * @return array
     * @date
     */
    public function getReport($field,$val){
//        $odb = $this->load->database('online',true);
        $w = $this->_getValue($field,$val);
        $where = "gid = {$w['gid']}";
        if(isset($w['sid'])){
            $where .= " and site_id in ({$w['sid']})";
        }
        if(isset($w['ast']) && isset($w['aed'])){
            $where .= " and  clock >= {$w['ast']} and clock < {$w['aed']}";
        }
        if(isset($w['rst']) && isset($w['red'])){
            $where .= " and reply_clock >= {$w['ast']} and reply_clock < {$w['aed']}";
        }
        if(isset($w['id'])){
            $where .= " and id = {$w['id']}";
        }
        if(isset($w['mid'])){
            $where .= " and client_id = {$w['mid']}";
        }
        if(isset($w['vip'])){
            $where .= " and vip = {$w['vip']}";
        }
        if(isset($w['status'])){
            if($w['status'] == 'd'){//认领未处理
                $where .= " and status = 0 and claim_flag != 0";
            }elseif($w['status'] == 'n'){//认领已处理
                $where .= " and status != 0 and claim_flag != 0";
            }else{
                $where .= " and status = {$w['status']}";
            }
        }
        if(isset($w['my'])){
            $where .= " and service_id = {$this->_uid}";
        }elseif(isset($w['uid'])){
            $where .= " and service_id in ({$w['uid']})";
        }
        if(isset($w['sort'])){
            $sort = " order by id desc";
        }else{
            $sort = " order by id desc";
        }
        $limit  = $this->getLimit('','','',1);
        $sql = "select * from t_report WHERE  {$where} {$sort} {$limit}";
        $_sql = "select count(*) as total from t_report WHERE {$where}";


        /* $_sql = "select count(*) as total from t_advise a LEFT JOIN t_advise_replies r ON r.advise_id = a.id WHERE {$where}  ORDER BY id DESC {$limit}";*/
        $ret = $this->odb->query($sql)->result_array();
        $num = 0;
//        var_dump($rel);die;
        $adata = array();
        if($ret){
            $_rel = $this->odb->query($_sql)->row_array();
            $num = $_rel['total'];
            foreach($ret as $r){
                if($r['archive_category']){
                    if(!in_array($r['archive_category'],$adata)){
                    $adata[] = $r['archive_category'];
                    }
                }
            }
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
        return array(array_values($ret),$num);
    }

    /**
     * 举报回复
     * @param array $field
     * @param array $val
     * @return mixed
     * @date
     */
    public function reportReply($field = array(),$val = array()){
//        $odb = $this->load->database('online',true);
        $w = $this->_getValue($field,$val);
        $time = time();
        $d = array(
            'reply_clock' => $time,
            'archive_class' => isset($w['class']) ? $w['class'] : 0,
            'archive_category' => $w['aid'],
            'status' => 1,
            'archive_clock' => $time,
            'invalid' => isset($w['invalid']) ? $w['invalid'] : 0,
            'service_id' => $this->_uid,
            'reply' => $w['content'],
        );
        $ret = $this->odb->set($d)->where('id',$w['id'])->update('t_report');
        return $ret;
    }

    /**
     * 举报修改
     * @param array $field
     * @param array $val
     * @return mixed
     * @date
     */
    public function editReport($field = array(),$val = array()){
//        $odb = $this->load->database('online',true);
        $w = $this->_getValue($field,$val);
        $time = time();
        $d = array(
            'reply_clock' => $time,
            'service_id' => $this->_uid,
            'reply' => $w['content'],
        );
        $ret = $this->odb->set($d)->where('id',$w['id'])->update('t_report');
        return $ret;
    }

    /**
     * 举报忽略
     * @param $id
     * @return mixed
     * @date
     */
    public function ignoreReport($id){
//        $odb = $this->load->database('online',true);
        $time = time();
        $id = explode(',',$id);
        $d = array(
            'reply_clock' => $time,
            'archive_clock' => $time,
            'status' => 2,
            'invalid' => 1,
            'service_id' => $this->_uid,
        );
        $ret = $this->odb->set($d)->where_in('id',$id)->update('t_report');
        return $ret;
    }

    /**
     * 盗号申述列表
     * @param $field
     * @param $val
     * @return array
     * @date
     */
    public function getLost($field = array(),$val = array()){
//        $odb = $this->load->database('online',true);
        $w = $this->_getValue($field,$val);
        $where = "gid = {$w['gid']}";
        if(isset($w['sid'])){
            $where .= " and sid in ({$w['sid']})";
        }
        if(isset($w['ast'])){
            $where .= " and  addtime >= {$w['ast']}";
            if(isset($w['aed'])){
                $where .= " and addtime < {$w['aed']}";
            }
        }
        if(isset($w['dst']) && isset($w['ded'])){
            $where .= " and statustime >= {$w['ast']}";
            if(isset($w['aed'])){
                $where .= " and statustime < {$w['aed']}";
            }
        }
        if(isset($w['mid'])){
            $where .= " and mid = {$w['mid']}";
        }
        if(isset($w['sort'])){
            $sort = " order by id desc";
        }else{
            $sort = " order by id desc";
        }

        $limit  = $this->getLimit('','','',1);
        $sql = "select * from kf_lostback WHERE  {$where} {$sort} {$limit}";
        $_sql = "select count(*) as total from kf_lostback WHERE {$where}";


        /* $_sql = "select count(*) as total from t_advise a LEFT JOIN t_advise_replies r ON r.advise_id = a.id WHERE {$where}  ORDER BY id DESC {$limit}";*/
        $ret = $this->odb->query($sql)->result_array();
        $num = 0;
//        var_dump($rel);die;
        $adata = array();
        if($ret){
            $_rel = $this->odb->query($_sql)->row_array();
            $num = $_rel['total'];
        }
        return array(array_values($ret),$num);
    }

    /**
     * 申述处理
     * @date
     */
    public function dealLost($field = array(),$val = array()){
        $w = $this->_getValue($field,$val);
        $id = $w['id'];
        $d = array(
            'reply' => isset($w['reply']) ? stripslashes($w['reply']) : '',
            'status' => $w['status'],
            'statustime' => time(),
            'uid' => $this->_uid
        );
        if(isset($w['findchip'])){
            $d['findchip'] = $w['findchip'];
        }
        $ret = $this->odb->set($d)->where('id',$id)->update('kf_lostback');
//        var_dump($ret);die;
        return $ret;
    }

    /**
     * 设置错误信息
     * @param $id
     * @param $err
     * @return mixed
     * @date
     */
    public function setInfo($id,$err){
        $ret = $this->odb->set('errinfo',$err)->where('id',$id)->update('kf_lostback');
        return $ret;
    }


}