<?php


defined('BASEPATH') or exit('No direct script access allowed');


class Transaction extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Api_model');
        $this->load->library('../services/ApiService');
        header("Content-Type: application/json");
        $this->load->model('Trans_Model', 'transaction');

        header("Access-Control-Allow-Origin: http://lgu-payment-webapp.test"); // allow your frontend
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    }


    private function generateReference($length = 16)
    {
        return strtoupper(substr(str_shuffle(str_repeat(
            '0123456789ABCDEFGHIJKLMNOPQRSTUVWXFYZabcdefghijklmnopqrstuvwxyz',
            $length
        )), 0, $length));
    }


    public function dotransac()
    {
        // Collect form inputs

        $mobile = trim($this->input->post('mobile_number', TRUE));
        if (!empty($mobile)) {
            // Convert 09xxxxxxxxx → 639xxxxxxxxx
            if (preg_match('/^09\d{9}$/', $mobile)) {
                $mobile = '63' . substr($mobile, 1);
            }
        } else {
            $mobile = "639000000000"; // fallback
        }

        $email = trim($this->input->post('email', TRUE));
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = "dev@netglobalsolutions.net"; // fallback default
        }

        $data = [
            "reference"      => $this->generateReference(16),
            "refid" => "REF-" . date('YmdHis'),
            "name"           => trim($this->input->post('name', TRUE)),
            "amount"         => trim($this->input->post('amount', TRUE)),
            "mobile_number"  => $mobile,
            "email"          => $email,
            "convience_fee"  => trim($this->input->post('convience_fee', TRUE)),
            "company"  => trim($this->input->post('company', TRUE)),
            "return_url"     => base_url('/success'),
            "callback_url"   => base_url('/success/postback')
        ];

        // Call external API
        $result = $this->apiservice->generate_qr_api($data);

        // Extract data from API response
        $apiData = $result['data'] ?? [];

        // Prepare transaction record

        // $data['reference']
        $transaction = [
            'trans_no'        => $apiData['reference_number'] ?? $data['reference'],
            'trans_payor'     => $data['name'],
            'trans_mobile'    => '09771741876',
            'trans_email'     => 'devs@netglobalsolutions.net',
            'trans_company'     => $data['company'],
            'trans_sub_total' => $data['amount'],
            'trans_conv_fee'  => $data['convience_fee'],
            'trans_refid'     => $data['refid'] ?? '',
            'trans_txid'      => $apiData['txn_ref'] ?? '',
            'trans_ref'      => $data['reference'] ?? '',
            'trans_raw_string' => $apiData['raw_string'] ?? '',   // ✅ store raw_string from API response
            'trans_status'    => 'CREATED'
        ];

        // Save transaction
        $insert_id = $this->transaction->create_transaction($transaction);

        // Final response
        echo json_encode([
            'success'        => true,
            'transaction_id' => $insert_id,
            'api_result'     => $result
        ]);
    }
}
