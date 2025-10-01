<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Report_model extends CI_Model
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Manila');
    }


    public function all_transaction_today_dept($dept)
    {
        // Set timezone
        date_default_timezone_set('Asia/Manila');

        // Calculate start and end datetime (15:01 yesterday to 15:00 today)
        $startDateTime = date('Y-m-d 15:30:00', strtotime('yesterday'));

        // Select the required fields, filtering out CASH payments
        $this->db->select('
            COALESCE(SUM(CASE WHEN type_of_payment != "CASH" THEN Convenience_Fee ELSE 0 END), 0) AS conv_fee,
            COALESCE(SUM(CASE WHEN type_of_payment != "CASH" THEN Sub_Total ELSE 0 END), 0) AS sub_total,
            COALESCE(SUM(CASE WHEN type_of_payment != "CASH" THEN Grand_Total ELSE 0 END), 0) AS grand_total,
            COUNT(CASE WHEN type_of_payment != "CASH" THEN Grand_Total END) AS grand_total_count
        ');
        $this->db->from('transaction');
        $this->db->where('Status', 'PAID');
        $this->db->where('Settled_Date >=', $startDateTime);

        // Apply department filter if provided
        if (!empty($dept)) {
            $this->db->where('Dept', $dept);
        }

        // Execute the query
        $query = $this->db->get();

        // Return the result as a row array if found, otherwise false
        return ($query->num_rows() > 0) ? $query->row_array() : false;
    }



    public function all_transaction_today()
    {
        // Set timezone
        date_default_timezone_set('Asia/Manila');

        // Get the day of the week (1 = Monday, 5 = Friday)
        $dayOfWeek = date('N');

        if ($dayOfWeek == 1) { // If today is Monday
            $startDateTime = date('Y-m-d 15:30:00', strtotime('last Friday'));
            $endDateTime = date('Y-m-d 15:30:00'); // Today (Monday) at 3:30 PM
        } else {
            // Default case: Get from yesterday 3:01 PM to today 3:00 PM
            $startDateTime = date('Y-m-d 15:01:00', strtotime('yesterday'));
            $endDateTime = date('Y-m-d 15:00:00');
        }

        $this->db->select('
            COALESCE(SUM(CASE WHEN type_of_payment != "CASH" THEN Convenience_Fee ELSE 0 END), 0) AS conv_fee,
            COALESCE(SUM(CASE WHEN type_of_payment != "CASH" THEN Sub_Total ELSE 0 END), 0) AS sub_total,
            COALESCE(SUM(CASE WHEN type_of_payment != "CASH" THEN Grand_Total ELSE 0 END), 0) AS grand_total,
            COUNT(CASE WHEN type_of_payment != "CASH" THEN Grand_Total END) AS grand_total_count
        ');
        $this->db->from('transaction');
        $this->db->where('Status', 'PAID');
        $this->db->where('Settled_Date >=', $startDateTime);
        $this->db->where('Settled_Date <=', $endDateTime);

        $query = $this->db->get();
        return ($query->num_rows() > 0) ? $query->row_array() : false;
    }




    public function all_transaction_yesterday()
    {
        // Set timezone
        date_default_timezone_set('Asia/Manila'); // Adjust as needed

        // Get current day of the week (1 = Monday, ..., 7 = Sunday)
        $currentDay = date('N');

        if ($currentDay == 1) { // If today is Monday
            // Get the last Friday 15:31 (3:31 PM)
            $startDateTime = date('Y-m-d 15:31:00', strtotime('last Friday'));
            // Get Monday 03:30 (3:30 AM)
            $endDateTime = date('Y-m-d 03:30:00');
        } else { // Default to yesterday's 15:01 - 15:00 range
            $startDateTime = date('Y-m-d 15:01:00', strtotime('-2 days'));
            $endDateTime = date('Y-m-d 15:00:00', strtotime('-1 days'));
        }

        $this->db->select('
        COALESCE(SUM(CASE WHEN type_of_payment != "CASH" THEN Convenience_Fee ELSE 0 END), 0) AS conv_fee,
        COALESCE(SUM(CASE WHEN type_of_payment != "CASH" THEN Sub_Total ELSE 0 END), 0) AS sub_total,
        COALESCE(SUM(CASE WHEN type_of_payment != "CASH" THEN Grand_Total ELSE 0 END), 0) AS grand_total,
        COUNT(CASE WHEN type_of_payment != "CASH" THEN Grand_Total END) AS grand_total_count
    ');
        $this->db->from('transaction');
        $this->db->where('Status', 'PAID');
        $this->db->where('Settled_Date >=', $startDateTime);
        $this->db->where('Settled_Date <=', $endDateTime);

        $query = $this->db->get();
        return ($query->num_rows() > 0) ? $query->row_array() : false;
    }




    public function all_transaction_yesterday_dept($dept)
    {
        // Get yesterday's date
        $yesterday = date('Y-m-d', strtotime('yesterday'));

        $query = "
        SELECT  
            SUM(CASE WHEN type_of_payment != 'CASH' THEN Convenience_Fee ELSE 0 END) AS conv_fee,
            SUM(CASE WHEN type_of_payment != 'CASH' THEN Sub_Total ELSE 0 END) AS sub_total,
            SUM(CASE WHEN type_of_payment != 'CASH' THEN Grand_Total ELSE 0 END) AS grand_total,
            COUNT(CASE WHEN type_of_payment != 'CASH' THEN Grand_Total END) AS grand_total_count
        FROM transaction
        WHERE Settled_Date LIKE ? 
          AND Status = 'PAID'
          AND Dept = ?
    ";

        $data = $this->db->query($query, ["%$yesterday%", $dept]);

        return ($data->num_rows() > 0) ? $data->row_array() : false;
    }


    public  function round_half_up($number, $decimals)
    {
        return round($number, $decimals, PHP_ROUND_HALF_UP);
    }

    public function all_transaction_data()
    {
        $query = "SELECT 
                  SUM(CASE WHEN type_of_payment != 'CASH' THEN Grand_Total ELSE 0 END) AS grand_total,
                  SUM(CASE WHEN type_of_payment != 'CASH' THEN Sub_Total ELSE 0 END) AS sub_total, 
                  SUM(CASE WHEN type_of_payment != 'CASH' THEN Convenience_Fee ELSE 0 END) AS conv_fee,
                  COUNT(CASE WHEN type_of_payment != 'CASH' THEN Grand_Total END) AS grand_total_count
               FROM transaction  
               WHERE Status = 'PAID'";

        $data = $this->db->query($query);
        return $data->row_array() ? $data->row_array() : false;
    }



    public function all_transaction_data_dept($dept)
    {
        // Base SQL query filtering only cashless transactions
        $query = "
    SELECT  
        SUM(CASE WHEN type_of_payment != 'CASH' THEN Grand_Total ELSE 0 END) AS grand_total,
        SUM(CASE WHEN type_of_payment != 'CASH' THEN Sub_Total ELSE 0 END) AS sub_total,
        SUM(CASE WHEN type_of_payment != 'CASH' THEN Convenience_Fee ELSE 0 END) AS conv_fee,
        COUNT(CASE WHEN type_of_payment != 'CASH' THEN Grand_Total END) AS Grand_Total_count
    FROM transaction
    WHERE Status = 'PAID'";

        // Add department filter if provided
        if ($dept !== null) {
            $query .= " AND Dept = " . $this->db->escape($dept);
        }

        $data = $this->db->query($query);

        // Return a single row or false if no data
        return $data->num_rows() > 0 ? $data->row_array() : false;
    }


    public function  all_transaction_this_week()
    {
        date_default_timezone_set('Asia/Manila');
        $today = date('Y-m-d');




        $todaydata = date('l');

        $yesterday = array();

        // Switch case to handle different days of the week
        switch ($todaydata) {
            case 'Monday':
                $friday = date('Y-m-d 15:31:00', strtotime("-3 days", strtotime($today)));
                $monday = date('Y-m-d 15:31:00');

                $data['Monday']  = $this->get_transaction_data($friday, $monday);
                break;

            case 'Tuesday':
                $fridayTomonday = date('Y-m-d 15:31:00', strtotime("-4 days", strtotime($today)));
                $monday = date('Y-m-d 15:31:00', strtotime("-1 days", strtotime($today)));


                $Tuesday = date('Y-m-d 15:31:00');
                $mondayToTuesday = date('Y-m-d 15:31:00', strtotime("-1 days", strtotime($today)));

                $data['Monday']  = $this->get_transaction_data($fridayTomonday, $monday);
                $data['Tuesday']  = $this->get_transaction_data($monday, $Tuesday);



                break;

            case 'Wednesday':
                $friday = date('Y-m-d 15:31:00', strtotime("-5 days", strtotime($today)));
                $monday = date('Y-m-d 15:31:00', strtotime("-2 days", strtotime($today)));


                $Tuesday = date('Y-m-d 15:31:00', strtotime("-1 days", strtotime($today)));
                $Wednesday = date('Y-m-d 15:31:00');

                $data['Monday']  = $this->get_transaction_data($friday, $monday);
                $data['Tuesday']  = $this->get_transaction_data($monday, $Tuesday);
                $data['Wednesday']  = $this->get_transaction_data($Tuesday, $Wednesday);
                break;

            case 'Thursday':
                $friday = date('Y-m-d 15:31:00', strtotime("-6 days", strtotime($today)));
                $monday = date('Y-m-d 15:31:00', strtotime("-3 days", strtotime($today)));


                $Tuesday = date('Y-m-d 15:31:00', strtotime("-2 days", strtotime($today)));

                $Wednesday = date('Y-m-d 15:31:00', strtotime("-1 days", strtotime($today)));
                $Thursday = date('Y-m-d 15:31:00');

                $data['Monday']  = $this->get_transaction_data($friday, $monday);
                $data['Tuesday']  = $this->get_transaction_data($Tuesday, $Tuesday);
                $data['Wednesday']  = $this->get_transaction_data($Wednesday, $Wednesday);
                $data['Wednesday']  = $this->get_transaction_data($Wednesday, $Thursday);
                break;

            case 'Friday':



                $friday = date('Y-m-d 15:31:00', strtotime("-7 days", strtotime($today)));
                $monday = date('Y-m-d 15:31:00', strtotime("-4 days", strtotime($today)));


                $Tuesday = date('Y-m-d 15:31:00', strtotime("-3 days", strtotime($today)));

                $Wednesday = date('Y-m-d 15:31:00', strtotime("-2 days", strtotime($today)));
                $Thursday = date('Y-m-d 15:31:00', strtotime("-1 days", strtotime($today)));
                $Friday = date('Y-m-d 15:31:00');

                $data['Monday']  = $this->get_transaction_data($friday, $monday);
                $data['Tuesday']  = $this->get_transaction_data($monday, $Tuesday);
                $data['Wednesday']  = $this->get_transaction_data($Tuesday, $Wednesday);
                $data['Thursday']  = $this->get_transaction_data($Wednesday, $Thursday);
                $data['Friday']  = $this->get_transaction_data($Thursday, $Friday);
                break;

            case 'Saturday':

                $data['Monday']     = $this->get_transaction_data_notincluded($date);
                $data['Tuesday']    = $this->get_transaction_data_notincluded($date);
                $data['Wednesday']  = $this->get_transaction_data_notincluded($date);
                $data['Thursday']  = $this->get_transaction_data_notincluded($date);
                $data['Friday']     = $this->get_transaction_data_notincluded($date);
                $data['Saturday']     = $this->get_transaction_data_notincluded($date);

                break;

            case 'Sunday':


                $data['Monday']     = $this->get_transaction_data_notincluded($date);
                $data['Tuesday']    = $this->get_transaction_data_notincluded($date);
                $data['Wednesday']  = $this->get_transaction_data_notincluded($date);
                $data['Wednesday']  = $this->get_transaction_data_notincluded($date);
                $data['Friday']     = $this->get_transaction_data_notincluded($date);
                $data['Saturday']   = $this->get_transaction_data_notincluded($date);
                $data['Sunday']     = $this->get_transaction_data_notincluded($date);
                break;

            default:
                break;
        }
        return $data;
    }



    public function  all_transaction_this_week_dept($dept)
    {
        date_default_timezone_set('Asia/Manila');
        $today = date('Y-m-d');




        $todaydata = date('l');

        $yesterday = array();

        // Switch case to handle different days of the week
        switch ($todaydata) {
            case 'Monday':
                $friday = date('Y-m-d 15:31:00', strtotime("-3 days", strtotime($today)));
                $monday = date('Y-m-d 15:31:00');

                $data['Monday']  = $this->get_transaction_data_dept($friday, $monday, $dept);
                break;

            case 'Tuesday':
                $fridayTomonday = date('Y-m-d 15:31:00', strtotime("-4 days", strtotime($today)));
                $monday = date('Y-m-d 15:31:00', strtotime("-1 days", strtotime($today)));


                $Tuesday = date('Y-m-d 15:31:00');
                $mondayToTuesday = date('Y-m-d 15:31:00', strtotime("-1 days", strtotime($today)));

                $data['Monday']  = $this->get_transaction_data_dept($fridayTomonday, $monday, $dept);
                $data['Tuesday']  = $this->get_transaction_data_dept($monday, $Tuesday, $dept);



                break;

            case 'Wednesday':
                $friday = date('Y-m-d 15:31:00', strtotime("-5 days", strtotime($today)));
                $monday = date('Y-m-d 15:31:00', strtotime("-2 days", strtotime($today)));


                $Tuesday = date('Y-m-d 15:31:00', strtotime("-1 days", strtotime($today)));
                $Wednesday = date('Y-m-d 15:31:00');

                $data['Monday']  = $this->get_transaction_data_dept($friday, $monday, $dept);
                $data['Tuesday']  = $this->get_transaction_data_dept($monday, $Tuesday, $dept);
                $data['Wednesday']  = $this->get_transaction_data_dept($Tuesday, $Wednesday, $dept);
                break;

            case 'Thursday':
                $friday = date('Y-m-d 15:31:00', strtotime("-6 days", strtotime($today)));
                $monday = date('Y-m-d 15:31:00', strtotime("-3 days", strtotime($today)));


                $Tuesday = date('Y-m-d 15:31:00', strtotime("-2 days", strtotime($today)));

                $Wednesday = date('Y-m-d 15:31:00', strtotime("-1 days", strtotime($today)));
                $Thursday = date('Y-m-d 15:31:00');

                $data['Monday']  = $this->get_transaction_data_dept($friday, $monday, $dept);
                $data['Tuesday']  = $this->get_transaction_data_dept($Tuesday, $Tuesday, $dept);
                $data['Wednesday']  = $this->get_transaction_data_dept($Wednesday, $Wednesday, $dept);
                $data['Wednesday']  = $this->get_transaction_data_dept($Wednesday, $Thursday, $dept);
                break;

            case 'Friday':



                $friday = date('Y-m-d 15:31:00', strtotime("-7 days", strtotime($today)));
                $monday = date('Y-m-d 15:31:00', strtotime("-4 days", strtotime($today)));


                $Tuesday = date('Y-m-d 15:31:00', strtotime("-3 days", strtotime($today)));

                $Wednesday = date('Y-m-d 15:31:00', strtotime("-2 days", strtotime($today)));
                $Thursday = date('Y-m-d 15:31:00', strtotime("-1 days", strtotime($today)));
                $Friday = date('Y-m-d 15:31:00');

                $data['Monday']  = $this->get_transaction_data_dept($friday, $monday, $dept);
                $data['Tuesday']  = $this->get_transaction_data_dept($monday, $Tuesday, $dept);
                $data['Wednesday']  = $this->get_transaction_data_dept($Tuesday, $Wednesday, $dept);
                $data['Thursday']  = $this->get_transaction_data_dept($Wednesday, $Thursday, $dept);
                $data['Friday']  = $this->get_transaction_data_dept($Thursday, $Friday, $dept);
                break;

            case 'Saturday':

                $data['Monday']     = $this->get_transaction_data_notincluded($date);
                $data['Tuesday']    = $this->get_transaction_data_notincluded($date);
                $data['Wednesday']  = $this->get_transaction_data_notincluded($date);
                $data['Thursday']  = $this->get_transaction_data_notincluded($date);
                $data['Friday']     = $this->get_transaction_data_notincluded($date);
                $data['Saturday']     = $this->get_transaction_data_notincluded($date);

                break;

            case 'Sunday':


                $data['Monday']     = $this->get_transaction_data_notincluded($date);
                $data['Tuesday']    = $this->get_transaction_data_notincluded($date);
                $data['Wednesday']  = $this->get_transaction_data_notincluded($date);
                $data['Wednesday']  = $this->get_transaction_data_notincluded($date);
                $data['Friday']     = $this->get_transaction_data_notincluded($date);
                $data['Saturday']   = $this->get_transaction_data_notincluded($date);
                $data['Sunday']     = $this->get_transaction_data_notincluded($date);
                break;

            default:


                break;
        }
        return $data;
    }


    public function get_transaction_data_notincluded($date)
    {


        return $jayParsedAry = [
            "total_count_success" => 0,
            "Sub_Total" => "0",
            "convinience_fee" => "0.00",
            "total_txn_amount" => "0.00",
            "total_count_failed" => 0,
            "total_count_created" => 0,
            "total_cash_count" => "0",
            "date" => $date
        ];
    }

    function get_transaction_data($dateFrom, $dateTo)
    {
        $sql = "SELECT 
                COUNT(Grand_Total) AS grand_Total_count,
                SUM(Sub_Total) AS Sub_Total,
                SUM(Convenience_Fee) AS convinience_fee,
                SUM(Grand_Total) AS total_amt_txn
            FROM transaction 
            WHERE Settled_Date BETWEEN '" . $dateFrom . "' AND '" . $dateTo . "'AND Status='PAID'";

        $data = $this->db->query($sql);
        // return $ngsi_data_sum = $data->num_rows() > 0 ? $data->row_array() : false;

        $resultArray['total_count_success'] = $data->num_rows() > 0 ? (int) $data->row()->grand_Total_count : false;
        $resultArray['Sub_Total'] = $data->num_rows() > 0 ? (int) $data->row()->Sub_Total : false;
        $resultArray['convinience_fee'] = $data->num_rows() > 0 ? number_format((float)$data->row()->convinience_fee, 2, '.', ',') : false;
        $resultArray['total_txn_amount'] = $data->num_rows() > 0 ? $data->row()->total_amt_txn : false;



        $qry = "SELECT COUNT(Sub_Total) AS total_count_failed 
            FROM transaction 
            WHERE Settled_Date BETWEEN '" . $dateFrom . "' AND '" . $dateTo . "' AND Status='FAILED'";
        $data2 = $this->db->query($qry);
        $resultArray['total_count_failed'] = $data2->num_rows() > 0 ? (int) $data2->row()->total_count_failed : false;


        $qry1 = "SELECT COUNT(Sub_Total) AS total_count_created 
            FROM transaction 
            WHERE Settled_Date BETWEEN '" . $dateFrom . " 15:31:00' AND '" . $dateTo . " 15:31:00' AND Status='CREATED'";

        $data3 = $this->db->query($qry1);
        $resultArray['total_count_created'] = $data3->num_rows() > 0 ? (int) $data3->row()->total_count_created : false;



        $qry4 = "SELECT COUNT(Sub_Total) AS total_count_count 
            FROM transaction 
            WHERE Settled_Date BETWEEN '" . $dateFrom . " 15:31:00' AND '" . $dateTo . " 15:31:00'";
        $data4 = $this->db->query($qry4);
        $resultArray['Sub_Total'] = $data4->num_rows() > 0 ?  $data4->row()->total_count_count : false;



        $qry5 = "SELECT COUNT(type_of_payment) AS total_cash_count 
            FROM transaction 
            WHERE Settled_Date BETWEEN '" . $dateFrom . " 15:31:00' AND '" . $dateTo . " 15:31:00' and  type_of_payment = 'CASH'  AND Status='PAID'";
        $data5 = $this->db->query($qry5);
        $resultArray['total_cash_count'] = $data4->num_rows() > 0 ?  $data5->row()->total_cash_count : false;
        $resultArray['date'] = $dateTo;
        return $resultArray;
    }





    function get_transaction_data_dept($dateFrom, $dateTo, $dept)
    {
        $sql = "SELECT 
                COUNT(Grand_Total) AS grand_Total_count,
                SUM(Sub_Total) AS Sub_Total,
                SUM(Convenience_Fee) AS convinience_fee,
                SUM(Grand_Total) AS total_amt_txn
            FROM transaction 
            WHERE Settled_Date BETWEEN '" . $dateFrom . "' AND '" . $dateTo . "'AND Status='PAID'AND Dept ='" . $dept . "'";

        $data = $this->db->query($sql);
        // return $ngsi_data_sum = $data->num_rows() > 0 ? $data->row_array() : false;

        $resultArray['total_count_success'] = $data->num_rows() > 0 ? (int) $data->row()->grand_Total_count : false;
        $resultArray['Sub_Total'] = $data->num_rows() > 0 ? (int) $data->row()->Sub_Total : false;
        $resultArray['convinience_fee'] = $data->num_rows() > 0 ? number_format((float)$data->row()->convinience_fee, 2, '.', ',') : false;
        $resultArray['total_txn_amount'] = $data->num_rows() > 0 ? $data->row()->total_amt_txn : false;



        $qry = "SELECT COUNT(Sub_Total) AS total_count_failed 
            FROM transaction 
            WHERE Settled_Date BETWEEN '" . $dateFrom . "' AND '" . $dateTo . "' AND Status='FAILED'AND Dept ='" . $dept . "'";
        $data2 = $this->db->query($qry);
        $resultArray['total_count_failed'] = $data2->num_rows() > 0 ? (int) $data2->row()->total_count_failed : false;


        $qry1 = "SELECT COUNT(Sub_Total) AS total_count_created 
            FROM transaction 
            WHERE Settled_Date BETWEEN '" . $dateFrom . " 15:31:00' AND '" . $dateTo . " 15:31:00' AND Status='CREATED' AND Dept ='" . $dept . "'";

        $data3 = $this->db->query($qry1);
        $resultArray['total_count_created'] = $data3->num_rows() > 0 ? (int) $data3->row()->total_count_created : false;



        $qry4 = "SELECT COUNT(Sub_Total) AS total_count_count 
            FROM transaction 
            WHERE Settled_Date BETWEEN '" . $dateFrom . " 15:31:00' AND '" . $dateTo . " 15:31:00'";
        $data4 = $this->db->query($qry4);
        $resultArray['Sub_Total'] = $data4->num_rows() > 0 ?  $data4->row()->total_count_count : false;



        $qry5 = "SELECT COUNT(type_of_payment) AS total_cash_count 
            FROM transaction 
            WHERE Settled_Date BETWEEN '" . $dateFrom . " 15:31:00' AND '" . $dateTo . " 15:31:00' and  type_of_payment = 'CASH'  AND Status='PAID'AND Dept ='" . $dept . "'";
        $data5 = $this->db->query($qry5);
        $resultArray['total_cash_count'] = $data4->num_rows() > 0 ?  $data5->row()->total_cash_count : false;
        $resultArray['date'] = $dateTo;
        return $resultArray;
    }




    public function get_monthly_data_transaction($month)
    {
         // $currentMonth="12";
         $yearmonth =  date('Y-'.$month) ;
         
             
         // echo "<br>";
 
                 $timestamp = strtotime("$yearmonth-01");
 
                 $day_of_the_month = date("l", $timestamp);   //convert to word
         //  echo "first day" .$day_of_the_month;
 
 
   
        if($day_of_the_month=='Sunday'){
            $dayfrom=date('Y-m-d 15:31:00',strtotime("-2 days", $timestamp));
        }elseif($day_of_the_month=='Saturday'){
            $dayfrom=date('Y-m-d 15:31:00',strtotime("-1 days", $timestamp));
        }elseif($day_of_the_month=='Monday'){
            $dayfrom=date('Y-m-d 15:31:00',strtotime("-3 days", $timestamp));
        }else{
            $dayfrom=date('Y-m-d 15:31:00',strtotime("-1 days", $timestamp));
        }





        $lastDay = strtotime("$yearmonth-t");    
        $lastday_of_month = date("l",$lastDay);   //get day  word 
 
 
 
        $lastDay = date("Y-m-t", strtotime("$yearmonth-01"));   //get the last day of the month
        $date= $lastDay.' 15:31:00';
 
 
        
          $lastday_of_month = date("l", strtotime($lastDay));   //get the word
     
 
 
 
 
 
        if($lastday_of_month=='Sunday'){
            // $end_day=date("Y-m-d H:i:s", strtotime($date . " -2 day"));
            $end_day=$date;
        }elseif($lastday_of_month=='Saturday'){
            // $end_day=date("Y-m-d H:i:s", strtotime($date . " -1 day"));
            $end_day=$date; 
        }else{
            $end_day=$date;
        }
        
            //    echo "day from" . $dayfrom;
        
            //    echo "<br>";
        
            //    echo "day end" .     $end_day;
        



        $qry_select = "SELECT sum(Convenience_Fee) AS ngsi_fee 
        FROM transaction  
        
       WHERE Settled_Date BETWEEN '" . $dayfrom . "' AND '" . $end_day . "' and  type_of_payment != 'CASH'  AND Status='PAID'";
    //  AND Status='PAID'";

        $qry_result = $this->db->query($qry_select);
        $ngsi_fee = $qry_result->num_rows() > 0 ? $qry_result->result_array() : false;

        $sql = "SELECT 
        COUNT(Sub_Total) AS total_count,
        SUM(Sub_Total) AS Sub_Total,
        SUM(Convenience_Fee) AS convinience_fee,
        SUM(Grand_Total) AS total_amt_txn
        FROM transaction 
        WHERE Settled_Date BETWEEN '" . $dayfrom . "' AND '" . $end_day . "' and  type_of_payment != 'CASH'  AND Status='PAID'";

        $data = $this->db->query($sql);
        $result['total_count_success'] = $data->num_rows() > 0 ? (int) $data->row()->total_count : false;
        $result['Sub_Total'] = $data->num_rows() > 0 ? (int) $data->row()->Sub_Total : false;
        $result['total_amt_txn'] = $data->num_rows() > 0 ? (int) $data->row()->total_amt_txn : false;
        $result['convinience_fee'] = $data->num_rows() > 0 ? number_format((float)$data->row()->convinience_fee, 2, '.', ',') : false;


        $qry = "SELECT COUNT(Sub_Total) AS total_count_failed 
        FROM transaction 
        WHERE Settled_Date BETWEEN '" . $dayfrom . "' AND '" . $end_day . "' and  type_of_payment != 'CASH'  AND Status='FAILED'";


        $data2 = $this->db->query($qry);
        $result['total_count_failed'] = $data2->num_rows() > 0 ? (int) $data2->row()->total_count_failed : false;


        $qry1 = "SELECT COUNT(Sub_Total) AS total_count_created 
        FROM transaction 
        WHERE Settled_Date BETWEEN '" . $dayfrom . "' AND '" . $end_day . "' and  type_of_payment != 'CASH'  AND Status='CREATED'";


        $data3 = $this->db->query($qry1);
        $result['total_count_created'] = $data3->num_rows() > 0 ? (int) $data3->row()->total_count_created : false;


        $qry4 = "SELECT COUNT(Sub_Total) AS total_count 
        FROM transaction 
        WHERE Settled_Date BETWEEN '" . $dayfrom . "' AND '" . $end_day . "' and  type_of_payment != 'CASH'";

        $data4 = $this->db->query($qry4);
        $result['total_count'] = $data4->num_rows() > 0 ? (int) $data4->row()->total_count : false;



        $qry5 = "SELECT COUNT(CASE WHEN type_of_payment = 'CASH' THEN 1 END) AS total_cash_count 
        FROM transaction 

        WHERE cash_settlement_date BETWEEN '" . $dayfrom . "' AND '" . $end_day . "' and  type_of_payment != 'CASH'";
        // WHERE cash_settlement_date LIKE " . $likePatterns[$i] . " " . $deptFilter;
        $data5 = $this->db->query($qry5);
        $result['total_cash_count'] = $data5->num_rows() > 0 ? (int)$data5->row()->total_cash_count : false;

            return $result;

 
    }


public function monthly_transaction_dept($dept)
{
    date_default_timezone_set('Asia/Manila');
    $currentMonth = date('n'); // 1–12 without leading zero
    $months = [
        1 => 'January', 2 => 'February', 3 => 'March',
        4 => 'April',   5 => 'May',      6 => 'June',
        7 => 'July',    8 => 'August',   9 => 'September',
        10 => 'October', 11 => 'November', 12 => 'December'
    ];

    $data = [];
    for ($m = 1; $m <= $currentMonth; $m++) {
        $monthKey = str_pad($m, 2, '0', STR_PAD_LEFT); // e.g. 01, 02, ...
        $data[$months[$m]] = $this->get_monthly_data_transaction_dept($monthKey, $dept);
    }

    return $data;
}


  ///get month transaction 
  public function monthly_transaction()
  {
      date_default_timezone_set('Asia/Manila');
      $currentMonth= date('m');

      $day = date('Y-m-d');
      
          $yearmonth =  date('Y-m'); 
          $timestamp = strtotime("$yearmonth-01");
          $dayOfWeek = date("l", $timestamp);
      



      // Switch case to handle different days of the week
      switch ($currentMonth) {

          case '01':
              $data['January']  = $this->get_monthly_data_transaction('01');     
              break;

          case '02':
              $data['January']  = $this->get_monthly_data_transaction('01');
              $data['February']  = $this->get_monthly_data_transaction('02');


              break;

          case '03':
                   
              $data['January']  = $this->get_monthly_data_transaction('01');
              $data['February']  = $this->get_monthly_data_transaction('02');
              $data['March']  = $this->get_monthly_data_transaction('03');
        
              break;

          case '04':
              $data['January']  = $this->get_monthly_data_transaction('01');
              $data['February']  = $this->get_monthly_data_transaction('02');
              $data['March']  = $this->get_monthly_data_transaction('03');
              $data['April']  = $this->get_monthly_data_transaction('04');
              break;

          case '05':
              $data['January']  = $this->get_monthly_data_transaction('01');
              $data['February']  = $this->get_monthly_data_transaction('02');
              $data['March']  = $this->get_monthly_data_transaction('03');
              $data['April']  = $this->get_monthly_data_transaction('04');
              $data['May']  = $this->get_monthly_data_transaction('05');


       
              break;

          case '06':
              $data['January']  = $this->get_monthly_data_transaction('01');
              $data['February']  = $this->get_monthly_data_transaction('02');
              $data['March']  = $this->get_monthly_data_transaction('03');
              $data['April']  = $this->get_monthly_data_transaction('04');
              $data['May']  = $this->get_monthly_data_transaction('05');
              $data['June']  = $this->get_monthly_data_transaction('06');

              break;

          case '07':
              $data['January']  = $this->get_monthly_data_transaction('01');
              $data['February']  = $this->get_monthly_data_transaction('02');
              $data['March']  = $this->get_monthly_data_transaction('03');
              $data['April']  = $this->get_monthly_data_transaction('04');
              $data['May']  = $this->get_monthly_data_transaction('05');
              $data['June']  = $this->get_monthly_data_transaction('06');
              $data['July']  = $this->get_monthly_data_transaction('07');
              
              break;

              
          case '08':
              $data['January']  = $this->get_monthly_data_transaction('01');
              $data['February']  = $this->get_monthly_data_transaction('02');
              $data['March']  = $this->get_monthly_data_transaction('03');
              $data['April']  = $this->get_monthly_data_transaction('04');
              $data['May']  = $this->get_monthly_data_transaction('05');
              $data['June']  = $this->get_monthly_data_transaction('06');
              $data['July']  = $this->get_monthly_data_transaction('07');
              $data['August']  = $this->get_monthly_data_transaction('08');
              break;
              
          case '09':
              $data['January']  = $this->get_monthly_data_transaction('01');
              $data['February']  = $this->get_monthly_data_transaction('02');
              $data['March']  = $this->get_monthly_data_transaction('03');
              $data['April']  = $this->get_monthly_data_transaction('04');
              $data['May']  = $this->get_monthly_data_transaction('05');
              $data['June']  = $this->get_monthly_data_transaction('06');
              $data['July']  = $this->get_monthly_data_transaction('07');
              $data['August']  = $this->get_monthly_data_transaction('08');
              $data['September']  = $this->get_monthly_data_transaction('09');
              break;
              
          case '10':
              $data['January']  = $this->get_monthly_data_transaction('01');
              $data['February']  = $this->get_monthly_data_transaction('02');
              $data['March']  = $this->get_monthly_data_transaction('03');
              $data['April']  = $this->get_monthly_data_transaction('04');
              $data['May']  = $this->get_monthly_data_transaction('05');
              $data['June']  = $this->get_monthly_data_transaction('06');
              $data['July']  = $this->get_monthly_data_transaction('07');
              $data['August']  = $this->get_monthly_data_transaction('08');
              $data['September']  = $this->get_monthly_data_transaction('09');
              $data['October']  = $this->get_monthly_data_transaction('10');
              break;
              
          case '11':
              $data['January']  = $this->get_monthly_data_transaction('01');
              $data['February']  = $this->get_monthly_data_transaction('02');
              $data['March']  = $this->get_monthly_data_transaction('03');
              $data['April']  = $this->get_monthly_data_transaction('04');
              $data['May']  = $this->get_monthly_data_transaction('05');
              $data['June']  = $this->get_monthly_data_transaction('06');
              $data['July']  = $this->get_monthly_data_transaction('07');
              $data['August']  = $this->get_monthly_data_transaction('08');
              $data['September']  = $this->get_monthly_data_transaction('09');
              $data['October']  = $this->get_monthly_data_transaction('10');
              $data['November']  = $this->get_monthly_data_transaction('11');
              break;
              
          case '12':
              $data['January']  = $this->get_monthly_data_transaction('01');
              $data['February']  = $this->get_monthly_data_transaction('02');
              $data['March']  = $this->get_monthly_data_transaction('03');
              $data['April']  = $this->get_monthly_data_transaction('04');
              $data['May']  = $this->get_monthly_data_transaction('05');
              $data['June']  = $this->get_monthly_data_transaction('06');
              $data['July']  = $this->get_monthly_data_transaction('07');
              $data['August']  = $this->get_monthly_data_transaction('08');
              $data['September']  = $this->get_monthly_data_transaction('09');
              $data['October']  = $this->get_monthly_data_transaction('10');
              $data['November']  = $this->get_monthly_data_transaction('11');
              $data['December']  = $this->get_monthly_data_transaction('12');
              break;

          default:
   
            
              break;
      }
      return $data;

  }




  public function get_monthly_data_transaction_dept($month,$dept)
  {

    // $currentMonth="12";
    $yearmonth =  date('Y-'.$month) ;
       
           
    // echo "<br>";

            $timestamp = strtotime("$yearmonth-01");

            $day_of_the_month = date("l", $timestamp);   //convert to word
    //  echo "first day" .$day_of_the_month;



   if($day_of_the_month=='Sunday'){
       $dayfrom=date('Y-m-d 15:31:00',strtotime("-2 days", $timestamp));
   }elseif($day_of_the_month=='Saturday'){
       $dayfrom=date('Y-m-d 15:31:00',strtotime("-1 days", $timestamp));
   }elseif($day_of_the_month=='Monday'){
       $dayfrom=date('Y-m-d 15:31:00',strtotime("-3 days", $timestamp));
   }else{
       $dayfrom=date('Y-m-d 15:31:00',strtotime("-1 days", $timestamp));
   }





   $lastDay = strtotime("$yearmonth-t");    
   $lastday_of_month = date("l",$lastDay);   //get day  word 



   $lastDay = date("Y-m-t", strtotime("$yearmonth-01"));   //get the last day of the month
   $date= $lastDay.' 15:31:00';


   
     $lastday_of_month = date("l", strtotime($lastDay));   //get the word






   if($lastday_of_month=='Sunday'){
     
       $end_day=$date;
   }elseif($lastday_of_month=='Saturday'){
    
       $end_day=$date; 
   }else{
       $end_day=$date;
   }
   
   


   $qry_select = "SELECT sum(Convenience_Fee) AS ngsi_fee 
   FROM transaction  
   
  WHERE Settled_Date BETWEEN '" . $dayfrom . "' AND '" . $end_day . "' and  type_of_payment != 'CASH'  AND Status='PAID' and Dept='".$dept."'";

   $qry_result = $this->db->query($qry_select);
   $ngsi_fee = $qry_result->num_rows() > 0 ? $qry_result->result_array() : false;

   $sql = "SELECT 
   COUNT(Sub_Total) AS total_count,
   SUM(Sub_Total) AS Sub_Total,
   SUM(Convenience_Fee) AS convinience_fee,
   SUM(Grand_Total) AS total_amt_txn
   FROM transaction 
   WHERE Settled_Date BETWEEN '" . $dayfrom . "' AND '" . $end_day . "' and  type_of_payment != 'CASH'  AND Status='PAID' and Dept='".$dept."'";

   $data = $this->db->query($sql);
   $result['total_count_success'] = $data->num_rows() > 0 ? (int) $data->row()->total_count : false;
   $result['Sub_Total'] = $data->num_rows() > 0 ? (int) $data->row()->Sub_Total : false;
   $result['total_amt_txn'] = $data->num_rows() > 0 ? (int) $data->row()->total_amt_txn : false;
   $result['convinience_fee'] = $data->num_rows() > 0 ? number_format((float)$data->row()->convinience_fee, 2, '.', ',') : false;


   $qry = "SELECT COUNT(Sub_Total) AS total_count_failed 
   FROM transaction 
   WHERE Settled_Date BETWEEN '" . $dayfrom . "' AND '" . $end_day . "' and  type_of_payment != 'CASH'  AND Status='FAILED' and Dept='".$dept."'";


   $data2 = $this->db->query($qry);
   $result['total_count_failed'] = $data2->num_rows() > 0 ? (int) $data2->row()->total_count_failed : false;


   $qry1 = "SELECT COUNT(Sub_Total) AS total_count_created 
   FROM transaction 
   WHERE Settled_Date BETWEEN '" . $dayfrom . "' AND '" . $end_day . "' and  type_of_payment != 'CASH'  AND Status='CREATED'and Dept='".$dept."'";


   $data3 = $this->db->query($qry1);
   $result['total_count_created'] = $data3->num_rows() > 0 ? (int) $data3->row()->total_count_created : false;


   $qry4 = "SELECT COUNT(Sub_Total) AS total_count 
   FROM transaction 
   WHERE Settled_Date BETWEEN '" . $dayfrom . "' AND '" . $end_day . "' and  type_of_payment != 'CASH' and Dept='".$dept."'";

   $data4 = $this->db->query($qry4);
   $result['total_count'] = $data4->num_rows() > 0 ? (int) $data4->row()->total_count : false;



   $qry5 = "SELECT COUNT(CASE WHEN type_of_payment = 'CASH' THEN 1 END) AS total_cash_count 
   FROM transaction 

   WHERE cash_settlement_date BETWEEN '" . $dayfrom . "' AND '" . $end_day . "' and  type_of_payment != 'CASH'and Dept='".$dept."'";
    $data5 = $this->db->query($qry5);
   $result['total_cash_count'] = $data5->num_rows() > 0 ? (int)$data5->row()->total_cash_count : false;

       return $result;

  }   

   
}
