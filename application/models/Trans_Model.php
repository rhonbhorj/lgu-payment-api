<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Trans_Model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    // === Check if reference ID exists ===
    public function check_refid_exists($refid)
    {
        return $this->db->where('trans_refid', $refid)
            ->get('tbl_transactions')
            ->row();
    }

    // === Create a new transaction ===
    public function create_transaction($data)
    {
        $this->db->insert('tbl_transactions', $data);
        return $this->db->insert_id();
    }

    // === Insert callback data ===
    public function doInsertCallback($data)
    {
        return $this->db->insert('tbl_transaction_callback', $data);
    }

    // === Update transaction status ===
    public function update_status_by_ref($refid, $type, $status, $datetime, $txid = null)
    {
        $update = [
            'trans_status' => $status,
            'trans_type' => $type,
            'trans_updated_at' => $datetime
        ];
        if ($txid) {
            $update['trans_txid'] = $txid;
        }

        return $this->db->where('trans_refid', $refid)
            ->update('tbl_transactions', $update);
    }

    // === Insert service (skip if category not found) ===
    public function insert_service($trans_no, $services)
    {
        if (empty($services) || count($services) < 4) {
            return false;
        }

        // Find category
        $category = $this->db->where('cat_code', $services[0])
            ->get('tbl_categories')
            ->row();

        if (!$category) {
            // Skip insertion if category not found
            return false;
        }

        $data = [
            'part_code'        => $services[0],
            'part_transno'     => $trans_no,
            'part_qty'         => $services[1],
            'part_amount'      => $services[2],
            'part_other_fees'  => $services[3],
            'part_particulars' => $category->cat_category
        ];

        return $this->db->insert('tbl_transaction_particulars', $data);
    }

    // === Get total other fees for a transaction ===
    public function getTotalOtherFee($refid)
    {
        $this->db->select_sum('part_other_fees', 'total_other_fees')
            ->where('part_transno', $refid);
        return $this->db->get('tbl_transaction_particulars')->row();
    }

    // === Fetch transaction details ===
    public function getTransactionDetails($refid)
    {
        return $this->db->where('trans_refid', $refid)
            ->get('tbl_transactions')
            ->row();
    }

    // === Get all categories ===
    public function get_all_categories()
    {
        return $this->db->get('tbl_categories')->result_array();
    }

    // === Fetch callback detail ===
    public function callback_data_detail($refid)
    {
        return $this->db->where('reference_number', $refid)
            ->get('tbl_transaction_callback')
            ->row();
    }
}
