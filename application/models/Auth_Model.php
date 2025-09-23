<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Auth_model extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }


    public function login($username, $password)
    {
        $this->db->select('
            u.user_id, 
            u.name, 
            u.username, 
            u.password, 
            u.user_type_id, 
            u.account_status, 
            u.account_state, 
            u.session_id,
            ut.ut_id, 
            ut.user_level, 
            ut.date_created as user_type_created, 
            ut.status AS user_type_status,
            d.id AS     , 
            d.department
        ');
        $this->db->from('tbl_user u');
        $this->db->join('tbl_user_type ut', 'u.user_type_id = ut.ut_id');
        $this->db->join('tbl_departments d', 'u.department_id = d.id');
        $this->db->where('u.username', $username);
        $this->db->where('u.password', $password); // Add password condition

        $query = $this->db->get();

        if ($query && $query->num_rows() === 1) {
            return $query->row_array(); // Return user data if found
        }

        return null; // Return null if no match found
    }






    public function login_token($user_id, $token)
    {
        $this->db->where('user_id', $user_id);
        $this->db->update('tbl_user', [
            'login_token' => $token,
            'last_login' => date('Y-m-d H:i:s')
        ]);
    }

    public function update_user_status($user_id, $status)
    {
        // Prepare data to update
        $update_data = ['account_status' => $status];

        // If status is INACTIVE, set session_id to NULL
        if ($status === 'INACTIVE') {
            $update_data['session_id'] = NULL;
        }

        // Update the database
        $this->db->where('user_id', $user_id);
        $this->db->update('tbl_user', $update_data);
    }



    public function log_user($data)
    {
        $this->db->insert('tbl_user_logs', $data);
    }

    public function get_user_by_username($username)
    {
        return $this->db->get_where('tbl_user', ['username' => $username])->row();
    }

    public function insert_department($department_name)
    {
        $data = [
            'department' => $department_name,
            'date_created' => date('Y-m-d H:i:s')
        ];
        return $this->db->insert('tbl_departments', $data); // Insert the department into the database
    }

    public function get_or_create_user_type($user_type_name)
    {
        if (empty($user_type_name)) {
            return null; // prevent inserting NULL
        }


        $map = [
            '1' => 'SUPERADMIN',
            '2' => 'ADMIN',
            '3' => 'SUBUSER',
            '4' => 'USER',
            '5' => 'CEO',
            '6' => 'CSR',
            '7' => 'ACCOUNTING',
        ];

        $user_type_key = array_search(strtoupper($user_type_name), $map);
        $query = $this->db->get_where('tbl_user_type', ['user_type' => $user_type_name]);
        if ($query->num_rows() > 0) {
            return $query->row()->ut_id;
        }

        $this->db->insert('tbl_user_type', [
            'user_type' => $user_type_name,
            'user_level' =>  $user_type_key,
            'date_created' => date('Y-m-d H:i:s'),
            'status' => 'active'
        ]);

        return $this->db->insert_id();
    }


    public function get_or_create_department($department_name)
    {
        if (empty($department_name)) {
            return null; // Prevent inserting NULL into the database
        }

        $query = $this->db->get_where('tbl_departments', ['department' => $department_name]);
        if ($query->num_rows() > 0) {
            return $query->row()->id;
        }

        $this->db->insert('tbl_departments', [
            'department' => $department_name,
            'date_created' => date('Y-m-d H:i:s')
        ]);

        return $this->db->insert_id();
    }


    public function register_user($data)
    {
        return $this->db->insert('tbl_user', $data);
    }



    public function get_departments()
    {
        $query = $this->db->get('tbl_departments'); // Fetch all records from tbl_departments
        return $query->result(); // Return the result as an array of objects
    }
}
