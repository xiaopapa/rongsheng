<?php
/**
 * 后台设置model
 * class Setting_model
 * Created by PhpStorm.
 * User: HarveyYang
 * Date: 2017/9/28
 * Time: 18:41
 */

class Setting_model extends Bmax_Model {
    private $odb;
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->odb = $this->load->database('online',true);
    }

    //翻译列表
    public function langList($field = array()){
        $w = $this->_getValue($field);
        $where = array();
        if(isset($w['pack'])){
            $where['pack'] = $w['pack'];
        }
        if(isset($w['tag'])){
            $where['tag'] = $w['tag'];
        }
        if($where) $this->odb->where($where);
        $this->odb->from('bay_language');
        $num = $this->odb->count_all_results('',false);
        $this->getLimit($this->odb,'','');
        $ret = $this->odb->order_by('id','desc')->get()->result_array();
        return array($ret,$num);
    }

    //新建语言翻译
    public function newLang($field = array(),$val = array()){

        $w = $this->_getValue($field,$val,$this->db);
        $where = array(
            'pack' => $w['pack'],
            'tag' => $w['tag']
        );
        $rel = $this->db->where($where)->get('lang_pack')->row_array();
        if($rel) return "已经有这么一个翻译！";
        $d = array(
            'etime' => time(),
            'editor' => $this->_uid
        );
        $ret = $this->db->set($d)->insert('lang_pack');
        return $ret;

        $w = $this->_getValue($field,$val,$this->db);
        $where = array(
            'pack' => $w['pack'],
            'tag' => $w['tag']
        );
        $rel = $this->odb->where($where)->get('bay_language')->row_array();
        if($rel) return "已经有这么一个翻译！";
        $d = array(
            'etime' => time(),
            'editor' => $this->_uid
        );
        $ret = $this->odb->set($d)->insert('bay_language');
        return $ret;
    }

    /**
     * 修改一条翻译
     * @param $id
     * @param $field
     * @param $arg
     * @return string
     * @date
     */
    public function editLang($id,$field,$arg = array()){
        $val = array();
        if($arg['pack'] && $arg['tag']){
            $where = array(
                'pack' => $arg['pack'],
                'tag' => $arg['tag']
            );
            $rel = $this->odb->where($where)->where("id != {$id}")->get('language')->row_array();
            if($rel) return "已经有这么一个翻译,不能修用这个tag标识！";
            $val['tag'] = $arg['tag'];
        }
        $w = $this->_getValue($field,array(),$this->db);
        $ret = $this->odb->where('id',$id)->update('bay_language');
        return $ret;
    }


    //语言包列表
    public function packList($field = array()){
        $w = $this->_getValue($field);
        $where = array();
        if(isset($w['pack'])){
            $where['pack'] = $w['pack'];
        }
        if(isset($w['tag'])){
            $where['tag'] = $w['tag'];
        }
        if(isset($w['content'])){
            $this->db->like('lan',$w['content']);
        }
        /*if(isset($w['sort'])){//这里可以扩展
            $order = "";
        }else{
            $order = "order by id desc";
        }*/
        if($where) $this->db->where($where);
        $this->db->from('lang_pack');
        $num = $this->db->count_all_results('',false);
        $this->getLimit($this->db,'','');
        $ret = $this->db->order_by('id','desc')->get()->result_array();
        return array($ret,$num);
    }

    //新建后台语言
    public function newPack($field = array(),$val = array()){
        $w = $this->_getValue($field,$val,$this->db);
        $where = array(
            'pack' => $w['pack'],
            'tag' => $w['tag']
        );
        $rel = $this->db->where($where)->get('lang_pack')->row_array();
        if($rel) return "已经有这么一个翻译！";
        $d = array(
            'etime' => time(),
            'editor' => $this->_uid
        );
        $ret = $this->db->set($d)->insert('lang_pack');
        return $ret;
    }

    /**
     * 修改一条翻译
     * @param $id
     * @param $field
     * @param $arg
     * @return string
     * @date
     */
    public function editPack($id,$field,$arg = array()){
        $val = array();
        if($arg['pack'] && $arg['tag']){
//            var_dump($arg['pack']);
//            var_dump($arg['tag']);
            $where = array(
                'pack' => $arg['pack'],
                'tag' => $arg['tag']
            );
            $rel = $this->db->where($where)->where("id != {$id}")->get('lang_pack')->row_array();
//            var_dump($this->db->last_query());die;
            if($rel) return "已经有这么一个翻译,不能修用这个tag标识！";
            $val['tag'] = $arg['tag'];
        }
        $w = $this->_getValue($field,$val,$this->db);
        $ret = $this->db->where('id',$id)->update('lang_pack');
        return $ret;
    }

    //语言包列表
    public function packName(){
        $ret = $this->db->where('del != 1')->get("pack_name")->result_array();
        return $ret;
    }
    //新建一个语言包标识
    public function newPackName($pack,$name){
        $rel = $this->db->where('pack',$pack)->get("pack_name")->row_array();
//        var_dump($rel);
//        var_dump($this->db->last_query());die;
        if($rel){
            return "已经有这么一语言包,请不要重复添加！";
        }
        $d = array(
            'pack' => $pack,
            'name' => $name,
            'editor' => $this->_uid,
            'etime' => time()
        );
        $ret = $this->db->set($d)->insert("pack_name");
        return $ret;
    }

    //修改(改名字)、删除、删除恢复
    public function editPackName($field = array(),$val = array()){
        $w = $this->_getValue($field,$val);
        $d  = array(
            'editor' => $this->_uid,
            'etime' => time()
        );
        if(isset($w['del'])){//删除
            $d['del'] = $w['del'];
        }else{//修改
            $d['name'] = $w['name'];
        }
        $ret = $this->db->set($d)->where('id',$w['id'])->update('pack_name');
        return $ret;
    }

    //语言包列表
    public function langName(){
        $ret = $this->odb->where('del != 1')->get("bay_lang_name")->result_array();
        return $ret;
    }
    //新建一个语言包标识
    public function newLangName($pack,$name){
        $rel = $this->odb->where('pack',$pack)->get("bay_lang_name")->row_array();
        if($rel){
            return "已经有这么一语言包,请不要重复添加！";
        }
        $d = array(
            'pack' => $pack,
            'name' => $name,
            'editor' => $this->_uid,
            'etime' => time()
        );
        $ret = $this->odb->set($d)->insert("bay_lang_name");
        return $ret;
    }

    //修改(改名字)、删除、删除恢复
    public function editLangName($field = array(),$val = array()){
        $w = $this->_getValue($field,$val);
        $d  = array(
            'editor' => $this->_uid,
            'etime' => time()
        );
        if(isset($w['del'])){//删除
            $d['del'] = $w['del'];
        }else{//修改
            $d['name'] = $w['name'];
        }
        $ret = $this->odb->set($d)->where('id',$w['id'])->update('bay_lang_name');
        return $ret;
    }

    //模块列表
    public function moduleList($field = array(),$val = array()){
        $w = $this->_getValue($field,$val);
        $d = array(
            'gid' => $w['gid'],
            'sid' => $w['sid']
        );
        $ret = $this->odb->where($d)->get('kf_module')->result_array();
        return $ret;
    }

    //修改模块
    public function editModule($field = array(),$val = array()){
        $w = $this->_getValue($field,$val);
        $d = array(
            'gid' => $w['gid'],
            'sid' => $w['sid']
        );
        $data = array(
            'robot' => $w['robot'],
            'online' => $w['online'],
            'lostback' => $w['lostback'],
            'report' => $w['report'],
            'advise' => $w['advise']
        );
        if(isset($w['robotpic'])){
            $data['robotpic'] = $w['/'];
        }
        if(isset($w['servicepic'])){
            $data['servicepic'] = $w['servicepic'];
        }
        $rel = $this->odb->where($d)->get('kf_module')->row_array();
        if($rel){//修改
            $ret = $this->odb->set($data)->where($d)->update('kf_module');
        }else{//新增
            $data['gid'] = $w['gid'];
            $data['sid'] = $w['sid'];
            $ret = $this->odb->set($data)->insert('kf_module');
        }
        return $ret;
    }

    /**
     * 客服在线设置
     * @param $gid
     * @param bool|false $sid
     * @return mixed
     * @date
     */
    public function serviceList($gid,$sid = false){
        $w = array(
            'gid' => $gid
        );
        if($sid !== false){
            $w['sid'] = $sid;
        }
        $ret = array();
        $rel = $this->odb->where($w)->get("bay_service_set")->result_array();
        if($rel){
            foreach($rel as $row){
                if($row['type'] == 1){
                    $ret['sid'] = $row['sid'];
                    $ret['gid'] = $row['gid'];
                    $ret['awtime'] = $row['wtime'];
                    $ret['agtime'] = $row['gtime'];
                    $ret['anotice'] = $row['notice'];
                    $ret['astatus'] = $row['status'];
                    $ret['editor'] = $row['editor'];
                    $ret['etime'] = $row['etime'];
                }elseif($row['type'] == 2){
                    $ret['bwtime'] = $row['wtime'];
                    $ret['bgtime'] = $row['gtime'];
                    $ret['bnotice'] = $row['notice'];
                    $ret['bstatus'] = $row['status'];
                    $ret['st'] = $row['st'];
                    $ret['ed'] = $row['ed'];
                }
            }
        }
        return $ret;
    }

    //新增、编辑一条在线设置
    public function editService($filed,$val){
        $w = $this->_getValue($filed,$val);

        $this->odb->trans_start();
        //平常
        if(isset($w['astatus'])){
            if(isset($w['astatus'])){//平常设置
                $data = array(
                    "wtime" => $w['awtime'],
                    "gtime" => $w['agtime'],
                    "notice" => $w['anotice'],
                    "status" => $w['astatus'],
                    "editor" => $this->_uid,
                    "etime" => time()
                );
                $where = array(
                    'gid' => $w['gid'],
                    'sid' => $w['sid'],
                    'type' => 1
                );
            }
            $rel = $this->odb->where($where)->get("bay_service_set")->row_array();
            if($rel){//修改
                $this->odb->set($data)->where($where)->update("bay_service_set");
            }else{//新增
                $data['sid'] = $w['sid'];
                $data['gid'] = $w['gid'];
                $data['type'] = 1;
                $this->odb->set($data)->insert("bay_service_set");
            }
        }

        //临时
        if(isset($w['bstatus'])){
            if(isset($w['bstatus'])){//临时设置
                $_data = array(
                    "wtime" => $w['bwtime'],
                    "gtime" => $w['bgtime'],
                    "notice" => $w['bnotice'],
                    "status" => $w['bstatus'],
                    "st" => $w['st'],
                    "ed" => $w['ed'],
                    "editor" => $this->_uid,
                    "etime" => time()
                );
                $_where = array(
                    'gid' => $w['gid'],
                    'sid' => $w['sid'],
                    'type' => 2
                );
            }
            $_rel = $this->odb->where($_where)->get("bay_service_set")->row_array();
            if($_rel){//修改
                $this->odb->set($_data)->where($_where)->update("bay_service_set");
            }else{//新增
                $_data['sid'] = $w['sid'];
                $_data['gid'] = $w['gid'];
                $_data['type'] = 2;
                $this->odb->set($_data)->insert("bay_service_set");
            }
        }
        $this->odb->trans_complete();
        if ($this->db->trans_status() === FALSE){//失败
            return "保存失败，请重试";
        }else{//成功
            return true;
        }
    }

    //快捷回复列表
    public function fastList($val){
        $where = array(
            "gid" => $val['gid'],
            "lang" => $val['lang'],
        );
        //0:自动回复,1:公共,uid:私有
        $w = array(0,1,$this->_uid);
        $this->db->where($where)->where_in('fuid',$w)->order_by("del asc,fuid asc");
        $this->db->from("fast");
        $this->getLimit($this->db);
        $num = $this->db->count_all_results('',false);
        $ret = $this->db->get()->result_array();
        return array($ret,$num);
    }

    //新增快捷回复
    public function addFast($val){
        $data = array(
            'lang' => $val['lang'],
            'title' => $val['title'],
            'gid' => $val['gid'],
            'etime' => time(),
            'editor' => $val['editor']
        );
        if($val['type'] == 0){
            $data['fuid'] = 0;
        }elseif($val['type'] == 1){
            $data['fuid'] = 1;
        }elseif($val['type'] == 2){
            $data['fuid'] = $this->_uid;
        }else{
            return "快捷回复类型不对！";
        }
        $ret = $this->db->set($data)->insert("fast");
        return $ret;
    }

    //编辑一条快捷回复
    public function editFast($field = array(),$val = array(),$check = false){
        $w = $this->_getValue($field,$val);
        if($check){
            $sql = "select * from bay_faset where id = {$w['id']} AND fuid = 0";
            $_c = $this->db->query($sql)->row_array();
            if($_c) return "你没有权限编辑欢这一条迎语！";
        }
        $data = array(
            'editor' => $this->_uid,
            'etime' => time()
        );
        if(isset($w['del'])){//删除/删除恢复
            if($w['del'] != 0 || $w['del'] != 1) return "删除标识值不对！";
            $data['del'] = $w['del'];
            $ret = $this->db->set($data)->where('id',$w['id'])->update("fast");
        }else{//修改
            if(!isset($w['title'])) return "没有修改的内容";
            $data['title'] = $w['title'];
            if(isset($w['lang'])) $data['lang'] = $w['lang'];
            $ret = $this->db->set($data)->where('id',$w['id'])->update("fast");
        }
        return $ret;
    }
}