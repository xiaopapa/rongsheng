<?php
defined('BASEPATH') OR exit('No direct script access allowed');


//登录类
class Login extends Bmax_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Login_model');
        return;
        $this->Login_model = new Login_model();
    }

    /**
     * 验证登录
     * @date
     */
    public function index(){
        $w = $this->_check("id,pw");
        $key = md5($w['id'].$w['pw']);
        $where = array(
            'user' => $w['id'],
            'pword' => $key,
            'del' => 0
        );
        $rel = $this->db->where($where)->get('user')->row_array();
        if($rel){
            $adm = array(
                'id' => $rel['id'],
                'name' => $rel['name'],
                'user' => $rel['user']
            );
            session_name(SESSION_NAME);
            $this->session('login', $adm);
//            $url =  'http://'.$w['url'];
//            header('Location:'.$url);
            fun::codeBack(1);
        }else{
            //没登录信息就退出
            $this->loginOut();

            exit();
        }


    }


    /**
     * 登录退出
     * @date
     */
    public function loginOut(){
        $this->session('login', null);
        fun::cookie(SESSION_NAME, '', NOW - 1);
        fun::codeBack(-1);
        echo 'hello world';
    }


    /**
     * SESSION
     * @param $name
     * @param string $val
     * @return bool
     * @date
     */
    private function session($name, $val = 'NULL'){
        $f = DATA_ROOT . fun::date('Ym') .'/session/'. PHPSESSID .'.php';
        if($val == 'NULL'){
            $ret = CS::$_SESSION[$name];
            if(!$ret && file_exists($f)){
                $s = file_get_contents($f);
                $s = explode("die();?>", $s);
                $s = $s[1];
                $ret = fun::unserialize($s);
            }
            return $ret;
        }else{
            session_id();
            session_start();
            $_SESSION[$name] = $val;
            CS::$_SESSION = $_SESSION;
            //过期时间8小时
            fun::cookie(SESSION_NAME, PHPSESSID, NOW + 28800);
            session_commit();
            file::puts($f, "<?php die();?>". fun::serialize($_SESSION));
        }
    }
}


