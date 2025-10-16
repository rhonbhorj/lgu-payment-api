<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Exceptions extends CI_Exceptions
{
    /**
     * Handle uncaught exceptions
     */
    public function show_exception($exception)
    {
        // If output buffer already has content, assume controller handled it
        if (ob_get_length()) {
            return;
        }

        // Default HTTP status
        $code = $exception->getCode();
        if ($code < 100 || $code >= 600) {
            $code = 500;
        }

        // Log the exception
        log_message('error', "Uncaught Exception: " . $exception->getMessage());

        // Check for specific API messages in exception message
        $message = 'Internal Server Error';
        if (strpos($exception->getMessage(), 'amount must be minimun') !== false) {
            $message = 'The amount must be minimun of 10';
            $code = 400;
        }

        // Prepare JSON response
        $response = [
            'success' => false,
            'message' => $message,
            'error'   => $exception->getMessage(),
            'timestamp' => date('Y-m-d H:i:s'),
            'path' => current_url()
        ];

        // Clean output buffer to prevent mixed content
        while (ob_get_level()) {
            ob_end_clean();
        }

        set_status_header($code);
        header('Content-Type: application/json');
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Handle PHP runtime errors
     */
    public function show_php_error($severity, $message, $filepath, $line)
    {
        // Ignore errors suppressed by @
        if (error_reporting() === 0) {
            return;
        }

        // Clean output buffer
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Log PHP error
        log_message('error', "PHP Error: {$message} in {$filepath} on line {$line}");

        // Prepare JSON response
        $response = [
            'success' => false,
            'message' => 'A PHP error occurred.',
            'error'   => $message,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        set_status_header(500);
        header('Content-Type: application/json');
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
