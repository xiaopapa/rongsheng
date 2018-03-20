<?php

/**
 * 模块添加model
 * Class Robot_model
 * @author harvey
 * @Date: 17-4-7
 * @license  http://www.boyaa.com/
 */
class Menu_model extends Bmax_Model {
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
     * @date 17-4-7
     */
    private function getODb(){
        if(!$this->odb){
            $this->odb = $this->load->database('online',true);
        }
    }

    //获取分页总条数
    public function countPage($db = ''){
        $sql = $this->m->count();
        if($db){
            $query = $db->query($sql);
        }else{
            $query = $this->db->query($sql);
        }
        $rel = $query->result_array();
        return $rel[0]['count(*)'];
    }



    /**
     * 获取一条菜单
     * @param string $search
     * @param string $t
     * @return mixed
     * @date
     */
    public function getMenuold($search = '',$t = ''){
        $sql = "SELECT * FROM bay_menu WHERE del != 1";
        $id = fun::get('id');
        if($id){
            if(strpos($id,',') !== false){
                $sql .= " and id = {$id}";
            }else{
                $sql .= " and id in({$t})";
            }
        }elseif($t){
            if(strpos($t,',') !== false){
                $sql .= " and t_type in({$t})";
            }else{
                $sql .= " and t_type = {$t}";
            }
        }
        if($search){
            $sql .= " and (tag like '%{$search}% or name like '%{$search}%')";
        }
        $query = $this->db->query($sql);
        return $query->result_array();
    }
    
    /**
     * 获取一个菜单列表
     * @param array $val
     * @param array $field
     * @param bool|true $limit
     * @return array
     * @date
     */
    public function getMenu($val = array(),$field = array(),$limit = true){
        $w = $this->_getValue($field,$val);
//        var_dump($w);die;
        if(isset($w['platform']) && $w['platform'] != 999){
            if(strpos($w['platform'],',') !== false){
                $this->db->where_in('platform',explode(',',$w['platform']));
            }else{
                $this->db->where('platform',$w['platform']);
            }
        }
        if(isset($w['del']) && $w['del'] != 999){
            if(strpos($w['id'],',') !== false){
                $this->db->where_in('del',explode(',',$w['del']));
            }else{
                $this->db->where('del',$w['del']);
            }
        }elseif($w['del'] != 999){
            $this->db->where('del',0);
        }
        if(isset($w['fid'])){
            if(strpos($w['fid'],',') !== false){
                $this->db->where_in('fid',explode(',',$w['fid']));
            }else{
                $this->db->where('fid',$w['fid']);
            }
        }
        if($w['id']){
            if(strpos($w['id'],',') !== false){
                $this->db->where_in('id',explode(',',$w['id']));
            }else{
                $this->db->where('id',$w['id']);
            }
        }elseif($w['t_type']){
            if(strpos($w['t_type'],',') !== false){
                $this->db->where_in('t_type',explode(',',$w['t_type']));
            }else{
                $this->db->where('t_type',$w['t_type']);
            }
        }
        if($w['search']){
            $this->db->where("(tag like '%{$w['search']}%' or name like '%{$w['search']}%')");
        }
        $this->db->from('menu');
        if($limit){//分页
            $num = $this->db->count_all_results('',false);
            $this->getLimit($this->db);
        }else{
            $num = 0;
        }
        $rel = $this->db->get()->result_array();
        if($rel){
            $arr = $this->getMenuData();
            $id = '';
            foreach($rel as $key => &$row){
                $row['port'] = array();
                $id .= $row['id'] . ',';
                $row['node_name'] = rtrim(implode(' / ',fun::reflect($row['node'],$arr)),' / ');
            }
            $id = rtrim($id,',');
            $sql = "select * from bay_port WHERE controller in ($id) AND del = 0";
            $prel = $this->db->query($sql)->result_array();
            if($prel){
                foreach($prel  as $p){
                    $cid = $p['controller'];
                    foreach($rel as &$r){
                        if($r['id'] == $cid){
                            $r['port'][] = array(
                                'id' => $p['id'],
                                'tag' => $p['class'] . '/' . $p['method'],
                                'statement' => $p['statement']
                            );
                            break;
                        }
                    }
                }
            }
        }
        return  (array($rel,$num));
    }

    /**
     * 获取菜单信息
     * @date
     */
    //这个后面可以做成缓存形式
    public function getMenuData(){
        $rel = $this->db->get('menu')->result_array();
        $data = array();
        if($rel){
            foreach($rel as $row){
                $data[$row['id']] = $row['name'];
            }
        }
        return $data;
    }

    /**
     * 检查菜单是否有重名的
     * @param $tag
     * @param $name
     * @return bool
     * @date
     */
    public function checkMenu($tag,$name,$platform,$id = 0){
        $sql = "SELECT * FROM bay_menu WHERE (tag = '{$tag}' or name = '{$name}') and platform = {$platform} and del != 1";
        if($id) $sql .= " and id != {$id}";
        $ret = $this->db->query($sql)->row_array();
        if($ret) return false;
        return true;
    }

    /**
     * 检查FID是不是指定类型模块（1：目录,2：实体,3:功能）
     * @param $fid
     * @param int $type
     * @return bool
     * @date
     */
    public function checkFid($fid,$type=1){
        $sql = "SELECT id FROM bay_menu WHERE id = {$fid} and t_type = {$type}";
        $ret = $this->db->query($sql)->row_array();
        if($ret) return true;
        return false;
    }

    /**
     * 新增或者更新/删除（del设为1）一条分类
     * @param $field
     * @param string $where
     * @param array $set
     * @return bool
     * @date
     */
    public function setMenu($field,$where = '',$set = array()){
        $this->m->table('menu')->setValue($field);
        if($set && is_array($set)){//补充值
            foreach($set as $key => $row){
                $this->m->value[$key] = $row;
            }
        }
        $this->m->value['etime'] = time();
        $this->m->value['editor'] = $this->_uid;
        if(!$where){//新增,事务提交
            $fid = (int) fun::get('fid');
            $sql = $this->m->insert(true,true);
            $this->db->trans_start();//事务开启
            $this->db->query($sql);
            $id = $this->db->insert_id();
            //如果fid不为0
            if($fid){
                $sql = "SELECT * FROM `bay_menu` WHERE id = '{$fid}'";
                $rel = $this->db->query($sql);
                $data = $rel->result_array();
                if(!$data) return false;
                $data = $data[0];
                $node = $data['node'];
                if($node){//更新祖先节点子ID
                    $this->_addNode($node,$id);
                }
            }
            //更新新插入的node
            $child_node = (isset($node) && $node) ?  $node . ",{$id}" : $id;
            $sql = "UPDATE `bay_menu` set node = '{$child_node}' WHERE id = {$id}";
            $this->db->query($sql);
            //接口处理
            $port = fun::get('port');
            if($port){
                $sql = "update bay_port set controller = {$id} where id in({$port})";
                $this->db->query($sql);
            }
            $this->db->trans_complete();//事务提交

        }else{//更新或删除
            $del = (int) fun::get('del');
            $id = fun::get('id');
            if($del == 1){//删除操作,事务
                $this->db->trans_start();//事务开启
                $sql = $this->m->update($where,true,true);
                $this->db->query($sql);
                //删除该ID下面所有子ID
                $sql = "SELECT * FROM `bay_menu` WHERE id = {$id}";
                $rel = $this->db->query($sql)->result_array();
                if($rel){
                    $rel = $rel[0];
                    $node = $rel['node'] ? $rel['node'] : '';
                    $child_id = $rel['child_id'] ? $rel['child_id'] : '';

                    if($child_id){
                        $this->_delChild($child_id);
                        $child_id = $child_id . ",{$id}";//删完子节点，再加上自己，去处理祖先节点
                    }else{
                        $child_id = $id;
                    }
                    if($node) $this->_dealNode($node,$child_id);
                }
                //接口处理
                $sql = "update bay_port set controller = 0 where controller = {$id}";
                $this->db->query($sql);
                $this->db->trans_complete();//事务提交
            }else{//更新
                $this->db->trans_start();//事务开启
                $sql = $this->m->update($where,true,true);
                $rel = $this->db->query($sql);
                //接口处理
                $port = fun::get('port');
                //原来有的
                $sql = "select id from bay_port WHERE controller = {$id}";
                $rel = $this->db->query($sql)->result_array();
                if($rel){//删掉原来有
                    $ids = '';
                    foreach($rel as $row){
                        $ids .= $row['id'] . ',';
                    }
                    $ids = rtrim($ids,',');
                    $_sql = "update bay_port set controller = 0 where id in({$ids})";
                    $this->db->query($_sql);
                }
                if($port){//现在的添加
                    $sql = "update bay_port set controller = {$id} where id in({$port})";
                    $this->db->query($sql);
                }
                $this->db->trans_complete();//事务提交
            }
            if(!$rel) return false;
        }
        //生成模块缓存
        $this->_putMenuCache();
        return true;
    }

    /**
     * 生成模块缓存 1、基本信息组 2、映射组 3、子节点组
     * @date
     */
    private function _putMenuCache(){
//    public function putMenuCache(){
        $rel = $this->db->where('del',0)->get('menu')->result_array();
        $all_menu = array();
        $arr = array();
        $r = array();
        $child_arr = array();
        if($rel){
            $all_menu = $rel;
            foreach($rel as $key => $row){
                $arr[$row['platform']][$row['id']] = $row;
                //处理映射组
                if($row["t_type"] == 3){//功能
                    $fid = $row['fid'];
                    $r[$row['platform']][$arr[$row['platform']][$fid]['tag'] . '.' .$row['tag']] = $row['id'];
                }else{//目录或者实体模块
                    $r[$row['platform']][$row['tag'] . '.*'] = $row['id'];
                }
                //顶级节点,特殊处理
                if($row['fid'] == 0){
                    $child_arr[0][] = $row['id'];
                }else{
                    $child_arr[$row['platform']][$row['fid']][] = $row['id'];
                }
            }
        }
        //写缓存
        $f1 = DATA_ROOT.'cache/menu/index.php';
        $f2 = DATA_ROOT.'cache/menu/reflect.php';
        $f3 = DATA_ROOT.'cache/menu/child_arr.php';
        $f4 = DATA_ROOT.'cache/menu/menu.php';
        $out = load_class('OP','helpers');
        $out->php($f1,$arr);
        $out->php($f2,$r);
        $out->php($f3,$child_arr);
        $out->php($f4,$all_menu);
    }


    /**
     * 为祖先节点加子节点
     * @param $fid
     * @param $cid
     * @date
     */
    private function _addNode($fid,$cid){
        $fid = explode(',',$fid);
        foreach($fid as $key => $row){
            $sql = "SELECT * FROM `bay_menu` WHERE id = {$row}";
            $rel = $this->db->query($sql)->result_array();
            if(isset($rel[0]['child_id'])){
                $temp = $rel[0]['child_id'];
                $new_str = $temp ? $temp . ",{$cid}" : $cid;
                $sql = "UPDATE  `bay_menu` SET child_id = '{$new_str}' WHERE id = {$row}";
                $this->db->query($sql);//更新子节点
            }else{//警报？
                fun::log("菜单节点操作失败",'menu');
            }
        }
    }

    //分类修改处理父节节点子ID
    private function _dealNode($fid,$child_id){
        $fid = explode(',',$fid);
        $child_id = explode(',',$child_id);
        foreach($fid as $key => $row){
            $sql = "SELECT * FROM `bay_menu` WHERE id = {$row}";
            $rel = $this->db->query($sql)->result_array();
            if(isset($rel[0]['child_id']) && $rel[0]['child_id']){
                $temp = $rel[0]['child_id'];
                $temp = explode(',',$temp);
                $new = array_diff($temp,$child_id);
                sort($new);
                $new_str = implode(',',$new);
                $sql = "UPDATE  `bay_menu` SET child_id = '{$new_str}' WHERE id = {$row}";
                $this->db->query($sql);//更新子节点
            }else{//警报？

            }
        }
    }

    //删除子ID
    private function _delChild($child_id){
        $sql = "UPDATE `bay_menu` set del = 1 WHERE id IN ({$child_id})";
        $this->db->query($sql);
    }

    /**
     * 将接口数据放到数据库里面
     * @date
     */
    public function scanPort(){
        $ret = fun::scanAll(FCPATH . 'application/controllers','php');
        $time = time();
        foreach($ret as $file){
            $class = str_replace('.php','',substr($file,strpos($file,'controllers/') + 12));
            $file = file_get_contents($file);
            $file = substr($file,strpos($file,'extends Bmax_Controller') + 27);
            $regex = '/\/\*\*[\s]+\*[\s]+([\x{4e00}-\x{9fa5}\S]+)[\s]*?\*[\s\S]*?public[\s]+function[\s]+([\w]+)[\s]*\(/ui';
            $matches = array();
            preg_match_all($regex, $file, $matches);

            if($matches[1] && $matches[2]){
                foreach($matches[1] as $key => $row){
                    $data = array(
                        'class' => $class,
                        'method' => $matches[2][$key],
                        'statement' => $row,
                        'etime' => $time,
                        'editor' => $this->_uid,
                        'del' => 0
                    );
                    $sql = "select * from bay_port WHERE class = '{$class}' AND method = '{$data['method']}'";
                    $rel = $this->db->query($sql)->row_array();
                    if($rel){//更新
                        $this->db->set($data)->where('id',$rel['id'])->update('port');
                    }else{//新增
                        $this->db->set($data)->insert('port');
                    }
                }
            }
        }
        //删除过期接口
        $outTime = $time - 3600*24*3;
        $sql = "update bay_port set del = 1 WHERE etime < $outTime";
        $this->db->query($sql);
    }

    /**
     * 生成缓存
     * @date
     */
    public function putPortCache(){
        $f = CACHE_DIR . 'port.php';
        $sql = "select * from bay_port WHERE del = 0";
        $rel = $this->db->query($sql)->result_array();
        $port = array();
        if($rel){
            foreach($rel as $row){
                $str = $row['class'] . '/' . $row['method'];
                $port[$str] = array(
                    'id' => $row['id'],
                    'class' => $row['class'],
                    'method' => $row['method'],
                    'controller' => $row['controller'],
                    'statement' => $row['statement']
                );
            }
        }
        $out = load_class('OP','helpers');
        $out->php($f,$port);
    }

    /**
     * 获取未被控制的接口
     * @return mixed
     * @date
     */
    public function getPort(){
        $w = array(
            'del' => 0,
            'controller' => 0
        );
        $rel = $this->db->where($w)->get('port')->result_array();
        $ret = array();
        if($rel){
            foreach($rel as $row){
                $ret[] = array(
                    'id' => $row['id'],
                    'tag' => $row['class'] . '/' . $row['method'],
                    'statement' => $row['statement']
                );
            }
        }
        return $ret;
    }

    /**
     * 给模块 删除/添加一个接口
     * @param $pid
     * @param $mid
     * @return mixed
     * @date
     */
    public function delMenuPort($pid,$mid = 0){
        if($mid){//添加
            $ret = $this->db->set('controller',$mid)->where('id',$pid)->update('port');
        }else{//删除
            $ret = $this->db->set('controller',0)->where('id',$pid)->update('port');
        }
        return $ret;
    }
}