<?php

use Restserver\Libraries\REST_Controller;

defined('BASEPATH') or exit('No direct script access allowed');


class Api extends REST_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Api_model');
        header("Content-Type: application/json");
    }
}
