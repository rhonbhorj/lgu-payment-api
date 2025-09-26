<?php


defined('BASEPATH') or exit('No direct script access allowed');


class Transaction extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Api_model', 'Api');
        $this->load->library('../services/ApiService');
        header("Content-Type: application/json");
        $this->load->model('Trans_Model', 'transaction');

        header("Access-Control-Allow-Origin:*");
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
        // 1. Check API Key
        $api_key = $this->input->get_request_header('X-API-KEY');
        if (empty($api_key)) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing API Key'
            ]);
            return;
        }

        $validKey = $this->Api->validate_api_key($api_key);
        if (!$validKey) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid or Inactive API Key'
            ]);
            return;
        }

        // 2. Get posted reference id
        $refid = trim($this->input->post('reference_number', TRUE));

        // 3. Check if transaction with same refid already exists
        $existing = $this->transaction->check_refid_exists($refid);
        if ($existing) {
            echo json_encode([
                'success' => false,
                'message' => 'Transaction with this reference already exists',
                'transaction_id' => $existing->trans_id
            ]);
            return;
        }

        // 4. Collect other form inputs
        $mobile = trim($this->input->post('mobile_number', TRUE));
        if (!empty($mobile)) {
            if (preg_match('/^09\d{9}$/', $mobile)) {
                $mobile = '63' . substr($mobile, 1);
            }
        } else {
            $mobile = "639000000000"; // fallback
        }

        $email = trim($this->input->post('email', TRUE));
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = "devs@netglobalsolutions.net"; // fallback
        }

        $data = [
            "reference"      => $this->generateReference(16),
            "refid"          => $refid,
            "name"           => trim($this->input->post('name', TRUE)),
            "amount"         => trim($this->input->post('amount', TRUE)),
            "mobile_number"  => $mobile,
            "email"          => $email,
            "convience_fee"  => trim($this->input->post('convience_fee', TRUE)),
            "company"        => trim($this->input->post('company', TRUE)),
            "return_url"     => base_url('/success'),
            "callback_url"   => base_url('/success/postback')
        ];

        // 5. Call API Service
        $result = $this->apiservice->generate_qr_api($data);
        $apiData = $result['data'] ?? [];

        // 6. Prepare transaction data for DB
        $transaction = [
            'trans_no'         => $apiData['reference_number'] ?? $data['reference'],
            'trans_payor'      => $data['name'],
            'trans_mobile'     => $mobile,
            'trans_email'      => $email,
            'trans_company'    => $data['company'],
            'trans_sub_total'  => $data['amount'],
            'trans_conv_fee'   => $data['convience_fee'],
            'trans_refid'      => $data['refid'] ?? '',
            'trans_txid'       => $apiData['txn_ref'] ?? '',
            'trans_ref'        => $data['reference'] ?? '',
            'trans_raw_string' => $apiData['raw_string'] ?? '',
            'trans_status'     => 'CREATED'
        ];

        // 7. Insert new transaction
        $insert_id = $this->transaction->create_transaction($transaction);

        // 8. Final JSON response
        echo json_encode([
            'success'        => true,
            'transaction_id' => $insert_id,
            'api_result'     => $result
        ]);
    }
}
