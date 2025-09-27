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


    private function validate_api_key()
    {
        $api_key = $this->input->get_request_header('X-API-KEY');

        // 🔴 Missing API Key → 401 Unauthorized
        if (empty($api_key)) {
            $response = [
                'success' => false,
                'message' => 'Missing API Key',
                'response' => []
            ];

            $this->output
                ->set_status_header(401) // Unauthorized
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))
                ->_display();  // ✅ Force send response immediately

            exit;
        }

        $validKey = $this->Api->validate_api_key($api_key);

        // 🔴 Invalid or inactive key → 400 Bad Request
        if (!$validKey) {
            $response = [
                'success' => false,
                'message' => 'Invalid or Inactive API Key',
                'response' => []
            ];

            $this->output
                ->set_status_header(400) // Bad Request
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))
                ->_display();  // ✅ Force send response immediately

            exit;
        }

        return true; // ✅ Valid
    }



    public function dogetcategories()
    {
        // ✅ just call the reusable function
        $this->validate_api_key();

        $categories = $this->transaction->get_all_categories();

        $filtered = array_map(function ($row) {
            return [
                'cat_code'     => $row['cat_code'],
                'cat_category' => $row['cat_category'],
            ];
        }, $categories);

        echo json_encode(['success' => true, 'data' => $filtered]);
    }


    public function dotransac()
    {
        // === 1. Validate API Key ===
        $this->validate_api_key();

        // === 2. Parse Input (JSON or form-data) ===
        $raw_input = json_decode($this->input->raw_input_stream, true);

        $refid   = trim($raw_input['reference_number'] ?? $this->input->post('reference_number', TRUE));
        $name    = trim($raw_input['name'] ?? $this->input->post('name', TRUE));
        $amount  = trim($raw_input['amount'] ?? $this->input->post('amount', TRUE));
        $company = trim($raw_input['company'] ?? $this->input->post('company', TRUE));
        $mobile  = trim($raw_input['mobile_number'] ?? $this->input->post('mobile_number', TRUE));
        $email   = trim($raw_input['email'] ?? $this->input->post('email', TRUE));
        $conv_fee = trim($raw_input['convience_fee'] ?? $this->input->post('convience_fee', TRUE));
        $raw_input = json_decode($this->input->raw_input_stream, true); // supports JSON requests
        $services  = $raw_input['services'] ?? [];
        // === 3. Validate Reference ID ===
        $existing = $this->transaction->check_refid_exists($refid);
        if ($existing) {
            $this->output
                ->set_status_header(400) // Bad Request
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'message' => 'Transaction already exists',
                    'response' => []
                ]))
                ->_display();
            exit;
        }

        // === 4. Required fields check ===
        if (empty($name) || empty($amount) || empty($company)) {
            $this->output
                ->set_status_header(400) // Bad Request
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'message' => 'Missing required fields',
                    'response' => []
                ]))
                ->_display();
            exit;
        }

        // === 5. Normalize Mobile ===
        if (!empty($mobile)) {
            if (preg_match('/^09\d{9}$/', $mobile)) {
                $mobile = '63' . substr($mobile, 1);
            }
        } else {
            $mobile = "639000000000"; // fallback
        }

        // === 6. Validate Email ===
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = "devs@netglobalsolutions.net"; // fallback
        }

        // === 7. Prepare Data for API ===

        $raw_input = json_decode($this->input->raw_input_stream, true);
        $services  = $raw_input['services'] ?? [];

        if (!empty($services)) {
            // Check if first element is an array → multiple services
            if (is_array($services[0])) {
                foreach ($services as $srv) {
                    $this->transaction->insert_service($refid, $srv);
                }
            } else {
                // Single service
                $this->transaction->insert_service($refid, $services);
            }
        }

        $data = [
            "reference"      => $this->generateReference(16),
            "refid"          => $refid,
            "name"           => $name,
            "amount"         => $amount,
            "mobile_number"  => $mobile,
            "email"          => $email,
            "convience_fee"  => $conv_fee,
            "company"        => $company,
            "return_url"     => base_url('/success'),
            "callback_url"   => base_url('/success/postback')
        ];

        // === 8. Call API Service ===
        $result = $this->apiservice->generate_qr_api($data);
        $apiData = $result['data'] ?? [];

        // === 9. Save Transaction (only if API returned valid data) ===
        if (!empty($apiData)) {
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

            $insert_id = $this->transaction->create_transaction($transaction);
        } else {
            $this->output
                ->set_status_header(502) // Bad Gateway
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'message' => 'Failed to generate transaction from API',
                    'response' => []
                ]))
                ->_display();
            exit;
        }

        // === 10. Log API Request/Response ===
        $request_method = $this->input->method(TRUE);
        $request_params = !empty($raw_input) ? $raw_input : ($request_method === 'POST'
            ? $this->input->post(NULL, TRUE)
            : $this->input->get(NULL, TRUE));

        $log_data = [
            'api_method'      => $request_method,
            'api_params'      => json_encode($request_params),
            'api_response'    => json_encode($result),
            'api_status'      => isset($result['status_code']) ? $result['status_code'] : null,
            'api_request_at'  => date('Y-m-d H:i:s'),
            'api_response_at' => date('Y-m-d H:i:s'),
            'api_authorized'  => ""
        ];
        $this->Api->insert_log($log_data);

        // === 11. Final Response ===
        $endpoint = rtrim(api_url(), '/') . '/qr-print' . '?ref=' . $refid;

        $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'success' => true,
                'status_code' => isset($result['status_code']) ? $result['status_code'] : null,
                'response' => [
                    'redirect_url' => $endpoint,
                    'create_at'    => date('Y-m-d H:i:s'),
                    'txn_ref'      => $apiData['txn_ref'] ?? ''
                ]
            ]))
            ->_display();
        exit;
    }


    public function doTransac_postback($ref_id = 0)
    {
        $ref_id = $this->input->get('ref_id', TRUE);
        $this->output->set_content_type('application/json');

        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || empty($ref_id)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid request',
            ]);
            return;
        }

        // Prepare callback data
        $callback_data = [
            'reference_number' => $ref_id,
            'callback_data' => json_encode($data),
            'date' => date('Y-m-d H:i:s'),
            'txid' => $data['TxId'] ?? null,
            'reference_num' => $data['referenceNumber'] ?? null,
            'callback_status' => $data['status'] ?? null,
        ];

        $status_response = $this->status_get($data['status'] ?? 0);

        // Check if callback already exists
        if ($this->transaction->callback_data_detail($ref_id)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Callback data already exists',
            ]);
            return;
        }

        // Get transaction details
        $transaction = $this->transaction->findByRefId($ref_id);
        if (!$transaction) {
            echo json_encode([
                'status' => 'error',
                'message' => 'No transaction found for this reference',
            ]);
            return;
        }

        $type = 'CASHLESS';
        $currentDateTime = date('YmdHis');
        if ($transaction->Status == 'CREATED') {
            $this->transaction->doInsertCallback($callback_data);

            switch ($data['status']) {
                case 4: // SUCCESS
                    // Update transaction status
                    if ($this->transaction->update_status_by_ref($ref_id, $type, $status_response, $currentDateTime, $callback_data['txid'])) {

                        // Insert services if provided
                        if (!empty($data['services']) && is_array($data['services'])) {
                            foreach ($data['services'] as $srv) {
                                $this->transaction->insert_service($ref_id, $srv);
                            }
                        }

                        // Recalculate other fees
                        $other_fees = $this->transaction->getTotalOtherFee($ref_id);

                        // Fetch updated transaction details
                        $transactionDetail = $this->transaction->getTransactionDetails($ref_id);

                        echo json_encode([
                            'status' => 'success',
                            'message' => 'Payment successful and services recorded.',
                            'other_fees' => $other_fees,
                            'transaction' => $transactionDetail
                        ]);
                        return;
                    }
                    break;

                case 1:
                case 2:
                case 3: // started, pending, failed
                    $status_labels = ['STARTED', 'PENDING', 'FAILED'];
                    $index = $data['status'] - 1;
                    if ($this->transaction->update_status_by_ref($ref_id, $type, $status_response, $currentDateTime, $callback_data['txid'])) {
                        echo json_encode([
                            'status' => $status_labels[$index],
                            'message' => 'Status updated',
                        ]);
                        return;
                    }
                    break;

                default:
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Unknown status',
                    ]);
                    return;
            }

            echo json_encode([
                'status' => 'error',
                'message' => 'Internal error updating status',
            ]);
            return;
        } else {
            // Transaction already processed, just update status
            if ($this->transaction->update_status_by_ref($ref_id, $type, $status_response, $currentDateTime, $callback_data['txid'])) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Transaction already processed. Status updated.',
                ]);
                return;
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Internal error updating status',
                ]);
                return;
            }
        }
    }

    private function status_get($status)
    {
        $statuses = [
            1 => 'STARTED',
            2 => 'PENDING',
            3 => 'FAILED',
            4 => 'SUCCESS'
        ];

        return $statuses[$status] ?? 'unknown';
    }
}
