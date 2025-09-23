<?php if ( ! defined( 'BASEPATH' ) ) exit( 'No direct script access allowed' );

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once ( APPPATH . 'PHPMailer/Exception.php' );
require_once ( APPPATH . 'PHPMailer/SMTP.php' );
require_once ( APPPATH . 'PHPMailer/PHPMailer.php' );


 function generate_token()
{
       $curl = curl_init();
      
       curl_setopt_array( $curl, array(
           CURLOPT_URL =>   $_ENV[ 'ENDPOINT_BASE_URL' ] . '/pgw/api/v1/auth/obtain-token/',
           CURLOPT_RETURNTRANSFER => true,
           CURLOPT_ENCODING => '',
           CURLOPT_MAXREDIRS => 10,
           CURLOPT_TIMEOUT => 0,
           CURLOPT_FOLLOWLOCATION => true,
           CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
           CURLOPT_CUSTOMREQUEST => 'POST',
           CURLOPT_POSTFIELDS =>'{
         "username": "'.  $_ENV[ 'API_USER_NAME' ].'",
         "secret": "'.  $_ENV[ 'API_SECRET' ].'",
         "app_uuid": "'.  $_ENV[ 'API_UUID' ].'"
       }',
           CURLOPT_HTTPHEADER => array(
               'Content-Type: application/json'

           ),
       ) );
     



       $response = curl_exec( $curl );
       $http_status_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
       curl_close( $curl );

       $jdata = 	json_decode( $response, true );
       // // $jdata['httpstatus']=$http_status_code;
       // return $jdata[ 'data' ][ 'token' ];

       $resp[ 'response' ] =  $jdata;
       $resp[ 'status_code' ] = $http_status_code;

return $resp;

   }


if ( ! function_exists( 'sendemail' ) ) 
 {

    function sendemail( $request )
 {


    $generated_token = generate_token();

        $email_messege = [
            'app_uuid' => $_ENV[ 'API_UUID' ],
            'reference_number' => $request["subject"] ,
            'receivers' => [
                $request['email']
            ],
            'subject' => 'Payment Confirmation - Ref: ' . $request["subject"] ,
            'body' => $request[ 'message' ]
        ];
        
     $jdata=   json_encode($email_messege);
        

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://api.dev.ngsiwallet.net/pgw/api/v1/mailer/send/',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS =>$jdata,
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: Bearer '.$generated_token['response'][ 'data' ][ 'token' ]
          ),
        ));
        
        $response = curl_exec($curl);
        
        curl_close($curl);
        echo $response;

echo json_encode($request[ 'subject' ]);

    }

}

