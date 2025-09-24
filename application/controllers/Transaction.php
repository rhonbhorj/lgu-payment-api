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

        $data = [
            "reference"      => $this->generateReference(16),
            "name"           => trim($this->input->post('name', TRUE)),
            "amount"         => trim($this->input->post('amount', TRUE)),
            "mobile_number"  => trim($this->input->post('mobile_number', TRUE)),
            "email"          => trim($this->input->post('email', TRUE)),
            "return_url"     => base_url('/success'),
            "callback_url"   => base_url('/success/postback')
        ];

        $result = $this->apiservice->generate_qr_api($data);
        echo json_encode($result);
    }
}
