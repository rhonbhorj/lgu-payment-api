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
    public function check_reference_exists($reference)
    {
        return $this->db->where('trans_refid', $reference)
            ->limit(1)
            ->get('tbl_transactions')
            ->num_rows() > 0;
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

    public function insert_service($trans_no, $services)
    {
        if (empty($services) || !is_array($services)) {
            return false;
        }

        // ✅ Detect and normalize service format
        if (array_keys($services) === range(0, count($services) - 1)) {
            // Old numeric array format
            $item_code      = $services[0] ?? null;
            $item_qty       = $services[1] ?? 1;
            $item_amount    = $services[2] ?? 0;
            $item_otherfees = $services[3] ?? 0;
        } else {
            // New associative format from JSON
            $item_code      = $services['item_code'] ?? null;
            $item_qty       = $services['item_qty'] ?? 1;
            $item_amount    = $services['item_amount'] ?? 0;
            $item_otherfees = $services['item_other_fees'] ?? 0;
        }

        // ✅ Validate before proceeding
        if (empty($item_code)) {
            return false;
        }

        // ✅ Lookup category
        $category = $this->db
            ->where('cat_code', $item_code)
            ->get('tbl_categories')
            ->row();

        if (!$category) {
            return false;
        }

        // ✅ Prepare insert data
        $data = [
            'part_code'        => $item_code,
            'part_transno'     => $trans_no,
            'part_qty'         => $item_qty,
            'part_amount'      => $item_amount,
            'part_other_fees'  => $item_otherfees,
            'part_particulars' => $category->cat_category
        ];

        // ✅ Insert and return success/failure
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


    // === Fetch callback detail ===
    public function callback_data_detail($refid)
    {
        return $this->db->where('reference_number', $refid)
            ->get('tbl_transaction_callback')
            ->row();
    }

    public function get_by_refid($ref_id)
    {
        return $this->db->where('trans_refid', $ref_id)
            ->get('tbl_transactions')
            ->row();
    }

    public function get_items_by_transno($trans_no)
    {
        return $this->db
            ->where('part_transno', $trans_no)
            ->get('tbl_transaction_particulars')
            ->result_array();
    }

    public function insert_callback($data)
    {
        return $this->db->insert('tbl_transaction_callback', $data);
    }


    public function update_status($trans_id, $status_label)
    {
        return $this->db->where('trans_id', $trans_id)
            ->update('tbl_transactions', ['trans_status' => $status_label]);
    }

    public function update_transaction($trans_id, $data)
    {
        return $this->db->where('trans_id', $trans_id)
            ->update('tbl_transactions', $data);
    }

    public function callback_exists($txid, $ref_id)
    {
        $this->db->where('txid', $txid);
        $this->db->where('reference_number', $ref_id);
        $query = $this->db->get('tbl_transaction_callback');

        return $query->num_rows() > 0;
    }

    public function get_transactions_with_particulars($start_date, $end_date = null)
    {
        $this->db->select('
            t.trans_id,
            t.trans_no,
            t.trans_refid,
            t.trans_payor,
            t.trans_mobile,
            t.trans_email,
            t.trans_company,
            t.trans_sub_total,
            t.trans_conv_fee,
            t.trans_grand_total,
            t.trans_txid,
            t.trans_ref,
            t.trans_date_created,
            t.trans_settled_date,
            t.trans_status,
            p.part_id,
            p.part_code,
            p.part_transno,
            p.part_particulars,
            p.part_qty,
            p.part_amount,
            p.part_other_fees
        ');
        $this->db->from('tbl_transactions t');
        $this->db->join('tbl_transaction_particulars p', 't.trans_refid = p.part_transno', 'left');

        if ($end_date === null) {
            $this->db->where('DATE(t.trans_date_created)', $start_date);
        } else {
            $this->db->where('t.trans_date_created >=', $start_date);
            $this->db->where('t.trans_date_created <=', $end_date);
        }

        $this->db->order_by('t.trans_date_created', 'DESC');
        $query = $this->db->get();

        return $query->result_array();
    }
}
