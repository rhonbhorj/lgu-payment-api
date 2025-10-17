<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Auth extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->library('session');
		$this->load->model('Auth_Model' , 'Auth_model');
		header('Content-Type: application/json');

		header("Access-Control-Allow-Origin: *");
		header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding, X-Requested-With, Authorization, X-API-KEY");
	}

	/**
	 * Authenticate user login
	 */
	public function authenticate()
	{
		$json_input = json_decode($this->input->raw_input_stream, true);

		$username = trim($json_input['username'] ?? '');
		$password = md5(trim($json_input['password'] ?? ''));

		$result = $this->Auth_model->login($username, $password);

		// Handle invalid credentials or disabled accounts
		if (!$result || $result['account_state'] !== 'ENABLED') {
			$message = !$result
				? 'Invalid username or password.'
				: 'Please contact the administrator to reactivate your account.';

			$response = [
				'status'  => 'failed',
				'message' => $message,
				'response' => []
			];

			echo json_encode($response, JSON_PRETTY_PRINT);
			return;
		}

		// Generate secure login token
		$login_token = md5(uniqid() . time() . $result['user_id']);
		$this->Auth_model->login_token($result['user_id'], $login_token);

		// Update account status
		$result['account_status'] = 'ACTIVE';

		// Save session data
		$this->session->set_userdata([
			'user_id'        => $result['user_id'],
			'name'           => $result['name'],
			'username'       => $result['username'],
			'user_type_id'   => $result['ut_id'],
			'userlevel'      => $result['user_level'],
			'department'     => $result['department'],
			'account_status' => $result['account_status'],
			'logged_in'      => TRUE,
			'login_token'    => $login_token
		]);

		// Log user login
		$this->Auth_model->log_user([
			'user_id'    => $result['user_id'],
			'ip_address' => $this->input->ip_address(),
			'date_added' => date("Y-m-d H:i:s"),
			'log_type'   => "LOGIN",
			'log_time'   => date("H:i:s"),
			'session_id' => session_id(),
		]);

		// Build success response
		$response = [
			'status'  => 'success',
			'message' => 'Login successful.',
			'response' => [
				'user_id'        => $result['user_id'],
				'name'           => $result['name'],
				'username'       => $result['username'],
				'userlevel'      => $result['user_level'],
				'department'     => $result['department'],
				'account_status' => $result['account_status'],
				'login_token'    => $login_token
			]
		];

		echo json_encode($response, JSON_PRETTY_PRINT);
	}


	/**
	 * Logout user
	 */
	public function logout()
	{
		// Read user_id from POST or JSON
		$user_id = $this->input->post('user_id');
		if (empty($user_id)) {
			$json_input = json_decode($this->input->raw_input_stream, true);
			$user_id = $json_input['user_id'] ?? null;
		}

		if (!empty($user_id)) {
			$this->Auth_model->log_user([
				'user_id'    => $user_id,
				'ip_address' => $this->input->ip_address(),
				'date_added' => date("Y-m-d H:i:s"),
				'log_type'   => "LOGOUT",
				'log_time'   => date("H:i:s"),
				'session_id' => session_id(),
			]);

			// ✅ Update DB to INACTIVE
			$this->Auth_model->update_user_status($user_id, 'INACTIVE');
		} else {
			log_message('error', 'Logout attempted with null user_id.');
		}

		// Destroy session
		$this->session->unset_userdata([
			'user_id',
			'name',
			'username',
			'user_type_id',
			'userlevel',
			'department',
			'account_status',
			'logged_in',
			'last_activity',
			'session_timeout'
		]);
		$this->session->sess_destroy();

		echo json_encode([
			'status'         => 'success',
			'message'        => 'Logged out successfully.',
			'response' => [
				'account_status' => 'INACTIVE'
			]
		]);
	}


	public function register()
	{
		$input = json_decode(file_get_contents('php://input'), true);

		$name            = $input['name'] ?? null;
		$username        = $input['username'] ?? null;
		$password        = md5($input['password'] ?? '');
		$user_type_name  = $input['user_type'] ?? null;
		$department_name = $input['department'] ?? "GENERAL"; // Default

		// Check if username exists
		$existing_user = $this->Auth_model->get_user_by_username($username);
		if ($existing_user) {
			echo json_encode([
				'status'  => 'error',
				'method'  => strtoupper($this->input->method()),
				'message' => 'Username already exists.'
			]);
			return;
		}

		$user_type_id  = $this->Auth_model->get_or_create_user_type($user_type_name);
		$department_id = $this->Auth_model->get_or_create_department($department_name);

		$user_data = [
			'name'          => $name,
			'username'      => $username,
			'password'      => $password,
			'user_type_id'  => $user_type_id,
			'department_id' => $department_id,
			'account_status' => 'ACTIVE',
			'account_state' => 'ENABLED',
			'session_id'    => NULL,
			'login_token'   => NULL,
			'last_login'    => NULL,
			'date_created'  => date('Y-m-d H:i:s'),
			'date_modified' => date('Y-m-d H:i:s')
		];

		if ($this->Auth_model->register_user($user_data)) {
			echo json_encode([
				'status'        => 'success',
				'method'        => strtoupper($this->input->method()),
				'message'       => 'User registered successfully.',
				'user_type_id'  => $user_type_id,
				'department_id' => $department_id
			]);
		} else {
			echo json_encode([
				'status'  => 'error',
				'method'  => strtoupper($this->input->method()),
				'message' => 'Failed to register user.'
			]);
		}
	}

	/**
	 * List departments
	 */
	public function get_departments()
	{
		$departments = $this->Auth_model->get_departments();
		echo json_encode([
			'status'  => !empty($departments) ? 'success' : 'error',
			'method'  => strtoupper($this->input->method()),
			'data'    => $departments ?: [],
			'message' => empty($departments) ? 'No departments found.' : null
		]);
	}
}
