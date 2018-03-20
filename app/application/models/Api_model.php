<?php
class Api_model extends Bmax_Model {
    private $odb;
    public function __construct()
    {
        parent::__construct();
        $this->ndb = $this->load->database();

    }

    private function getODb(){
        if(!$this->odb){
            $this->odb = $this->load->database('online',true);
        }
        return $this->odb;
    }
    public function dosomething(){
//        $query = $this->db->get('entries', 10);
        echo '<pre/>';
//        $query = $this->db->get('game');

//        foreach ($query->result() as $row)
//        {
//            echo 'sss';
//            echo $row->name;
//        }
//        $this->load->dbforge();
        $db = $this->load->database();
        $query = $this->db->get('robot');
//        var_dump($this->db->list_tables());
//        var_dump($this->ndb->list_tables());

        return $query->result();
    }
}