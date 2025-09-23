<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class SanitationService 
{
    protected $CI;

    public function __construct() 
    {
        $this->CI =& get_instance();
        $this->CI->load->database();
    }
    
    public function sanitize($data)
    {
        if(is_array($data))
        {
            foreach ($data as $key => $value)
            {
                $data[$key] = $this->sanitize($value);
            }
        }

        else 
        {
            $data = substr($this->CI->db->escape($data), 1, -1);
        }
    
        return $data;
    }
    
}