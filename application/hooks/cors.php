<?php
defined('BASEPATH') or exit('No direct script access allowed');

function enable_cors()
{
    // During dev: allow all
    header("Access-Control-Allow-Origin: *");

    // Methods & headers
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding, X-Requested-With, Authorization, X-API-KEY");

    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}
