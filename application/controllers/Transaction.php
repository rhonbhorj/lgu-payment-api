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

        $reference = $this->generateReference(16);
        $postdata = [
            "endpoint" => "p2m-generateQR",
            "reference_number"   => $reference,
            "return_url" => "https://example.com/success",
            "callback_url"      => "https://example.com/success/postback",
            "merchant_details" => [
                "txn_amount" => "100",
                "method" => "dynamic",
                "txn_type" => "1",
                "name" => "john doe",
                "mobile_number" => "09123456782"
            ],
            "email_confirmation" => [
                "email" => "your@gmail.com",
                "auto" => "off"
            ]
        ];


        $result = $this->apiservice->generate_qr_api($postdata);
        echo json_encode($result);
    }
}
