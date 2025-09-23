<?php
defined( 'BASEPATH' ) or exit( 'No direct script access allowed' );

class MyServices
 {
    protected $CI;
    protected $secret_key;
    protected $endpoint_base_url;

    public function __construct()
 {
        $this->CI = &get_instance();
        $this->CI->load->database();

        // Key for encryption ( must be the same on both ends )
        // $this->secret_key = $_ENV[ 'SECRET_KEY' ];
        $this->endpoint_base_url = $_ENV[ 'ENDPOINT_BASE_URL' ];
    }

    // Generation of Token were developed here as well to lessen the steps
    // This will ommit the step of getting token from the External API

    public function call_transaction_api( $request )
 {

        $curl = curl_init();

        curl_setopt_array( $curl, array(
            CURLOPT_URL => 'http://iselco-web.test/api/trans',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>  $request,
           
        ) );

        $response = curl_exec( $curl );

        curl_close( $curl );
        return  $response;
    }
}
