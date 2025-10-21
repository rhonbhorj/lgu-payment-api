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
                    'response' => [
                        'message' => 'X-API-KEY is required (missing in header and environment)',
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
                    'response' => [
                        'message' => 'Invalid or inactive X-API-KEY',
                        'details' => []
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                ->_display();
            exit; // 🛑 STOP HERE TOO
        }

        return true; // ✅ Continue if valid
    }


    public function dogetcategories()
    {

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->output
                ->set_status_header(405) // Method Not Allowed
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => false,
                    'status_code' => 405,
                    'message' => 'Method not allowed. Use GET instead.'
                ]));
        }

        try {
            $this->validate_api_key();
            $categories = $this->transaction->get_all_categories();
            $filtered = array_map(function ($row) {
                return [
                    'cat_code'     => $row['cat_code'],
                    'cat_category' => $row['cat_category'],
                ];
            }, $categories);

            return $this->output
                ->set_status_header(200)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode([
                    'status' => true,
                    'status_code' => 200,
                    'response' => $filtered,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (Throwable $e) {
            return $this->output
                ->set_status_header(500)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode([
                    'status' => false,
                    'status_code' => 500,
                    'response' => [
                        'message' => 'Unexpected server error',
                        'details' => [
                            'exception' => $e->getMessage(),
                            'file' => basename($e->getFile()),
                            'line' => $e->getLine()
                        ]
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    public function dotransac()
    {
        // ✅ Allow only POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->output
                ->set_status_header(405)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => false,
                    'status_code' => 405,
                    'response' => [
                        'message' => 'Method not allowed. Use POST instead.',
                    ]
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        try {
            $this->validate_api_key();

            $raw_input = json_decode($this->input->raw_input_stream, true);

            $ref_id   = trim($raw_input['reference_number'] ?? $this->input->post('reference_number', TRUE) ?? '');
            $name     = trim($raw_input['name'] ?? $this->input->post('name', TRUE) ?? '');
            $amount   = trim($raw_input['amount'] ?? $this->input->post('amount', TRUE) ?? '');
            $company  = trim($raw_input['company'] ?? $this->input->post('company', TRUE) ?? '');
            $mobile   = trim($raw_input['mobile_number'] ?? $this->input->post('mobile_number', TRUE) ?? '');
            $email    = trim($raw_input['email'] ?? $this->input->post('email', TRUE) ?? '');
            $services = $raw_input['services'] ?? [];

            // ✅ Basic validation
            if (empty($ref_id)) return $this->respond_error('Reference number is required', 400);
            if ($this->transaction->check_refid_exists($ref_id)) return $this->respond_error('Transaction already exists', 400);
            if (empty($name) || empty($company)) return $this->respond_error('Missing required fields', 400);
            if (!empty($mobile) && !preg_match('/^09\d{9}$/', $mobile)) return $this->respond_error('Mobile number must be 11 digits starting with 09', 400);

            // ✅ Start DB transaction
            $this->db->trans_begin();

            // ✅ Format defaults
            $mobile = (!empty($mobile)) ? '63' . substr($mobile, 1) : "639000000000";
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $email = "devs@netglobalsolutions.net";

            $subtotal = 0;
            $total_conv_fee = 0;
            $total_amount = 0;

            // ✅ Prepare service list (normalize array format)
            $service_list = !empty($services) ? (isset($services['item_code']) ? [$services] : $services) : [];

            foreach ($service_list as $srv) {
                if (is_array($srv)) {
                    $qty           = floatval($srv['item_qty'] ?? 1);
                    $item_amount   = floatval($srv['item_amount'] ?? 0);
                    $item_other    = floatval($srv['item_other_fees'] ?? 0);
                    $conv_fee_item = floatval($srv['convenience_fee'] ?? 0);

                    // ✅ Compute line totals properly
                    $line_subtotal = ($qty * $item_amount) + $item_other;
                    $line_total = $line_subtotal + $conv_fee_item;

                    $subtotal += $line_subtotal;
                    $total_conv_fee += $conv_fee_item;
                    $total_amount += $line_total;
                }
            }

            // ✅ Auto-calculate amount if missing
            if (empty($amount) || floatval($amount) <= 0) {
                $amount = $total_amount;
            }

            // ✅ Compute conv_fee automatically based on amount
            $amount_val = floatval($amount);
            if ($amount_val >= 1 && $amount_val <= 5000) {
                $conv_fee = 25;
            } elseif ($amount_val >= 5001 && $amount_val <= 1000000) {
                $conv_fee = $amount_val * 0.005;
            } elseif ($amount_val > 1000000) {
                $conv_fee = $amount_val * 0.002;
            } else {
                $this->db->trans_rollback();
                return $this->respond_error('Invalid amount range for convenience fee computation.', 400);
            }

            // ✅ Insert services
            foreach ($service_list as $srv) {
                if (is_array($srv)) {
                    $this->transaction->insert_service($ref_id, $srv);
                }
            }

            // ✅ Build payload for API
            $data = [
                "reference"      => 'REF-' . $this->generateReference(12),
                "refid"          => $ref_id,
                "name"           => $name,
                "amount"         => number_format(floatval($subtotal) + floatval($conv_fee), 2, '.', ''),
                "mobile_number"  => $mobile,
                "email"          => $email,
                "convience_fee"  => $conv_fee,
                "company"        => $company,
                "return_url"     => base_url('/success'),
                "callback_url"   => base_url() . '/api-postback?ref_id=' . $ref_id
            ];

            $result = $this->apiservice->generate_qr_api($data);
            if (is_string($result)) $result = json_decode($result, true);
            $apiData = $result['data'] ?? [];

            $raw_msg = $apiData['raw_string'] ?? json_encode($apiData);
            $raw_json = json_decode($raw_msg, true);

            if (!empty($raw_json['message']) && stripos($raw_json['message'], 'amount must be minimun') !== false) {
                $this->db->trans_rollback();
                $flat_response = $raw_json['raw_response'] ?? $raw_json;
                return $this->respond_error($raw_json['message'], 400, ['raw_response' => $flat_response]);
            }

            if (empty($apiData)) {
                $this->db->trans_rollback();
                return $this->respond_error('Failed to generate transaction from API', 502, [
                    'raw_response' => $result['raw_response'] ?? $result
                ]);
            }

            // ✅ Insert transaction record
            $transaction = [
                'trans_no'          => $apiData['reference_number'] ?? $data['reference'],
                'trans_payor'       => $name,
                'trans_mobile'      => $mobile,
                'trans_email'       => $email,
                'trans_company'     => $company,
                'trans_sub_total'   => $subtotal,
                'trans_conv_fee'    => $conv_fee,
                'trans_grand_total' => floatval($subtotal) + floatval($conv_fee),
                'trans_refid'       => $data['refid'] ?? '',
                'trans_txid'        => $apiData['txn_ref'] ?? '',
                'trans_ref'         => $data['reference'] ?? '',
                'trans_raw_string'  => $raw_msg,
                'trans_date_created' => date('Y-m-d H:i:s'),
                'trans_status'      => 'CREATED'
            ];
            $this->transaction->create_transaction($transaction);

            // ✅ Commit transaction
            $this->db->trans_commit();

            // ✅ Log API call
            $request_method = $this->input->method(TRUE);
            $request_params = !empty($raw_input) ? $raw_input : ($request_method === 'POST' ? $this->input->post(NULL, TRUE) : $this->input->get(NULL, TRUE));
            $log_data = [
                'api_method'      => $request_method,
                'api_params'      => json_encode($request_params),
                'api_response'    => json_encode($result),
                'api_status'      => $result['status_code'] ?? null,
                'api_request_at'  => date('Y-m-d H:i:s'),
                'api_response_at' => date('Y-m-d H:i:s'),
                'api_authorized'  => ""
            ];
            $this->Api->insert_log($log_data);

            // ✅ Success response
            $endpoint = rtrim(api_url(), '/') . '/payment-form?ref=' . $ref_id;
            $response = [
                'success' => true,
                'status_code' => 200,
                'response' => [
                    'redirect_url'     => $endpoint,
                    'raw_string'       => $raw_msg,
                    'created_at'       => date('Y-m-d H:i:s'),
                    'reference_number' => $data['refid'] ?? '',
                    'ref_id'           => $data['reference'],
                    'subtotal'         => number_format($subtotal, 2, '.', ''),
                    'conv_fee'         => number_format($conv_fee, 2, '.', ''),
                    'grand_total'      => number_format(floatval($subtotal) + floatval($conv_fee), 2, '.', '')
                ]
            ];

            return $this->output
                ->set_status_header(200)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return $this->respond_error('Unexpected server error', 500, [
                'error' => $e->getMessage(),
                'file'  => basename($e->getFile()),
                'line'  => $e->getLine()
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
                    'response' => [
                        'message' => 'Method not allowed. Use GET instead.',
                    ]
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
                        'response' => [
                            'message' => 'Missing ref_id',
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
                        'response' => [
                            'message' => 'Transaction not found',
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
                    'response' => [
                        'message' => 'Unexpected server error',
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
                    'response' => [
                        'message' => 'Method not allowed. Use POST instead.',
                    ]
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
                        'response' => [
                            'message' => 'Missing ref_id',
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
                        'response' => [
                            'message' => 'Transaction not found',
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
                        'response' => [
                            'message' => 'Invalid JSON payload',
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
                        'response' => [
                            'message' => 'Duplicate callback - already processed',
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
                    'response' => [
                        'message' => 'Unexpected server error',
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
                    'response' => [
                        'message' => 'Method not allowed. Use POST instead.',
                    ]
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
                        'status' => false,
                        'status_code' => 400,
                        'response' => [
                            'message' => 'Missing ref_id',
                            'details' => []
                        ],
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            $transaction = $this->transaction->get_by_refid($ref_id);
            $result = $this->apiservice->check_status_api([
                'reference_number' => $transaction->trans_no
            ]);
            if (!$transaction) {
                return $this->output
                    ->set_status_header(404)
                    ->set_content_type('application/json', 'utf-8')
                    ->set_output(json_encode([
                        'status' => false,
                        'status_code' => 404,
                        'response' => [
                            'message' => 'Transaction not found',
                            'details' => []
                        ],

                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
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

            $updatedAt = $transaction->updated_at
                ?? $transaction->trans_updated
                ?? $transaction->trans_date
                ?? null;

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
                    'payment_channel' =>$result['response']['data']['payment-channel'] ?? "",
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
                    'status' => true,
                    'status_code' => 500,
                    'response' => [
                        'message' => 'Unexpected server error',
                        'details' => [
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
                    'response' => [
                        'message' => 'Method not allowed. Use GET instead.',
                    ]
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
                    'error'       => [
                        'message' => 'Unexpected server error',
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
            'response' => [
                'message' => $message,
                'details' => $extra,
            ],
        ];

        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
