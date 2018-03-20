<?php

/**
 * 归档
 * Class Archive_model
 * @author harvey
 * @Date: ${DAY}
 * @license  http://www.boyaa.com/
 */
class SynData_model extends Bmax_Model {
    private $apiUrl;
    private $_apiUrl = array(
        'test' => array(
            1 => 'http://kfapi2-test.oa.com/',
            2 => 'http://kfapi-test.oa.com/'
        ),
        'pre' => array(
            1 => 'http://kfapi-pre.boyaagame.com/',
            2 => 'http://kfapi1-pre.boyaagame.com/'
        ),
        'production' => array(
            1 => 'http://service2.boyaagame.com/',
            2 => 'http://service.boyaagame.com/'
        )
    );

    //配置同步表
    private $_confTable = array(
        'bay_users',
        'bay_keyword',
        'bay_mbfaq',
        'bay_mbfaqcate',
        'bay_robot',
        'bay_archive'
    );
    //配置同步表最后更新时间段字段名称
    private $_tableEtime = array(
        'diy_users' => 'etime',
        'bay_keyword' => 'etime',
        'bay_mbfaq' => 'etime',
        'bay_mbfaqcate' => 'etime',
        'bay_robot' => 'etime',
        'bay_archive' => 'etime',
    );
    public function __construct()
    {

        parent::__construct();

        $this->load->database();
        $ev = fun::get('evm');
        if($ev == 'test' || ENVIRONMENT == 'test' || ENVIRONMENT == 'vm'){
            $this->apiUrl = $this->_apiUrl['test'];
        }elseif($ev == 'pre' || ENVIRONMENT == 'pre'){
            $this->apiUrl = $this->_apiUrl['pre'];
        }elseif($ev == 'pdt' || ENVIRONMENT == 'production'){
            $this->apiUrl = $this->_apiUrl['production'];
        }else{
            die('evn arg not right!');
        }
    }


    /**
     * 获取旧的没有同步完成的同步数据
     * @return array
     * @date
     */
    public function getOldSyn(){
        $sql = "select * from bay_slog WHERE done != 1";
        $rel = $this->db->query($sql)->result_array();
        $ret = array();
        if($rel){
            foreach($rel as $row){
                $ret[] = array(
                    'st' => $row['starttime'],
                    'ed' => $row['endtime'],
                    'id' => $row['id']
                );
            }
        }
        return $ret;
    }

    /**
     * 处理发送数据
     * @param $stime                    string  开始时间
     * @param $etime                    string  结束时间
     * * @param $table                  array|string   数据表(不传取默认的同步表）
     * @param $lastid                   bool|false slog id ,如果有传，标识是同步以前没同步完成的数据
     * @return string
     * @date
     */
    public function delSql($stime,$etime,$table = array(),$lastid = false){
        $num = 0;
        $sql = array();
        if(!$table) $table = $this->_confTable;
        foreach($table as $t){
            //编辑时间字段，没有就用默认的“etime”
            $ef = $this->_tableEtime[$t] ? $this->_tableEtime[$t] : '';
            $rel = $this->getSendRel($t,$stime,$etime,$ef);
            //组装SQL
            if($rel){
                if(strpos($t,'.') !== false) $t = ltrim(strchr($t,'.'),'.');//跨库表处理
                //取表结构
                $col = $this->getColumn($t);
                foreach($rel as $key => $v){
                    $str = "REPLACE INTO {$t} SET";
                    foreach($col as $k => $r){
                        $str .= "`{$r}` = '" . fun::magic_quote($v[$r]) ."',";
                    }
                    $sql['sql'.$num] = rtrim($str,',');
                    $num++;
                }
            }
        }
        $last_done = false;//完成标记
        if(empty($sql)){
            $msg = "时间：" . date("Y-m-d H:i:s") . "该次没有数据可以同步~\n\r";
//            echo $msg;
            fun::log($msg,'cron');
            //如果是从表里拉取的时间段内没有数据同步，应该是时间修改了，完成标记为成功
            if($lastid){
                $last_done = true;
            }
        }else{
            list($ret,$msg) = $this->sendSql($sql);
            if($lastid){//同步旧的
                $last_done = $ret;
            }else{//新的同步
                if(!$ret){//同步异常，保存下一次继续同步
                    $sql = "insert into bay_slog SET starttime = {$stime} ,endtime = {$etime}";
                    $this->db->query($sql);
                }
            }
//            echo ($msg);
        }
        if($last_done){//旧同步成功，改变记录
            $sql = "update  bay_slog SET done = 1 WHERE id = {$lastid}";
            $this->db->query($sql);
        }
        return $msg;
    }

    /**
     * 获取表的字段名（注意，有两张以上一名字的表，但是字段不一样，在不同的数据库里，会出错）
     * @param $table
     * @return array
     * @date
     */
    private function getColumn($table){
        if(strpos($table,'.') !== false) $table = ltrim(strchr($table,'.'),'.');//跨库表处理
        $table = trim($table,'`');
        $sql = "select COLUMN_NAME from information_schema.COLUMNS where table_name = '{$table}'";
        $ret = $this->db->query($sql)->result_array();
        $col = array();
        if($ret){
            foreach($ret as $row){
                if(in_array($row['COLUMN_NAME'],$col)) continue;//去重
                $col[] = $row['COLUMN_NAME'];
            }
        }
        return $col;
    }

    /**
     * 根据条件获取同步数据
     * @param $table
     * @param $time
     * @param int $endtime
     * @param string $etime
     * @return array|bool
     * @date
     */
    private function getSendRel($table,$time,$endtime = 0,$etime = "etime"){
        //分离时间多条件(添加时间,修改时间)
        if(!$etime) $etime = 'etime';
        $tmp = explode(',',$etime);
        $where = '';
        foreach($tmp as $key => $r){
            if($key > 0) $where .= " or ";
            $where .= "({$r} > {$time}";
            if($endtime){
                $where .= " and {$r} < {$endtime}";
            }
            $where .= ")";
        }

        $sql = "select * from {$table} WHERE {$where}";
        return $this->db->query($sql)->result_array();
    }

    /**
     * 发送sql语句到目标server同步
     * @param $sql
     * @return array
     * @date
     */
    private function sendSql($sql){
        $sec = "mN3@UCoYGj%fnzUs";
        $time = time();
        $num = count($sql);
        $key = md5($sec . $time . $num);
        $sql['time'] = $time;
        $sql['key'] = $key;
        $sql['num'] = $num;
        $url = $this->apiUrl;
        $hasSend = array();
        $msg = "时间：" . date("Y-m-d H:i:s") ."发送了({$num})条数据,\n\r";
        $ret = true;
        foreach($url as $key =>  $row){
            if($key == 1){
                $str = "国内";
            }elseif($key == 2){
                $str = "国外";
            }

            if(in_array($row,$hasSend)) continue;//重复url不重复发送
            $hasSend[] = $row;
            $data = array(
                "url" => $row . 'api/code.php?cmd=sendData',
                "data" => $sql,
            );
//        var_dump($data);die;
            $rel = fun::curlHttp($data,'post');
            if($rel['code'] == 200){
                $temp = explode('|',$rel['data']);
                if($temp[0] == 'ok=1'){
                    $msg .= $str . "同步了(" . $temp[1] .")条数据,\n\r";
                    if($temp[1] != $num){//只要有一个环境没同步成功，或者数目对不上，就标识为失败
                        $ret = false;
                    }
                }else{
                    $ret = false;//只有
                    $msg .= $str . "同步数据失败了,\n\r";
                }
            }else{
                $ret = false;
                $msg .= $str . "同步数据失败了,\n\r";
            }
        }
        fun::log($msg,'cron');
        return array($ret,$msg);
    }

    /**
     * 刷新远程redis
     * @return bool
     * @date
     */
    public function flushRedis(){
        $ret = false;
        foreach($this->apiUrl as $row){
            $data = array(
                "url" => $row . 'api/code.php?cmd=setRedis&hand=1',
            );
            $rel = fun::curlHttp($data);
            if($rel['code'] == 200){
                if(strpos($rel['data'],'start Artificial end!') !== false){
                    $ret = true;
                }
            }
        }
        return $ret;
    }
}


