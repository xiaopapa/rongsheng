<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Welcome extends Bmax_Controller {


    public function __construct()
    {
        parent::__construct();
//        $this->load->model('archive/Archive_model');
        return;
//        $this->Archive_model = new Archive_model();
    }
    
    public function index()
    {
        echo 'hello';die;
        $this->load->view('welcome_message');
    }
}