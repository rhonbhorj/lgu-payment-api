<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Report_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        date_default_timezone_set('Asia/Manila');
    }

    public function all_transaction_today()
    {
        $start = date('Y-m-d 00:00:00');
        $end   = date('Y-m-d 23:59:59');

        $row = $this->get_transactions_between($start, $end);

        return [
            "grand_total"        => $row ? (float) $row->grand_total : 0,
            "sub_total"          => $row ? (float) $row->sub_total : 0,
            "conv_fee"           => $row ? (float) $row->conv_fee : 0,
            "grand_total_count"  => $row ? (int) $row->grand_total_count : 0
        ];
    }

    /**
     * Transactions yesterday
     */
    public function all_transaction_yesterday()
    {
        $start = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $end   = date('Y-m-d 23:59:59', strtotime('-1 day'));

        $row = $this->get_transactions_between($start, $end);

        return [
            "grand_total"        => $row ? (float) $row->grand_total : 0,
            "sub_total"          => $row ? (float) $row->sub_total : 0,
            "conv_fee"           => $row ? (float) $row->conv_fee : 0,
            "grand_total_count"  => $row ? (int) $row->grand_total_count : 0
        ];
    }


    /**
     * All data (no date filter)
     */
    public function all_transaction_data()
    {
        $this->db->select("
            SUM(trans_grand_total) AS grand_total,
            SUM(trans_sub_total) AS sub_total, 
            SUM(trans_conv_fee) AS conv_fee,
            COUNT(trans_grand_total) AS grand_total_count
        ");
        $this->db->from("tbl_transactions");
        $this->db->where("trans_status", "PAID");
        $row = $this->db->get()->row();

        return [
            "grand_total"        => $row ? (float) $row->grand_total : 0,
            "sub_total"          => $row ? (float) $row->sub_total : 0,
            "conv_fee"           => $row ? (float) $row->conv_fee : 0,
            "grand_total_count"  => $row ? (int) $row->grand_total_count : 0
        ];
    }

    /**
     * Transactions this week (Mon - Sun)
     */
    public function all_transaction_this_week()
    {
        $today     = date('Y-m-d');
        $dayOfWeek = date('N'); // 1=Monday, 7=Sunday
        $data      = [];

        // Loop through Mon-Sun
        $allDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        foreach ($allDays as $i => $dayName) {
            if ($i < $dayOfWeek) {
                $start = date('Y-m-d 00:00:00', strtotime("monday this week +$i days"));
                $end   = date('Y-m-d 23:59:59', strtotime("monday this week +$i days"));
                $data[$dayName] = $this->get_transaction_data($start, $end);
            } else {
                $data[$dayName] = $this->empty_transaction_data(date('Y-m-d'));
            }
        }

        return $data;
    }

    /**
     * Monthly data for all months up to current
     */
    public function monthly_transaction()
    {
        $currentMonth = (int) date('m');
        $data = [];

        for ($m = 1; $m <= $currentMonth; $m++) {
            $monthName = date('F', mktime(0, 0, 0, $m, 1));
            $data[$monthName] = $this->get_monthly_data_transaction($m);
        }

        return $data;
    }

    /**
     * Fetch transaction data between 2 dates
     */
    private function get_transactions_between($start, $end)
    {
        $this->db->select([
            'SUM(trans_grand_total) AS grand_total',
            'SUM(trans_sub_total) AS sub_total',
            'SUM(trans_conv_fee) AS conv_fee',
            'COUNT(trans_id) AS grand_total_count'
        ]);
        $this->db->from('tbl_transactions');
        $this->db->where('trans_status', 'PAID');
        $this->db->where('trans_settled_date >=', $start);
        $this->db->where('trans_settled_date <=', $end);

        $query = $this->db->get();
        return $query->row();
    }


    /**
     * Helper - Empty response
     */
    private function empty_transaction_data($date)
    {
        return [
            "total_count_success" => 0,
            "sub_total"           => 0,
            "convenience_fee"     => "0.00",
            "total_txn_amount"    => 0,
            "total_count_failed"  => 0,
            "total_count_created" => 0,
            "date"                => $date
        ];
    }

    /**
     * Get transaction summary for a specific date range
     */
    public function get_transaction_data($dateFrom, $dateTo)
    {
        $result = [];

        // ✅ Success (PAID)
        $this->db->select("
            COUNT(trans_id) AS total_count_success,
            SUM(trans_sub_total) AS sub_total,
            SUM(trans_conv_fee) AS convenience_fee,
            SUM(trans_grand_total) AS total_txn_amount
        ");
        $this->db->from("tbl_transactions");
        $this->db->where("trans_settled_date >=", $dateFrom);
        $this->db->where("trans_settled_date <=", $dateTo);
        $this->db->where("trans_status", "PAID");
        $success = $this->db->get()->row();

        $result['total_count_success'] = $success ? (int)$success->total_count_success : 0;
        $result['sub_total']           = $success ? (float)$success->sub_total : 0;
        $result['convenience_fee']     = $success ? number_format((float)$success->convenience_fee, 2, '.', ',') : "0.00";
        $result['total_txn_amount']    = $success ? (float)$success->total_txn_amount : 0;

        // ✅ Failed
        $result['total_count_failed'] = $this->count_transactions($dateFrom, $dateTo, "FAILED");

        // ✅ Created
        $result['total_count_created'] = $this->count_transactions($dateFrom, $dateTo, "CREATED");

        $result['date'] = $dateTo;
        return $result;
    }

    /**
     * Count by status
     */
    private function count_transactions($dateFrom, $dateTo, $status)
    {
        $this->db->select("COUNT(trans_id) AS cnt");
        $this->db->from("tbl_transactions");
        $this->db->where("trans_settled_date >=", $dateFrom);
        $this->db->where("trans_settled_date <=", $dateTo);
        $this->db->where("trans_status", $status);
        $row = $this->db->get()->row();
        return $row ? (int)$row->cnt : 0;
    }

    /**
     * Monthly summary
     */
    public function get_monthly_data_transaction($month)
    {
        $year = date('Y');
        $month = str_pad($month, 2, '0', STR_PAD_LEFT);

        $start = "$year-$month-01 00:00:00";
        $end   = date("Y-m-t 23:59:59", strtotime($start));

        $result = $this->get_transaction_data($start, $end);
        $result['total_count_all'] = $this->count_transactions($start, $end, ""); // all statuses
        $result['date_from'] = $start;
        $result['date_to']   = $end;

        return $result;
    }
}
