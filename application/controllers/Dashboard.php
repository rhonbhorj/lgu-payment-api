<?php

defined('BASEPATH') or exit('No direct script access allowed');


class Dashboard extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Api_model');
        header("Content-Type: application/json");
    }

    public function month()
    {
        date_default_timezone_set('Asia/Manila');

        $month = "03";
        echo     $yearmonth =  date('Y-' . $month);


        echo "<br>";

        $timestamp = strtotime("$yearmonth-01");

        $day_of_the_month = date("l", $timestamp);   //convert to word
        echo "first day" . $day_of_the_month;


        if ($day_of_the_month == 'Sunday') {
            $dayfrom = date('Y-m-d 15:31:00', strtotime("-2 days", $timestamp));
        } elseif ($day_of_the_month == 'Saturday') {
            $dayfrom = date('Y-m-d 15:31:00', strtotime("-1 days", $timestamp));
        } elseif ($day_of_the_month == 'Monday') {
            $dayfrom = date('Y-m-d 15:31:00', strtotime("-3 days", $timestamp));
        } else {
            $dayfrom = date('Y-m-d 15:31:00', strtotime("-1 days", $timestamp));
        }

        $lastDay = strtotime("$yearmonth-t");
        $lastday_of_month = date("l", $lastDay);   //get day  word 

        $lastDay = date("Y-m-t", strtotime("$yearmonth-01"));   //get the last day of the month
        $date = $lastDay . ' 15:30:00';

        echo      "<br>";

        echo "last day" . $lastday_of_month = date("l", strtotime($lastDay));   //get the word
        echo "<br>";

        if ($lastday_of_month == 'Sunday') {
            $end_day = $date;
        } elseif ($lastday_of_month == 'Saturday') {
            $end_day = $date;
        } else {
            $end_day = $date;
        }

        echo "day from" . $dayfrom;

        echo "<br>";

        echo "day end" .     $end_day;
    }


    public function index()
    {

        $dept = $this->session->userdata('department');
        $userlevel = $this->session->userdata('userlevel');
        if ($userlevel != "USER") {
            $allTransactionToday = $this->report_model->all_transaction_today();
            $allTransactionYesterday = $this->report_model->all_transaction_yesterday();
            $allData = $this->report_model->all_transaction_data();
            $dashboardData['week_data'] = $this->day_count();
            $dashboardData['month_data'] = $this->month_count();
        } else {

            $allTransactionToday = $this->report_model->all_transaction_today_dept($dept);
            $allTransactionYesterday = $this->report_model->all_transaction_yesterday_dept($dept);
            $allData = $this->report_model->all_transaction_data_dept($dept);
            $dashboardData['week_data'] = $this->day_count_dept($dept);
            $dashboardData['month_data'] = $this->month_count_dept($dept);
        }

        $dashboardData['today'] = $allTransactionToday;
        $dashboardData['yesterday'] = $allTransactionYesterday;
        $dashboardData['all_data'] = $allData;


        echo json_encode($dashboardData);
    }

    public function all_transaction_today_dept()
    {
        header('Content-type: application/json; charset=utf-8');
        $dept = $this->session->userdata('department');
        $allTransactionToday = $this->report_model->all_transaction_today($dept);
        $allTransactionYesterday = $this->report_model->all_transaction_yesterday_dept($dept);
        $allData = $this->report_model->all_transaction_data_dept($dept);

        $dashboardData['today'] = $allTransactionToday;
        $dashboardData['yesterday'] = $allTransactionYesterday;
        $dashboardData['all_data'] = $allData;
        $dashboardData['week_data'] = $this->day_count_dept();
        $dashboardData['month_data'] = $this->month_count_dept();

        echo json_encode($dashboardData);
    }


    public function all_transaction_today()
    {

        header('Content-type: application/json; charset=utf-8');
        $allTransactionToday = $this->report_model->all_transaction_today();
        $allTransactionYesterday = $this->report_model->all_transaction_yesterday();
        $allData = $this->report_model->all_transaction_data();

        $dashboardData['today'] = $allTransactionToday;
        $dashboardData['yesterday'] = $allTransactionYesterday;
        $dashboardData['all_data'] = $allData;
        $dashboardData['week_data'] = $this->day_count();
        $dashboardData['month_data'] = $this->month_count();

        echo json_encode($dashboardData);
    }


    public function day_count()
    {
        return $this->report_model->all_transaction_this_week();
    }



    public function day_count_dept()
    {

        $dept = $this->session->userdata('department');



        return $this->report_model->all_transaction_this_week_dept($dept);
    }
    public function day_count_dept_old()
    {

        $dept = $this->session->userdata('department');


        $today = date('l');
        $yesterday = array();
        switch ($today) {
            case 'Monday':

                for ($i = 0; $i < 1; $i++) {
                    $yesterday[] = date('Y-m-d', strtotime("-$i days", strtotime($today)));
                }
                break;

            case 'Tuesday':

                for ($i = 0; $i < 2; $i++) {
                    $yesterday['data' . ($i + 1)] = date('Y-m-d', strtotime("-$i days", strtotime($today)));
                }
                break;

            case 'Wednesday':
                for ($i = 0; $i < 3; $i++) {
                    $yesterday['data' . ($i + 1)] = date('Y-m-d', strtotime("-$i days", strtotime($today)));
                }
                break;

            case 'Thursday':

                for ($i = 0; $i < 4; $i++) {
                    $yesterday['data' . ($i + 1)] = date('Y-m-d', strtotime("-$i days", strtotime($today)));
                }
                break;

            case 'Friday':

                for ($i = 0; $i < 5; $i++) {
                    $yesterday['data' . ($i + 1)] = date('Y-m-d', strtotime("-$i days", strtotime($today)));
                }
                break;

            case 'Saturday':

                for ($i = 0; $i < 6; $i++) {
                    $yesterday['data' . ($i + 1)] = date('Y-m-d', strtotime("-$i days", strtotime($today)));
                }
                break;

            case 'Sunday':

                for ($i = 0; $i < 7; $i++) {
                    $yesterday['data' . ($i + 1)] = date('Y-m-d', strtotime("-$i days", strtotime($today)));
                }
                break;

            default:
                // Default case to handle any unexpected input
                $yesterday['data'] = $today;
                break;
        }
        //   return $i; 
        return $this->report_model->all_transaction_this_week_dept($yesterday, $i, $dept);
    }


    public function month_count()
    {
        $result = $this->report_model->monthly_transaction();
        return $result;
    }

    public function month_count_dept()
    {
        $dept = $this->session->userdata('department');
        $result = $this->report_model->monthly_transaction_dept($dept);
        return $result;
    }
}
