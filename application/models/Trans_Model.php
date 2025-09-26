<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Trans_Model extends CI_Model {

    protected $table = 'tbl_transactions';


    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

     public function create_transaction($data)
    {
        $this->db->insert($this->table, $data);
        return $this->db->insert_id(); 
    }
    
    public function check_refid_exists($refid)
    {
        return $this->db->get_where('tbl_transactions', ['trans_refid' => $refid])->row();
    }
}
