<?php

/**
 * crontab model
 * Class Cron_model
 * @author harvey
 * @Date: 17-2-15
 * @license  http://www.boyaa.com/
 */
class Cron_model extends Bmax_Model {
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
     * 获取旧DB连接
     * @date
     */
    private function getODb(){
        if(!$this->odb){
            $this->odb = $this->load->database('online',true);
        }
    }

    //检查是否及已经有一条设置了
    public function hasSet($gid,$sid){
        $sql = "SELECT * FROM bay_robot WHERE gid = {$gid} and sid = {$sid}";
        $query = $this->db->query($sql);
        if($query->result()){
            return true;
        }else{
            return false;
        }
    }


}