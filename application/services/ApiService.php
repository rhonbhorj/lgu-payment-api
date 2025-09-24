<?php
defined('BASEPATH') or exit('No direct script access allowed');

class ApiService
{
    protected $CI;
    protected $secret_key;
    protected $endpoint_base_url;
    protected $x_api_key;
    protected $x_api_username;
    protected $x_api_password;
    public function __construct()
    {
        $this->CI = &get_instance();
        // $this->CI->load->database();
        // $this->CI->load->model('User_model');
        $this->endpoint_base_url = $_ENV['X_API_ENDPOINT_BASE_URL'];
        $this->x_api_key = $_ENV['X_API_KEY'];
        $this->x_api_username = $_ENV['X_API_USERNAME'];
        $this->x_api_password = $_ENV['X_API_PASSWORD'];
    }

    // Generation of Token were developed here as well to lessen the steps
    // This will ommit the step of getting token from `1the External API

    public function generate_token()
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => rtrim($this->endpoint_base_url, '/') . '/generate-token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'X-API-KEY: ' . $this->x_api_key,
                'X-API-USERNAME: ' . $this->x_api_username,
                'X-API-PASSWORD: ' . $this->x_api_password,
            ),
        ));

        $response = curl_exec($curl);
        $http_status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $response = trim($response);
        $decoded = json_decode($response, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            $response = preg_replace('/\x00+$/', '', $response);
            $decoded = json_decode($response, true);
        }
        return [
            'response' => $decoded,
            'status_code' => $http_status_code
        ];
    }

    public function call_external_api($v)
    {
        $generated_token = $this->generate_token();

        $endpoint = $this->endpoint_base_url . '/pgw/api/v1/transactions/new/';
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL =>  $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($v),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $generated_token
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        // expecting to be a json encoded response
        $resp['response'] =  json_decode($response, true);
        // $resp[ 'status_code' ] = $generated_token;

        return $resp;
    }


    public function call_external_api_cash($ref_id)
    {
        $cancel = array('reference_number' => $ref_id);
        $curl = curl_init();
        $generated_token = $this->generate_token();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->endpoint_base_url . '/pgw/api/v1/transactions/cancel/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($cancel),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $generated_token['response']['data']['token'],
            ),
        ));

        $response = curl_exec($curl);
        $http_status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        // Decode JSON response
        $decoded_response = json_decode($response, true);

        // Return both status code and response data
        return array(
            'status_code' => $http_status_code,
            'response' => $decoded_response
        );
    }

    public function generate_qr_api($data)
    {

        $postdata = [
            "endpoint" => "p2m-generateQR",
            "reference_number" => $data['reference'],
            "return_url"       => $data['return_url'],
            "callback_url"     => $data['callback_url'],
            "merchant_details" => [
                "txn_amount"    => $data['amount'],
                "method"        => "dynamic",
                "txn_type"      => "1",
                "name"          => $data['name'],
                "mobile_number" => $data['mobile_number']
            ],
            "email_confirmation" => [
                "email" => $data['email'],
                "auto"  => "off"
            ]
        ];

        $tokenResponse = $this->generate_token();
        $token = null;

        if (
            isset($tokenResponse['response']['data']['token']) &&
            ($tokenResponse['status_code'] == 200 || $tokenResponse['status_code'] == 201)
        ) {
            $token = $tokenResponse['response']['data']['token'];
        } else {
            return [
                'status_code' => 401,
                'status' => false,
                'message' => 'Failed to generate authentication token',
                'error' => $tokenResponse['response']['message'] ?? 'Authentication failed'
            ];
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => rtrim($this->endpoint_base_url, '/') . '/v1/cashin',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($postdata),
            CURLOPT_HTTPHEADER => array(
                "X-API-KEY: {$this->x_api_key}",
                "X-API-USERNAME: {$this->x_api_username}",
                "X-API-PASSWORD: {$this->x_api_password}",
                'Content-Type: application/json',
                "Authorization: Bearer {$token}",
            ),
        ));

        $response = curl_exec($curl);
        $http_status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($response === false) {
            return [
                'status_code' => 500,
                'status' => false,
                'message' => 'Curl error',
                'error' => curl_error($curl)
            ];
        }

        $response = preg_replace('/null\s*$/', '', $response);
        $decoded = json_decode($response, true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return [
                'status_code' => 500,
                'status' => false,
                'message' => 'Invalid JSON response',
                'raw_response' => $response
            ];
        }

        if ($http_status_code >= 400) {
            return [
                'status_code' => $http_status_code,
                'status' => false,
                'message' => $decoded['message'] ?? 'API request failed',
                'error_details' => $decoded['errors'] ?? null,
                'raw_response' => $decoded
            ];
        }

        return [
            'status_code' => $http_status_code,
            'status' => true,
            'message' => 'Created Successfully!',
            'data' => $decoded['data'] ?? $decoded
        ];
    }
}
