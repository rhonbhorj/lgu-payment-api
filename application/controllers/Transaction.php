<?php


defined('BASEPATH') or exit('No direct script access allowed');


class Transaction extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();

        $this->load->model('Api_Model', 'Api');
        $this->load->model('Trans_Model', 'transaction');
        $this->load->library('../services/ApiService');
        $this->load->helper('email');

        header("Content-Type: application/json");
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding, X-Requested-With, Authorization, X-API-KEY");

        date_default_timezone_set('Asia/Manila');
    }

    private function generateReference($length = 6)
    {
        $timestamp = date('yHis'); // year (2-digit), hour, minute, second
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $randomPart = substr(str_shuffle(str_repeat($characters, $length)), 0, $length);
        return strtoupper($timestamp . $randomPart);
    }


    private function validate_api_key()
    {
        // 1️⃣ Try to get API key from headers
        $api_key = $this->input->get_request_header('X-API-KEY', TRUE);

        if (empty($api_key) && !empty($_SERVER['HTTP_X_API_KEY'])) {
            $api_key = $_SERVER['HTTP_X_API_KEY'];
        }

        // 2️⃣ If not in headers, try from environment/config
        if (empty($api_key)) {
            $api_key = getenv('API_KEY');
            if (empty($api_key) && defined('API_KEY')) {
                $api_key = API_KEY;
            }
        }

        // 3️⃣ Still empty → reject request and stop
        if (empty($api_key)) {
            $this->output
                ->set_status_header(400)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode([
                    'status' => false,
                    'status_code' => 400,
                    'message' => 'X-API-KEY is required (missing in header and environment)',
                    'response' => [
                        'details' => []
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                ->_display();
            exit; // 🛑 STOP HERE
        }

        // 4️⃣ Validate the API key
        $validKey = $this->Api->validate_api_key($api_key);
        if (!$validKey) {
            $this->output
                ->set_status_header(403)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode([
                    'status' => false,
                    'status_code' => 403,
                    'message' => 'Invalid or inactive X-API-KEY',
                    'response' => [
                        'details' => []
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                ->_display();
            exit; // 🛑 STOP HERE TOO
        }

        return true; // ✅ Continue if valid
    }

    public function dotransac()
    {
        // Allow only POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->output
                ->set_status_header(405)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => false,
                    'status_code' => 405,
                    'message' => 'Method not allowed. Use POST instead.',
                    'response' => []
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        try {
            $this->validate_api_key();

            // Decode raw JSON
            $raw_input_str = $this->input->raw_input_stream;
            $raw_input = json_decode($raw_input_str, true);

            // Fail fast if JSON is missing or invalid
            if (empty($raw_input) || !is_array($raw_input)) {
                return $this->respond_error('Invalid JSON body. Must be a valid JSON object.', 400);
            }

            // Strict top-level validation
            $required_keys = ['reference_number', 'name', 'amount', 'mobile_number', 'email', 'company', 'services'];

            // Check missing keys
            foreach ($required_keys as $key) {
                if (!array_key_exists($key, $raw_input)) {
                    return $this->respond_error("Missing required field: {$key}", 400);
                }
            }

            // Check extra keys
            $extra_keys = array_diff(array_keys($raw_input), $required_keys);
            if (!empty($extra_keys)) {
                return $this->respond_error('JSON body is invalid', 400);
            }

            // Validate top-level types
            if (!is_string($raw_input['reference_number'])) return $this->respond_error('reference_number must be string', 400);
            if (!is_string($raw_input['name'])) return $this->respond_error('name must be string', 400);
            if (!is_float($raw_input['amount']) && !is_int($raw_input['amount'])) return $this->respond_error('amount must be number, not string', 400);
            if (!is_string($raw_input['mobile_number'])) return $this->respond_error('mobile_number must be string', 400);
            if (!is_string($raw_input['email'])) return $this->respond_error('email must be string', 400);
            if (!is_string($raw_input['company'])) return $this->respond_error('company must be string', 400);

            // Validate services
            if (!is_array($raw_input['services']) || empty($raw_input['services'])) {
                return $this->respond_error('services must be a non-empty array', 400);
            }

            foreach ($raw_input['services'] as $index => $srv) {
                $required_service_keys = ['item_code', 'item_qty', 'item_amount', 'item_other_fees'];

                // Missing keys in service
                foreach ($required_service_keys as $skey) {
                    if (!array_key_exists($skey, $srv)) {
                        return $this->respond_error("Service at index {$index} is missing field: {$skey}", 400);
                    }
                }

                // Extra keys in service
                $extra_srv_keys = array_diff(array_keys($srv), $required_service_keys);
                if (!empty($extra_srv_keys)) {
                    return $this->respond_error("Service at index {$index} has extra field(s): " . implode(', ', $extra_srv_keys), 400);
                }

                // Exact type validation
                if (!is_string($srv['item_code'])) return $this->respond_error("Service {$index} item_code must be string", 400);
                if (!is_int($srv['item_qty'])) return $this->respond_error("Service {$index} item_qty must be integer", 400);
                if (!is_float($srv['item_amount']) && !is_int($srv['item_amount'])) return $this->respond_error("Service {$index} item_amount must be number", 400);
                if (!is_float($srv['item_other_fees']) && !is_int($srv['item_other_fees'])) return $this->respond_error("Service {$index} item_other_fees must be number", 400);
            }

            // ✅ Extract validated fields
            $ref_id = trim($raw_input['reference_number']);
            $name   = trim($raw_input['name']);
            $amount = floatval($raw_input['amount']);
            $company = trim($raw_input['company']);
            $mobile = trim($raw_input['mobile_number']);
            $email  = trim($raw_input['email']);
            $services = $raw_input['services'];

            // ✅ Validate reference number length (max 30)
            if (strlen($ref_id) > 30) {
                return $this->respond_error('Reference number is too long. Maximum 50 characters allowed.', 400);
            }

            // ✅ Check duplicate transaction by refid
            if ($this->transaction->check_refid_exists($ref_id)) {
                return $this->respond_error('Transaction already exists', 400);
            }

            // ✅ Start DB transaction
            $this->db->trans_begin();

            // ✅ Format mobile and email defaults
            $mobile = '63' . substr($mobile, 1);
            if (empty($email)) $email = "devs@netglobalsolutions.net";

            // ✅ Calculate totals
            $subtotal = 0;
            $total_amount = 0;

            foreach ($services as $srv) {
                $line_subtotal = ($srv['item_qty'] * $srv['item_amount']) + $srv['item_other_fees'];
                $subtotal += $line_subtotal;
                $total_amount += $line_subtotal;

                $this->transaction->insert_service($ref_id, $srv);
            }

            // ✅ Compute convenience fee
            if ($amount >= 1 && $amount <= 5000) {
                $conv_fee = 25;
            } elseif ($amount >= 5001 && $amount <= 1000000) {
                $conv_fee = $amount * 0.005;
            } elseif ($amount > 1000000) {
                $conv_fee = $amount * 0.002;
            } else {
                $this->db->trans_rollback();
                return $this->respond_error('Invalid amount range for convenience fee computation.', 400);
            }

            // ✅ Generate unique reference number (min 30 chars, no duplicates)
            do {
                // REF- prefix (4 chars) → generate 26 chars to make 30 total
                $generatedRef = 'REF-' . $this->generateReference(26);

                // Pad if shorter, trim if longer
                if (strlen($generatedRef) < 30) {
                    $generatedRef = str_pad($generatedRef, 30, strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 1)));
                } elseif (strlen($generatedRef) > 30) {
                    $generatedRef = substr($generatedRef, 0, 30);
                }

                // Check duplicate in DB
                $exists = $this->transaction->check_reference_exists($generatedRef);
            } while ($exists);

            // ✅ Build API payload
            $data = [
                "reference" => $generatedRef,
                "refid"     => $ref_id,
                "name"      => $name,
                "amount"    => number_format($subtotal + $conv_fee, 2, '.', ''),
                "mobile_number" => $mobile,
                "email"     => $email,
                "convience_fee" => $conv_fee,
                "company"   => $company,
                "return_url" => base_url('/success'),
                "callback_url" => base_url('/api-postback?ref_id=' . $ref_id)
            ];

            $result = $this->apiservice->generate_qr_api($data);
            if (is_string($result)) $result = json_decode($result, true);
            $apiData = $result['data'] ?? [];
            $raw_msg = $apiData['raw_string'] ?? json_encode($apiData);

            if (empty($apiData)) {
                $this->db->trans_rollback();
                return $this->respond_error('Failed to generate transaction from API', 502, [
                    'raw_response' => $result['raw_response'] ?? $result
                ]);
            }

            // ✅ Insert transaction record
            $transaction = [
                'trans_no' => $apiData['reference_number'] ?? $data['reference'],
                'trans_payor' => $name,
                'trans_mobile' => $mobile,
                'trans_email' => $email,
                'trans_company' => $company,
                'trans_sub_total' => $subtotal,
                'trans_conv_fee' => $conv_fee,
                'trans_grand_total' => $subtotal + $conv_fee,
                'trans_refid' => $data['refid'],
                'trans_txid' => $apiData['txn_ref'] ?? '',
                'trans_ref' => $data['reference'],
                'trans_raw_string' => $raw_msg,
                'trans_date_created' => date('Y-m-d H:i:s'),
                'trans_status' => 'CREATED'
            ];
            $this->transaction->create_transaction($transaction);
            $this->db->trans_commit();

            // ✅ Success response
            $endpoint = rtrim(api_url(), '/') . '/payment-form?ref=' . $ref_id;
            return $this->output
                ->set_status_header(200)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode([
                    'success' => true,
                    'status_code' => 200,
                    'response' => [
                        'redirect_url' => $endpoint,
                        'raw_string' => $raw_msg,
                        'created_at' => date('Y-m-d H:i:s'),
                        'reference_number' => $data['refid'],
                        'ref_id' => $data['reference'],
                        'subtotal' => number_format($subtotal, 2, '.', ''),
                        'conv_fee' => number_format($conv_fee, 2, '.', ''),
                        'grand_total' => number_format($subtotal + $conv_fee, 2, '.', '')
                    ]
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return $this->respond_error('Unexpected server error', 500, [
                'error' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ]);
        }
    }

    public function dotransac_checkref($ref_id = 0)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->output
                ->set_status_header(405)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => false,
                    'status_code' => 405,
                    'message' => 'Method not allowed. Use GET instead.',
                    'response' => []
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        try {
            $this->validate_api_key();

            $ref_id = $this->input->get('ref_id', TRUE);

            if (empty($ref_id)) {
                return $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json', 'utf-8')
                    ->set_output(json_encode([
                        'success' => false,
                        'status_code' => 400,
                        'message' => 'Missing ref_id',
                        'response' => [
                            'details' => []
                        ],
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            $transactions = $this->transaction->get_by_refid($ref_id);

            if (!$transactions) {
                return $this->output
                    ->set_status_header(404)
                    ->set_content_type('application/json', 'utf-8')
                    ->set_output(json_encode([
                        'status' => false,
                        'status_code' => 404,
                        'message' => 'Transaction not found',
                        'response' => [
                            'details' => []
                        ],
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            $raw_items = $this->transaction->get_items_by_transno($ref_id);
            $raw_items = is_array($raw_items) ? $raw_items : [];

            $items = array_map(function ($item) {
                return [
                    'code' => $item['part_code'] ?? '',
                    'description' => $item['particulars'] ?? '',
                    'qty' => isset($item['part_qty']) ? (int)$item['part_qty'] : 0,
                    'amount' => isset($item['part_amount']) ? (float)$item['part_amount'] : 0,
                    'other_fees' => isset($item['part_other_fees']) ? (float)$item['part_other_fees'] : 0,
                    'total' => ((float)($item['part_amount'] ?? 0) * (int)($item['part_qty'] ?? 0)) + (float)($item['part_other_fees'] ?? 0),
                ];
            }, $raw_items);

            $sub_total = array_sum(array_column($items, 'total'));
            $conv_fee = (float) $transactions->trans_conv_fee;
            $grand_total = $sub_total + $conv_fee;

            $response = [
                'status'      => true,
                'status_code'  => 200,
                'response'         => [
                    'ref_id'      => $transactions->trans_refid,
                    'name'        => $transactions->trans_payor,
                    'company'     => $transactions->trans_company,
                    'mobile'      => substr($transactions->trans_mobile, 0, 4) . '****' . substr($transactions->trans_mobile, -2),
                    'email'       => preg_replace('/(.).+(@.+)/', '$1****$2', $transactions->trans_email),
                    'sub_total'   => $sub_total,
                    'conv_fee'    => $conv_fee,
                    'grand_total' => $grand_total,
                    'department'  => 'BPLS',
                    'status'      => $transactions->trans_status,
                    'qr_url'      => $transactions->trans_raw_string,
                    'items'       => $items,
                ],
            ];


            return $this->output
                ->set_status_header(200)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (Throwable $e) {
            return $this->output
                ->set_status_header(500)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode([
                    'status' => false,
                    'status_code' => 500,
                    'message' => 'Unexpected server error',
                    'response' => [
                        'details' => [
                            'exception' => $e->getMessage(),
                            'file' => basename($e->getFile()),
                            'line' => $e->getLine()
                        ]
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    public function dotransac_postback($ref_id = 0)
    {



        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->output
                ->set_status_header(405)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => false,
                    'status_code' => 405,
                    'message' => 'Method not allowed. Use POST instead.',
                    'response' => []
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        try {
            $ref_id = $this->input->get('ref_id', TRUE)
                ?? $this->input->get('refid', TRUE)
                ?? $ref_id;

            if (empty($ref_id)) {
                return $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json', 'utf-8')
                    ->set_output(json_encode([
                        'status' => false,
                        'status_code' => 400,
                        'message' => 'Missing ref_id',
                        'response' => [
                            'details' => []
                        ],
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            $transaction = $this->transaction->get_by_refid($ref_id);

            if (!$transaction) {
                return $this->output
                    ->set_status_header(404)
                    ->set_content_type('application/json', 'utf-8')
                    ->set_output(json_encode([
                        'status' => false,
                        'status_code' => 404,
                        'message' => 'Transaction not found',
                        'response' => [
                            'details' => []
                        ],
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            $raw_input = file_get_contents("php://input");
            $data = json_decode($raw_input, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json', 'utf-8')
                    ->set_output(json_encode([
                        'status' => false,
                        'status_code' => 400,
                        'message' => 'Invalid JSON payload',
                        'response' => [
                            'details' => []
                        ],
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            $statusValue  = $data['status'] ?? $transaction->trans_status;
            $statusLabel  = $this->status_get($statusValue);
            $txId         = $data['TxId'] ?? $transaction->trans_txid;
            $referenceNum = $data['TxRef'] ?? $transaction->trans_ref;

            $callbackJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($this->transaction->callback_exists($txId, $ref_id)) {
                return $this->output
                    ->set_status_header(200)
                    ->set_content_type('application/json', 'utf-8')
                    ->set_output(json_encode([
                        'status' => false,
                        'status_code' => 200,
                        'message' => 'Duplicate callback - already processed',
                        'response' => [
                            'details' => []
                        ],
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            $call_back_data = [
                'reference_number' => $ref_id,
                'callback_data'    => $callbackJson,
                'date'             => date('Y-m-d H:i:s'),
                'txid'             => $txId,
                'reference_num'    => $referenceNum,
                'callback_status'  => $statusLabel
            ];
            $this->transaction->insert_callback($call_back_data);

            if ($statusLabel === 'PAID') {
                $this->transaction->update_transaction($transaction->trans_id, [
                    'trans_status'       => $statusLabel,
                    'trans_settled_date' => date('Y-m-d H:i:s')
                ]);
                $settledDate = date('Y-m-d H:i:s');
            } else {
                $this->transaction->update_transaction($transaction->trans_id, [
                    'trans_status' => $statusLabel
                ]);
                $settledDate = null;
            }

            $emailParams = [
                'reference_number' => $ref_id,
                'trans_no' => $transaction->trans_no,
                'subject' => 'Payment Confirmation - REF:' . $ref_id,
                'email' => $transaction->trans_email ? $transaction->trans_email : "devs@netglobalsolutions.net",
                'date' => $transaction->trans_date_created,
                'company' => $transaction->trans_company,
                'payor_name' => $transaction->trans_payor,
                'mobile_no' => $transaction->trans_mobile,
                'sub_total' => $transaction->trans_sub_total,
                'convenience_fee' => $transaction->trans_conv_fee,
                'grand_total' => $transaction->trans_grand_total,
            ];

            sendemail($emailParams);
            return $this->output
                ->set_status_header(200)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode([
                    'status' => true,
                    'status_code' => 200,
                    'response' => [
                        'status' => $statusLabel,
                        'settled_date' => $settledDate,
                        'email_body' => $emailParams,
                        'email_status' => "Sent"
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (Throwable $e) {
            return $this->output
                ->set_status_header(500)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode([
                    'status' => false,
                    'status_code' => 500,
                    'message' => 'Unexpected server error',
                    'response' => [
                        'details' => [
                            'exception' => $e->getMessage(),
                            'file' => basename($e->getFile()),
                            'line' => $e->getLine()
                        ]
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    public function dotransac_status()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->output
                ->set_status_header(405)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => false,
                    'status_code' => 405,
                    'message' => 'Method not allowed. Use POST instead.',
                    'response' => []
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        try {
            $this->validate_api_key();

            // ✅ Read raw JSON
            $raw_input = trim(file_get_contents("php://input"));
            $data = json_decode($raw_input, true);

            // Check JSON validity
            if (!is_array($data)) {
                return $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json', 'utf-8')
                    ->set_output(json_encode([
                        'status' => false,
                        'status_code' => 400,
                        'message' => 'JSON body is invalid',
                        'response' => [],
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            // ✅ Strict required keys
            $required_keys = ['reference_number'];
            $errors = [];

            // Check missing keys
            foreach ($required_keys as $key) {
                if (!array_key_exists($key, $data)) {
                    $errors[] = "Missing required field: {$key}";
                }
            }

            // Check extra keys
            $extra_keys = array_diff(array_keys($data), $required_keys);
            foreach ($extra_keys as $ekey) {
                $errors[] = "Extra field detected: {$ekey}";
            }

            // Return 400 if any errors
            if (!empty($errors)) {
                return $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json', 'utf-8')
                    ->set_output(json_encode([
                        'status' => false,
                        'status_code' => 400,
                        'message' => 'JSON body is invalid',
                        'response' => [],
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            // ✅ Extract reference_number
            $ref_id = $data['reference_number'];

            // ✅ Fetch transaction
            $transaction = $this->transaction->get_by_refid($ref_id);
            if (!$transaction) {
                return $this->output
                    ->set_status_header(404)
                    ->set_content_type('application/json', 'utf-8')
                    ->set_output(json_encode([
                        'status' => false,
                        'status_code' => 404,
                        'message' => 'Transaction not found',
                        'response' => [],
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            // ✅ Only call API if transaction exists
            $result = $this->apiservice->check_status_api([
                'reference_number' => $transaction->trans_no
            ]);

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

            $response = [
                'status' => true,
                'status_code' => $statusCode,
                'response' => [
                    'status' => $status,
                    'reference_number' => $transaction->trans_refid,
                    'ref_id' => $transaction->trans_no,
                    'fee' => $transaction->trans_conv_fee,
                    'amount' => $transaction->trans_sub_total,
                    'total_amount' => $transaction->trans_grand_total,
                    'txn_date' => $transaction->trans_date_created ?? "",
                    'settled_date' => ($status === 'PAID') ? $transaction->trans_settled_date : "",
                    'payment_channel' => $result['response']['data']['payment-channel'] ?? "",
                    'payment_reference' => $result['response']['data']['payment-reference'] ?? "",
                    'transaction_id' => $result['response']['data']['transaction_id'] ?? "",
                ],
            ];

            return $this->output
                ->set_status_header($statusCode)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (Throwable $e) {
            return $this->output
                ->set_status_header(500)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode([
                    'status' => false,
                    'status_code' => 500,
                    'message' => 'Unexpected server error',
                    'response' => [
                        'errors' => [
                            'exception' => $e->getMessage(),
                            'file' => basename($e->getFile()),
                            'line' => $e->getLine()
                        ]
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    public function doget_transactions()
    {

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->output
                ->set_status_header(405)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => false,
                    'status_code' => 405,
                    'message' => 'Method not allowed. Use GET instead.',
                    'response' => []
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        try {
            $this->validate_api_key();

            $input_json = json_decode(file_get_contents('php://input'), true);

            $datetime_from = $this->input->get('datetime_from', TRUE) ?? ($input_json['datetime_from'] ?? null);
            $datetime_to   = $this->input->get('datetime_to', TRUE) ?? ($input_json['datetime_to'] ?? null);

            if (!$datetime_from) {
                return $this->output
                    ->set_status_header(400)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'status_code' => 400,
                        'message' => 'datetime_from is required'
                    ], JSON_PRETTY_PRINT));
            }

            $records = $this->transaction->get_transactions_with_particulars($datetime_from, $datetime_to);

            // Group particulars under each transaction
            $transactions = [];
            foreach ($records as $row) {
                $refid = $row['trans_refid'];

                if (!isset($transactions[$refid])) {
                    $transactions[$refid] = [
                        'reference_number'  => $row['trans_refid'],
                        'name'              => $row['trans_payor'],
                        'company'           => $row['trans_company'],
                        'particulars'       => [],
                        'department'        => "BPLS",
                        'sub_amount'        => $row['trans_sub_total'],
                        'fee'               => (float)$row['trans_conv_fee'],
                        'total_amount'      => (float)$row['trans_grand_total'],
                        'status'            => $row['trans_status'],
                        'txid'              => $row['trans_txid'],
                        'txn_date'          => $row['trans_date_created'],
                        'settled_date'      => $row['trans_settled_date'],
                        'or_number'         => "",
                        'or_released'        => "",
                        'type_of_payment'   => "",
                        'cash_settled_date' => "",
                        'released_by'       => "NGSI TEST",
                    ];
                }

                // Add particular details
                if (!empty($row['part_id'])) {
                    $transactions[$refid]['particulars'][] = [
                        'part_id'        => $row['part_id'],
                        'part_code'      => $row['part_code'],
                        'particular'     => $row['part_particulars'],
                        'qty'            => (int)$row['part_qty'],
                        'amount'         => (float)$row['part_amount'],
                        'other_fees'     => (float)$row['part_other_fees']
                    ];
                }
            }

            $response = [
                'success'     => true,
                'status_code' => 200,
                'date_from'   => $datetime_from,
                'date_to'     => $datetime_to,
                'data'        => array_values($transactions),
                'timestamp'   => date('Y-m-d H:i:s')
            ];

            return $this->output
                ->set_status_header(200)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (Throwable $e) {
            return $this->output
                ->set_status_header(500)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode([
                    'success'     => false,
                    'status_code' => 500,
                    'message' => 'Unexpected server error',
                    'error'       => [
                        'details' => [
                            'exception' => $e->getMessage(),
                            'file'      => basename($e->getFile()),
                            'line'      => $e->getLine()
                        ]
                    ],
                    'timestamp' => date('Y-m-d H:i:s')
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
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

    private function respond_error($message, $status = 400, $extra = [])
    {
        $response = [
            'status' => false,
            'status_code' => $status,
            'message' => $message,
            'response' => [
                'details' => $extra,
            ],
        ];

        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
