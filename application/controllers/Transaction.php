<?php


defined('BASEPATH') or exit('No direct script access allowed');


class Transaction extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Api_Model', 'Api');
        $this->load->library('../services/ApiService');
        header("Content-Type: application/json");
        $this->load->model('Trans_Model', 'transaction');

        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding, X-Requested-With, Authorization, X-API-KEY");
    }

    private function generateReference($length = 6)
    {
        // Generate timestamp part: YYHHMMSS (e.g., 251007134512)
        $timestamp = date('yHis'); // year (2-digit), hour, minute, second

        // Generate random part
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $randomPart = substr(str_shuffle(str_repeat($characters, $length)), 0, $length);

        // Combine timestamp + random
        return strtoupper($timestamp . $randomPart);
    }



    private function validate_api_key()
    {
        $api_key = $this->input->get_request_header('X-API-KEY');

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

        $ref_id   = trim($raw_input['reference_number'] ?? $this->input->post('reference_number', TRUE));
        $name     = trim($raw_input['name'] ?? $this->input->post('name', TRUE));
        $amount   = trim($raw_input['amount'] ?? $this->input->post('amount', TRUE));
        $company  = trim($raw_input['company'] ?? $this->input->post('company', TRUE));
        $mobile   = trim($raw_input['mobile_number'] ?? $this->input->post('mobile_number', TRUE));
        $email    = trim($raw_input['email'] ?? $this->input->post('email', TRUE));
        $conv_fee = trim($raw_input['convience_fee'] ?? $this->input->post('convience_fee', TRUE));
        $services = $raw_input['services'] ?? [];

        // === 3. Validate Reference ID ===
        $existing = $this->transaction->check_refid_exists($ref_id);
        if ($existing) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'message' => 'Transaction already exists',
                    'response' => []
                ]));
        }

        // === 4. Required fields check ===
        if (empty($name) || empty($amount) || empty($company)) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'message' => 'Missing required fields',
                    'response' => []
                ]));
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

        // === 7. Save Services ===
        if (!empty($services)) {
            if (is_array($services[0])) {
                foreach ($services as $srv) {
                    $this->transaction->insert_service($ref_id, $srv);
                }
            } else {
                $this->transaction->insert_service($ref_id, $services);
            }
        }

        // === 8. Prepare Data for API ===
        $data = [
            "reference"      => 'REF-' . $this->generateReference(16),
            "refid"          => $ref_id,
            "name"           => $name,
            "amount"         => $amount,
            "mobile_number"  => $mobile,
            "email"          => $email,
            "convience_fee"  => $conv_fee,
            "company"        => $company,
            "return_url"     => base_url('/success'),
            "callback_url"   => base_url() . '/api-postback?ref_id=' . $ref_id
        ];

        // === 9. Call API Service ===
        $result = $this->apiservice->generate_qr_api($data);

        // 🔍 Debugging - log the raw API response
        log_message('error', 'API Service Response: ' . print_r($result, true));

        // Decode JSON if it’s a string
        if (is_string($result)) {
            $result = json_decode($result, true);
        }

        // Safely extract response data
        $apiData = $result['data'] ?? $result ?? [];

        // === 10. Save Transaction ===
        if (!empty($apiData)) {
            $transaction = [
                'trans_no'          => $apiData['reference_number'],
                'trans_payor'       => $data['name'],
                'trans_mobile'      => $mobile,
                'trans_email'       => $email,
                'trans_company'     => $data['company'],
                'trans_sub_total'   => $data['amount'],
                'trans_conv_fee'    => $data['convience_fee'],
                'trans_grand_total' => floatval($data['amount']) + floatval($data['convience_fee']),
                'trans_refid'       => $data['refid'] ?? '',
                'trans_txid'        => $apiData['txn_ref'] ?? '',
                'trans_ref'         => $data['reference'] ?? '',
                'trans_raw_string'  => $apiData['raw_string'], // store raw API response
                'trans_date_created' => date('Y-m-d H:i:s'),
                'trans_status'      => 'CREATED'
            ];

            $insert_id = $this->transaction->create_transaction($transaction);
        } else {
            return $this->output
                ->set_status_header(502)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'message' => 'Failed to generate transaction from API',
                    'raw_response' => $result,
                    'response' => []
                ]));
        }

        // === 11. Log API Request/Response ===
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

        // === 12. Final Response ===
        $endpoint = rtrim(api_url(), '/') . '/payment-form' . '?ref=' . $ref_id;

        return $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'success' => true,
                'status_code' => isset($result['status_code']) ? $result['status_code'] : null,
                'response' => [
                    'redirect_url' => $endpoint,
                    'raw_string' => $apiData['raw_string'],
                    'create_at'    => date('Y-m-d H:i:s'),
                    'reference_number'      => $data['refid'] ?? '',
                    'ref_id' => $data['reference']
                ]
            ]));
    }

    public function dotransac_checkref($ref_id = 0)
    {
        $ref_id = $this->input->get('ref_id', TRUE);

        if (empty($ref_id)) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'message' => 'Missing ref_id'
                ]));
        }

        // ✅ fetch transaction
        $transactions = $this->transaction->get_by_refid($ref_id);

        if (!$transactions) {
            return $this->output
                ->set_status_header(404)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'message' => 'Transaction not found'
                ]));
        }

        // ✅ fetch raw items
        $raw_items = $this->transaction->get_items_by_transno($ref_id);
        $raw_items = is_array($raw_items) ? $raw_items : [];

        // ✅ format items
        $items = array_map(function ($item) {
            return [
                'code'        => $item['part_code'] ?? '',
                'description' => $item['particulars'] ?? '',
                'qty'         => isset($item['part_qty']) ? (int)$item['part_qty'] : 0,
                'amount'      => isset($item['part_amount']) ? (float)$item['part_amount'] : 0,
                'other_fees'  => isset($item['part_other_fees']) ? (float)$item['part_other_fees'] : 0,
                'total'       => (
                    ((float)($item['part_amount'] ?? 0) * (int)($item['part_qty'] ?? 0))
                    + (float)($item['part_other_fees'] ?? 0)
                ),
            ];
        }, $raw_items);

        // ✅ compute totals
        $sub_total   = array_sum(array_column($items, 'total'));
        $conv_fee    = (float) $transactions->trans_conv_fee;
        $grand_total = $sub_total + $conv_fee;

        // ✅ build response
        $response = [
            'success'     => true,
            'ref_id'      => $transactions->trans_refid,
            'name'        => $transactions->trans_payor,
            'company'     => $transactions->trans_company,
            'mobile'      => substr($transactions->trans_mobile, 0, 4) . '****' . substr($transactions->trans_mobile, -2),
            'email'       => preg_replace('/(.).+(@.+)/', '$1****$2', $transactions->trans_email),
            'sub_total'   => $sub_total,
            'conv_fee'    => $conv_fee,
            'grand_total' => $grand_total,
            'deparment'   => 'BPLS',
            'status'      => $transactions->trans_status,
            'qr_url'      => $transactions->trans_raw_string,
            'items'       => $items
        ];


        return $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode($response));
    }

    public function dotransac_postback($ref_id = 0)
    {
        // ✅ Get ref_id from GET or param
        $ref_id = $this->input->get('ref_id', TRUE)
            ?? $this->input->get('refid', TRUE)
            ?? $ref_id;

        if (empty($ref_id)) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'message' => 'Missing ref_id'
                ]));
        }

        // ✅ Fetch transaction
        $transaction = $this->transaction->get_by_refid($ref_id);

        if (!$transaction) {
            return $this->output
                ->set_status_header(404)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'message' => 'Transaction not found'
                ]));
        }

        // ✅ Parse incoming JSON payload
        $raw_input = file_get_contents("php://input");
        $data = json_decode($raw_input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'message' => 'Invalid JSON payload'
                ]));
        }

        // ✅ Extract fields
        $statusValue   = $data['status'] ?? $transaction->trans_status;
        $statusLabel   = $this->status_get($statusValue); // SUCCESS → PAID
        $txId          = $data['TxId'] ?? $transaction->trans_txid;
        $referenceNum  = $data['TxRef'] ?? $transaction->trans_ref;

        // ✅ Encode full callback data
        $callbackJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // ✅ Prevent duplicate callbacks
        if ($this->transaction->callback_exists($txId, $ref_id)) {
            return $this->output
                ->set_status_header(200)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'message' => 'Duplicate callback - already processed'
                ], JSON_PRETTY_PRINT));
        }

        // ✅ Insert callback log
        $call_back_data = [
            'reference_number' => $ref_id,
            'callback_data'    => $callbackJson,
            'date'             => date('Y-m-d H:i:s'),
            'txid'             => $txId,
            'reference_num'    => $referenceNum,
            'callback_status'  => $statusLabel
        ];
        $this->transaction->insert_callback($call_back_data);

        // ✅ Update transaction status
        if ($statusLabel === 'PAID') {
            // If paid → set settled date
            $this->transaction->update_transaction($transaction->trans_id, [
                'trans_status'       => $statusLabel,
                'trans_settled_date' => date('Y-m-d H:i:s')
            ]);
        } else {
            $this->transaction->update_transaction($transaction->trans_id, [
                'trans_status' => $statusLabel
            ]);
        }


        return $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'success' => true,
                'status'  => $statusLabel,
                'settled_date' => ($statusLabel === 'PAID') ? date('Y-m-d H:i:s') : null
            ], JSON_PRETTY_PRINT));
    }




    public function status_get($type)
    {
        $map = [
            '1' => 'CREATED',
            '2' => 'PENDING',
            '3' => 'FAILED',
            '4' => 'PAID',
            'CREATED' => 'CREATED',
            'PENDING' => 'PENDING',
            'FAILED'  => 'FAILED',
            'PAID'    => 'PAID'
        ];

        return $map[(string)$type] ?? 'UNKNOWN';
    }


    public function dotransac_status()
    {
        $ref_id = $this->input->get('ref_id', TRUE);

        if (empty($ref_id)) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'status_code' => 400,
                    'message' => 'Missing ref_id'
                ], JSON_PRETTY_PRINT));
        }

        $result = $this->apiservice->check_status_api([
            'reference_number' => $ref_id
        ]);

        $transaction = $this->transaction->get_by_refid($ref_id);

        if (!$transaction) {
            return $this->output
                ->set_status_header(404)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'status_code' => 404,
                    'message' => 'Transaction not found'
                ], JSON_PRETTY_PRINT));
        }

        $status = $this->status_get($transaction->trans_status);
        if ($status === 'PAID') {
            $statusCode = 200;
        } elseif ($status === 'PENDING') {
            $statusCode = 202;
        } elseif (in_array($status, ['FAILED', 'CANCELLED', 'DECLINED'])) {
            $statusCode = 400;
        } else {
            $statusCode = 200;
        }

        // ✅ Choose best timestamp available
        $updatedAt = $transaction->updated_at
            ?? $transaction->trans_updated
            ?? $transaction->trans_date
            ?? null;

        // ✅ Build response
        $response = [
            'success'      => true,
            'status_code'  => $statusCode,
            'data'         => [
                'status'       => $status,
                'reference_number'      => $transaction->trans_refid,
                'ref_id'       => $transaction->trans_no,
                'fee'       => $transaction->trans_conv_fee,
                'amount'       => $transaction->trans_sub_total,
                'total_amount' =>$transaction->trans_grand_total,
                'settled_date' => ($status === 'PAID') ? $transaction->trans_settled_date : "",
                'payment-reference' => $result['response']['payment_channel'] ?? "",
                'transaction_id' => $result['response']['transaction_id'] ?? "",
            ]
        ];

        return $this->output
            ->set_status_header($statusCode)
            ->set_content_type('application/json')
            ->set_output(json_encode($response, JSON_PRETTY_PRINT));
    }
}