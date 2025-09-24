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

        //data
        $reference = $this->generateReference(16);
        $name          = trim($this->input->post('name', TRUE));
        $amount        = trim($this->input->post('amount', TRUE));
        $mobile_number = trim($this->input->post('mobile_number', TRUE));
        $email         = trim($this->input->post('email', TRUE));

        //for postback
        $return_url         = base_url('/success');
        $callback_url       = base_url('/success/postback');


        $postdata = [
            "endpoint" => "p2m-generateQR",
            "reference_number"   => $reference,
            "return_url" => $return_url,
            "callback_url"      => $callback_url,
            "merchant_details" => [
                "txn_amount" =>  $amount,
                "method" => "dynamic",
                "txn_type" => "1",
                "name" => $name,
                "mobile_number" => $mobile_number
            ],
            "email_confirmation" => [
                "email" => $email,
                "auto" => "off"
            ]
        ];


        $result = $this->apiservice->generate_qr_api($postdata);
        echo json_encode($result);
    }
}
