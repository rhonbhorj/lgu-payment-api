<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Report_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        date_default_timezone_set('Asia/Manila');
    }

    // 🔹 TODAY
    public function all_transaction_today()
    {
        $start = date('Y-m-d 00:00:00');
        $end   = date('Y-m-d 23:59:59');
        $row   = $this->get_transactions_between($start, $end);

        return $this->format_summary($row);
    }

    // 🔹 YESTERDAY
    public function all_transaction_yesterday()
    {
        $start = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $end   = date('Y-m-d 23:59:59', strtotime('-1 day'));
        $row   = $this->get_transactions_between($start, $end);

        return $this->format_summary($row);
    }

    // 🔹 ALL TIME
    public function all_transaction_data()
    {
        $this->db->select("
            SUM(trans_grand_total) AS grand_total,
            SUM(trans_sub_total) AS sub_total,
            SUM(trans_conv_fee) AS conv_fee,
            COUNT(trans_id) AS grand_total_count
        ");
        $this->db->from("tbl_transactions");
        $this->db->where("TRIM(trans_status) =", "PAID");
        $row = $this->db->get()->row();

        return $this->format_summary($row);
    }

    // 🔹 THIS WEEK (Mon - Sun)
    public function all_transaction_this_week()
    {
        $startOfWeek = strtotime('monday this week');
        $data = [];

        for ($i = 0; $i < 7; $i++) {
            $day = date('Y-m-d', strtotime("+$i day", $startOfWeek));
            $dayName = date('l', strtotime($day));
            $start = "$day 00:00:00";
            $end   = "$day 23:59:59";

            $data[$dayName] = $this->get_transaction_data($start, $end);
        }

        return $data;
    }

    // 🔹 MONTHLY (January to current)
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

    // 🔹 FETCH BETWEEN DATES (for Paid)
    private function get_transactions_between($start, $end)
    {
        $this->db->select([
            'SUM(trans_grand_total) AS grand_total',
            'SUM(trans_sub_total) AS sub_total',
            'SUM(trans_conv_fee) AS conv_fee',
            'COUNT(trans_id) AS grand_total_count'
        ]);
        $this->db->from('tbl_transactions');
        $this->db->where('TRIM(trans_status) =', 'PAID');
        $this->db->where('trans_settled_date >=', $start);
        $this->db->where('trans_settled_date <=', $end);

        return $this->db->get()->row();
    }

    // 🔹 Get summary for a specific date range
    public function get_transaction_data($dateFrom, $dateTo)
    {
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
        $this->db->where("TRIM(trans_status) =", "PAID");
        $success = $this->db->get()->row();

        return [
            "total_count_success" => (int)($success->total_count_success ?? 0),
            "sub_total"           => (float)($success->sub_total ?? 0),
            "convenience_fee"     => number_format((float)($success->convenience_fee ?? 0), 2, '.', ','),
            "total_txn_amount"    => (float)($success->total_txn_amount ?? 0),
            "total_count_failed"  => $this->count_transactions($dateFrom, $dateTo, "FAILED"),
            "total_count_created" => $this->count_transactions($dateFrom, $dateTo, "CREATED"),
            "date"                => $dateTo
        ];
    }

    // 🔹 Count by status
    private function count_transactions($dateFrom, $dateTo, $status)
    {
        $this->db->select("COUNT(trans_id) AS cnt");
        $this->db->from("tbl_transactions");
        $this->db->where("trans_settled_date >=", $dateFrom);
        $this->db->where("trans_settled_date <=", $dateTo);
        $this->db->where("TRIM(trans_status) =", $status);
        $row = $this->db->get()->row();

        return (int)($row->cnt ?? 0);
    }

    // 🔹 Monthly summary
    public function get_monthly_data_transaction($month)
    {
        $year  = date('Y');
        $month = str_pad($month, 2, '0', STR_PAD_LEFT);
        $start = "$year-$month-01 00:00:00";
        $end   = date("Y-m-t 23:59:59", strtotime($start));

        $result = $this->get_transaction_data($start, $end);

        // ✅ Count all regardless of status
        $this->db->from("tbl_transactions");
        $this->db->where("trans_settled_date >=", $start);
        $this->db->where("trans_settled_date <=", $end);
        $result['total_count_all'] = $this->db->count_all_results();

        $result['date_from'] = $start;
        $result['date_to']   = $end;

        return $result;
    }

    // 🔹 Helper: Format summary response
    private function format_summary($row)
    {
        return [
            "grand_total"       => (float)($row->grand_total ?? 0),
            "sub_total"         => (float)($row->sub_total ?? 0),
            "conv_fee"          => (float)($row->conv_fee ?? 0),
            "grand_total_count" => (int)($row->grand_total_count ?? 0)
        ];
    }
}
