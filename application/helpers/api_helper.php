<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('api_url')) {
    function api_url()
    {
        $CI =& get_instance();
        return $CI->config->item('api_url');
    }
}
