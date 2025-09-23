<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . 'services/ApiService.php');

class Auth extends CI_Controller
{

	public function __construct()
	{
		parent::__construct();

		// $this->apiService = new ApiService();
		$this->load->library('session');
		$this->load->model('Auth_Model');

		header('Content-Type: application/json');
	}

	public function authenticate()
	{
		$json_input = json_decode($this->input->raw_input_stream, true);
		$username = $json_input['username'] ?? null;
		$password = md5(trim($json_input['password'] ?? ''));

		$result = $this->Auth_Model->login($username, $password);

		if (!$result || $result['account_state'] !== 'ENABLED') {
			$errorMessage = !$result
				? 'Invalid username or password.'
				: 'Please contact NGSI to reactivate your account.';

			echo json_encode([
				'response' => [
					'status'      => 'failed',
					'message'     => $result,
					'data'        => []
				]
			]);
			return; // <-- ADD THIS TO STOP EXECUTION
		}

		$login_token = md5(uniqid() . time() . $result['user_id']);
		$this->Auth_Model->login_token($result['user_id'], $login_token);

		// Store in session
		$this->session->set_userdata([
			'user_id'       => $result['user_id'],
			'name'          => $result['name'],
			'username'      => $result['username'],
			'user_type_id'  => $result['ut_id'],
			'userlevel'     => $result['user_level'],
			'department'    => $result['department'],
			'logged_in'     => TRUE,
			'login_token'   => $login_token
		]);

		$this->Auth_Model->log_user([
			'user_id'       => $result['user_id'],
			'ip_address'    => $this->input->ip_address(),
			'date_added'    => date("Y-m-d H:i:s"),
			'log_type'      => "LOGIN",
			'log_time'      => date("H:i:s"),
			'session_id'    => session_id(),
		]);

		echo json_encode([
			'response' => [
				'status'      => 'success',
				'message'     => 'login successful.',
				'data'        => [
					'user_id'     => $result['user_id'],
					'name'        => $result['name'],
					'username'    => $result['username'],
					'userlevel'   => $result['user_level'],
					'department'  => $result['department'],
					'login_token' => $login_token
				]
			]
		]);
	}

	public function logout()
	{
		$user_id = $this->input->post('user_id');
		$login_token = $this->input->post('	');

		if (!empty($user_id)) {
			$this->Auth_Model->log_user([
				'user_id'       => $user_id,
				'ip_address'    => $this->input->ip_address(),
				'date_added'    => date("Y-m-d H:i:s"),
				'log_type'      => "LOGOUT",
				'log_time'      => date("H:i:s"),
				'session_id'    => session_id(),
			]);

			// Update user status
			$this->Auth_Model->update_user_status($user_id, 'INACTIVE');
		} else {
			log_message('error', 'Logout attempted with null user_id.');
		}
		$this->session->unset_userdata([
			'user_id',
			'name',
			'username',
			'user_type_id',
			'userlevel',
			'department',
			'logged_in',
			'last_activity',
			'session_timeout'
		]);
		$this->session->sess_destroy();

		echo json_encode([
			'response' => [
				'status'      => 'success',
				'method' => strtoupper($this->input->method()),
				'message' => 'logged out successfully.'

			]
		]);
	}


	public function insert_department()
	{
		$this->load->library('form_validation');
		$department_name = trim($this->input->post('department'));
		if (empty($department_name)) {
			$raw_input = file_get_contents("php://input");
			$json = json_decode($raw_input, true);

			if (isset($json['department'])) {
				$department_name = trim($json['department']);
			}
		}
		if (empty($department_name)) {
			echo json_encode([
				'status' => 'error',
				'method' => strtoupper($this->input->method()),
				'message' => 'Department name is required.'
			]);
			return;
		}
		$result = $this->Auth_Model->insert_department($department_name);

		if ($result) {
			echo json_encode([
				'status' => 'success',
				'method' => strtoupper($this->input->method()),
				'message' => 'Department added successfully.'
			]);
		} else {
			echo json_encode([
				'status' => 'error',
				'method' => strtoupper($this->input->method()),
				'message' => 'Failed to add department.'
			]);
		}
	}


	public function get_departments()
	{
		$departments = $this->Auth_Model->get_departments();

		if (!empty($departments)) {
			echo json_encode([
				'status' => 'success',
				'method' => strtoupper($this->input->method()),
				'data' => $departments
			]);
		} else {
			echo json_encode([
				'status' => 'error',
				'method' => strtoupper($this->input->method()),
				'message' => 'No departments found.'
			]);
		}
	}


	public function register()
	{
		$input = json_decode(file_get_contents('php://input'), true);

		$name = $input['name'] ?? null;
		$username = $input['username'] ?? null;
		$password = md5($input['password'] ?? '');
		$user_type_name = $input['user_type'] ?? null;
		$department_name = $input['department'] ?? null;


		$existing_user = $this->Auth_Model->get_user_by_username($username);
		if ($existing_user) {
			echo json_encode([
				'status' => 'error',
				'method' => strtoupper($this->input->method()),
				'message' => 'Username already exists.'
			]);
			return;
		}

		$user_type_id = $this->Auth_Model->get_or_create_user_type($user_type_name);

		// 4. Get or create department
		$department_id = $this->Auth_Model->get_or_create_department($department_name);

		$user_data = [
			'name' => $name,
			'username' => $username,
			'password' => $password,
			'user_type_id' => $user_type_id,
			'department_id' => $department_id,
			'account_status' => 'ACTIVE',
			'account_state' => 'ENABLED',
			'session_id' => NULL,
			'login_token' => NULL,
			'last_login' => NULL,
			'date_created' => date('Y-m-d H:i:s'),
			'date_modified' => date('Y-m-d H:i:s')
		];

		// 6. Insert user
		if ($this->Auth_Model->register_user($user_data)) {

			echo json_encode([
				'status' => 'success',
				'method' => strtoupper($this->input->method()),
				'message' => 'User registered successfully.',
				'user_type_id' => $user_type_id,
				'department_id' => $department_id
			]);
		} else {
			echo json_encode([
				'status' => 'error',
				'method' => strtoupper($this->input->method()),
				'message' => 'Failed to register user.'
			]);
		}
	}
}
