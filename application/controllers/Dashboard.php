<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Dashboard extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Report_Model', 'report_model');
        date_default_timezone_set('Asia/Manila');

        // ✅ API Headers (for CORS + JSON)
        header("Content-Type: application/json; charset=utf-8");
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding, X-Requested-With, Authorization, X-API-KEY");
    }

    /**
     * Dashboard main API endpoint
     * Returns today, yesterday, all-time, weekly, and monthly summaries
     */
    public function index()
    {
        $dashboardData = [
            'today'      => $this->report_model->all_transaction_today(),
            'yesterday'  => $this->report_model->all_transaction_yesterday(),
            'all_data'   => $this->report_model->all_transaction_data(),
            'week_data'  => $this->report_model->all_transaction_this_week(),
            'month_data' => $this->report_model->monthly_transaction(),
        ];

        echo json_encode([
            'success' => true,
            'status_code' => 200,
            'data' => $dashboardData
        ]);
    }
}
