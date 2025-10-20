<?php if (!defined('BASEPATH'))
  exit('No direct script access allowed');


function generate_token()
{
  $curl = curl_init();

  curl_setopt_array($curl, array(
    CURLOPT_URL => $_ENV['EMAIL_ENDPOINT_BASE_URL'] . '/generate-token',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => '{
         "username": "' . $_ENV['EMAIL_API_USER_NAME'] . '",
         "secret": "' . $_ENV['EMAIL_API_SECRET'] . '",
         "app_uuid": "' . $_ENV['EMAIL_API_UUID'] . '"
       }',
    CURLOPT_HTTPHEADER => array(
      'Content-Type: application/json'

    ),
  ));




  $response = curl_exec($curl);
  $http_status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  curl_close($curl);

  $jdata = json_decode($response, true);

  $resp['response'] = $jdata;
  $resp['status_code'] = $http_status_code;

  return $resp;
}


if (!function_exists('sendemail')) {

  function sendemail($request)
  {
    $generated_token = generate_token();

    $email_body = '<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f7f7f7; color: #333;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" width="100%" style="max-width: 450px; margin: 0 auto; padding: 20px; background-color: rgba(0, 37, 79, 0.9); border: 1px solid #e0e0e0; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
        <tr>
            <td style="vertical-align: middle; text-align: center;">
                <img src="https://sjlgu-uat.netglobalsolutions.net/assets/images/ngsi_updated_logo.png" alt="Company Logo" height="55px" style="display: inline-block; vertical-align: middle;" />
            </td>
        </tr>
    </table>
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" width="100%" style="max-width: 450px; margin: 0 auto; padding: 20px; background-color: #ffffff; border: 1px solid #e0e0e0; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
        <tr>
            <td>
                <div style="padding: 20px;">
                    <h3 style="font-size: 16px; margin: 5px 0; font-weight: bold; color: #333;">Dear Valued Customer,</h3>
                    <p style="font-size: 14px; text-align: justify; line-height: 1.3; color: #555;">
                        Thank you for your recent payment! Your transaction has been successfully completed. Below are the details:
                    </p>

                   <table style="border-collapse: collapse; width: 100%; box-shadow: none; border: 1px solid #ccc;">
                                 <tr style="border-bottom: 1px solid #ccc;">
                                 <th colspan="2" style="  padding: 10px; text-align: center;">Payment Details</th></tr>
                                 <tr style="border-bottom: 1px solid #ccc; background-color: #ffffff;">
                                 <td style="padding: 8px; font-weight: bold;">Merchant Name</td><td style="padding: 8px;">MINDORO LGU</td></tr>
                                 <tr style="border-bottom: 1px solid #ccc; background-color: #ffffff;">
                                 <td style="padding: 8px; font-weight: bold;">Reference Number</td><td style="padding: 8px;">' . $request['reference_number'] . '</td></tr>
                                 <tr style="border-bottom: 1px solid #ccc; background-color: #ffffff;">
                                 <td style="padding: 8px; font-weight: bold;">Transaction Date & Time</td><td style="padding: 8px;">' . date("F j, Y, g:i a", strtotime($request['date'])) . '</td></tr>
                                 <tr style="border-bottom: 1px solid #ccc; background-color: #ffffff;">
                                 <td style="padding: 8px; font-weight: bold;">Name Of Payor</td><td style="padding: 8px;">' . $request['payor_name'] . '</td></tr>
                                 <tr style="border-bottom: 1px solid #ccc; background-color: #ffffff;">
                                 <td style="padding: 8px; font-weight: bold;">Company</td><td style="padding: 8px;">' . $request['company'] . '</td></tr>
                                 <tr style="border-bottom: 1px solid #ccc; background-color: #ffffff;">
                                 <td style="padding: 8px; font-weight: bold;">Amount</td><td style="padding: 8px;">PHP '. number_format($request['sub_total'], 2) . '</td></tr>
                                 <tr style="border-bottom: 1px solid #ccc; background-color: #f9f9f9;">
                                 <td style="padding: 8px; font-weight: bold;">Convenience Fee</td><td style="padding: 8px;">PHP '  . number_format($request['convenience_fee'], 2) . '</td></tr>
                              <tr style="background: rgba(0, 37, 79, 0.9); color: #ffffff;"><td style="padding: 8px; font-weight: bold;">Total</td><td style="padding: 8px; font-weight: bold;">PHP ' . number_format($request['grand_total'], 2) . '</td></tr>
                       </div>
            <p style="font-size: 14px; line-height: 1.3; text-align: justify;">Your prompt payment is greatly appreciated and ensures the uninterrupted provision of our services. If you have any questions or concerns regarding your payment or account, please do not hesitate to contact our customer service team at:</p>

            <div style="font-size: 14px; color: #555;">
              <span><b>&#128241;
                 Mobile / Viber:</b></span><br /><br/>
 <a href="tel:+639171486979" style="color: #007bff; text-decoration: none; font-weight: bold; ">09171486979</a><br>
              <a href="tel:+639171793481" style="color: #007bff; text-decoration: none; font-weight: bold; ">09171793481</a><br>
              <a href="tel:+639173126960" style="color: #007bff; text-decoration: none; font-weight: bold; ">09173126960</a>
            </div>

            <hr style="border: none; border-top: 2px solid #ccc; margin: 5px 0;" />

            <p style="font-size: 16px; line-height: 1.3;"><b>For further information and assistance, please contact:</b></p>
            <div style="font-size: 14px; color: #555;">
              <span>NetGlobal Solutions Inc.</span><br />
              <span>Email: <a href="mailto:support@netglobalsolutions.net" style="color: #007bff; text-decoration: none; font-weight: bold;">support@netglobalsolutions.net</a></span>
            </div>

            <hr style="border: none; border-top: 2px solid #ccc; margin: 20px 0;" />

            <div style="font-size: 12px; text-align: center; margin-top: 20px;">
              <span>Please <b>DO NOT REPLY TO THIS EMAIL</b>. This mailbox is not monitored, and you will not receive a response. For assistance, please use the contact details above.</span>
            </div>

            <!-- Social Media Links -->
            <div style="text-align: center; margin-top: 20px;">
              <a href="https://www.facebook.com/netglobalsolutionsinc" target="_blank" style="text-decoration: none;">
                <img src="https://sjlgu-uat.netglobalsolutions.net/assets/images/facebook.png" alt="Facebook" height="30px"/>
              </a>
              <a href="https://www.linkedin.com/company/netglobalsolutions-inc/" target="_blank" style="text-decoration: none;">
                <img src="https://sjlgu-uat.netglobalsolutions.net/assets/images/linkedin.png" alt="LinkedIn" height="30px" />
              </a>
              
            </div>

          </div>
        </td>
      </tr>
    </table>
  </body>';

    $email_message = [
      'app_uuid' => $_ENV['EMAIL_API_UUID'],
      'reference_number' => $request['reference_number'],
      'receivers' => $request['email'],
      'subject' => $request['subject'],
      'body' => $email_body // Ensure this is a proper string
    ];

    $jdata = json_encode($email_message);
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => $_ENV['EMAIL_ENDPOINT_BASE_URL'] . '/api/mailer',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => $jdata,
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $generated_token['response']['data']['token']
      ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    return $response;
  }
}
