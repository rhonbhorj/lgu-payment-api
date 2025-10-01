<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Auth_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /**
     * Login user
     */
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
            d.id AS department_id,
            d.department
        ');
        $this->db->from('tbl_user u');
        $this->db->join('tbl_user_type ut', 'u.user_type_id = ut.ut_id');
        $this->db->join('tbl_departments d', 'u.department_id = d.id', 'left');
        $this->db->where('u.username', $username);
        $this->db->where('u.password', $password);

        $query = $this->db->get();
        return ($query && $query->num_rows() === 1) ? $query->row_array() : null;
    }

    public function login_token($user_id, $token)
    {
        $this->db->where('user_id', $user_id);
        $this->db->update('tbl_user', [
            'login_token'   => $token,
            'last_login'    => date('Y-m-d H:i:s'),
            'account_status' => 'ACTIVE' // ✅ Mark user as ACTIVE on login
        ]);
    }

    public function update_user_status($user_id, $status)
    {
        $update_data = ['account_status' => $status];

        if ($status === 'INACTIVE') {
            $update_data['session_id'] = NULL;
        }

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

    public function get_or_create_user_type($user_type_name)
    {
        if (empty($user_type_name)) {
            return null;
        }

        $map = [
            'SUPERADMIN' => 1,
            'ADMIN'      => 2,
            'SUBUSER'    => 3,
            'USER'       => 4,
            'CEO'        => 5,
            'CSR'        => 6,
            'ACCOUNTING' => 7,
        ];

        $upper_type = strtoupper($user_type_name);
        $user_level = $map[$upper_type] ?? 99;

        $query = $this->db->get_where('tbl_user_type', ['user_type' => $upper_type]);
        if ($query->num_rows() > 0) {
            return $query->row()->ut_id;
        }

        $this->db->insert('tbl_user_type', [
            'user_type'    => $upper_type,
            'user_level'   => $user_level,
            'date_created' => date('Y-m-d H:i:s'),
            'status'       => 'active'
        ]);

        return $this->db->insert_id();
    }

    public function get_or_create_department($department_name)
    {
        if (empty($department_name)) {
            $department_name = "GENERAL";
        }

        $query = $this->db->get_where('tbl_departments', ['department' => $department_name]);
        if ($query->num_rows() > 0) {
            return $query->row()->id;
        }

        $this->db->insert('tbl_departments', [
            'department'   => $department_name,
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
        return $this->db->get('tbl_departments')->result();
    }
}
