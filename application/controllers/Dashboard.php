<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Dashboard extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Report_Model', 'report_model');
        header("Content-Type: application/json");

        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding, X-Requested-With, Authorization, X-API-KEY");
    }

    public function month()
    {
        date_default_timezone_set('Asia/Manila');

        $month = "03";
        echo $yearmonth = date('Y-' . $month);

        echo "<br>";

        $timestamp = strtotime("$yearmonth-01");
        $day_of_the_month = date("l", $timestamp);   // convert to word
        echo "first day " . $day_of_the_month;

        if ($day_of_the_month == 'Sunday') {
            $dayfrom = date('Y-m-d 15:31:00', strtotime("-2 days", $timestamp));
        } elseif ($day_of_the_month == 'Saturday') {
            $dayfrom = date('Y-m-d 15:31:00', strtotime("-1 days", $timestamp));
        } elseif ($day_of_the_month == 'Monday') {
            $dayfrom = date('Y-m-d 15:31:00', strtotime("-3 days", $timestamp));
        } else {
            $dayfrom = date('Y-m-d 15:31:00', strtotime("-1 days", $timestamp));
        }

        $lastDay = date("Y-m-t", strtotime("$yearmonth-01"));   // last day of month
        $date = $lastDay . ' 15:30:00';

        echo "<br>";
        echo "last day " . date("l", strtotime($lastDay));   // get weekday of last day
        echo "<br>";

        $end_day = $date;

        echo "day from " . $dayfrom;
        echo "<br>";
        echo "day end " . $end_day;
    }

    public function index()
    {
        $allTransactionToday     = $this->report_model->all_transaction_today();
        $allTransactionYesterday = $this->report_model->all_transaction_yesterday();
        $allData                 = $this->report_model->all_transaction_data();
        $dashboardData['week_data']  = $this->day_count();
        $dashboardData['month_data'] = $this->month_count();

        $dashboardData['today']     = $allTransactionToday;
        $dashboardData['yesterday'] = $allTransactionYesterday;
        $dashboardData['all_data']  = $allData;

        echo json_encode($dashboardData);
    }

    public function all_transaction_today()
    {
        header('Content-type: application/json; charset=utf-8');

        $allTransactionToday     = $this->report_model->all_transaction_today();
        $allTransactionYesterday = $this->report_model->all_transaction_yesterday();
        $allData                 = $this->report_model->all_transaction_data();

        $dashboardData['today']     = $allTransactionToday;
        $dashboardData['yesterday'] = $allTransactionYesterday;
        $dashboardData['all_data']  = $allData;
        $dashboardData['week_data'] = $this->day_count();
        $dashboardData['month_data'] = $this->month_count();

        echo json_encode($dashboardData);
    }

    public function day_count()
    {
        return $this->report_model->all_transaction_this_week();
    }

    public function month_count()
    {
        return $this->report_model->monthly_transaction();
    }
}
