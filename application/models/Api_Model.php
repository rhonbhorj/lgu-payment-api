<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Api_model extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }


    public function get_api_keys()
    {
        $query = $this->db->get('tbl_api_key');  // SELECT * FROM tbl_api_key
        return $query->result(); // return as array of objects
    }

    public function get_api_key_by_id($id)
    {
        $query = $this->db->get_where('tbl_api_key', array('api_id' => $id));
        return $query->row(); // return single row
    }

    public function validate_api_key($api_key)
    {
        $this->db->where('api_key_value', $api_key);
        $this->db->where('api_status', 'ACTIVE');
        $query = $this->db->get('tbl_api_key');

        if ($query->num_rows() > 0) {
            return $query->row(); // return the matched row
        } else {
            return false; // no valid key
        }
    }

        public function insert_log($data) {
        $this->db->insert('tbl_api_logs', $data);
        return $this->db->insert_id();
    }

    
}
