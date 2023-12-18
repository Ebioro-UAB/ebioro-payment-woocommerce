<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sends API requests to Ebioro.
 */
class Ebioro_API_Handler {

    /**
     * Log variable function
     *
     * @var string/array Log variable function.
     */
    public static $log;

    /**
     * Ebioro API url
     *
     * @var string Ebioro API url.
     */
    public static $api_url = 'https://merchant-api.ebioro.com';

    /**
     * Ebioro Test API url
     *
     * @var string Ebioro Test API url
     */
    public static $test_api_url = 'https://test-merchant.ebioro.com';

    /**
     * Ebioro API version
     *
     * @var string Ebioro API version.
     */
    public static $api_version = '2023-11-23';

    /**
     * Ebiro API key
     *
     * @var string Ebiro API key.
     */
    public static $api_key;

    /**
     * Ebiro API secret key
     *
     * @var string Ebiro API secret key.
     */
    public static $api_secret;

    /**
     * Call the $log variable function.
     *
     * @param string $message Log message.
     * @param string $level   Optional. Default 'info'.
     *                        emergency|alert|critical|error|warning|notice|info|debug
     */
    public static function log($message, $level = 'info') {
        return call_user_func(self::$log, $message, $level);
    }

    /**
     * Get the response from an API request.
     *
     * @param string $endpoint
     * @param array  $params
     * @param string $method
     *
     * @return array
     */
    public static function send_request($endpoint, $params = array(), $method = 'GET') {
        
        self::log('Ebioro Request Args for ' . $endpoint . ': ' . print_r($params, true));

        $authHeaders = self::buildAuthHeaders($endpoint, $method, $params);

        $args = array(
            'method'  => $method,
            'headers' => $authHeaders,
        );

        $url = self::api_url() . $endpoint;

        if (in_array($method, array('POST', 'PUT'))) {
            $args['body'] = json_encode($params);
        } else {
            $url = add_query_arg($params, $url);
        }

        // self::log('Ebioro Request arguments for ' . $endpoint . ': ' . print_r($args, true));

        $response = wp_remote_request(esc_url_raw($url), $args);

        if (is_wp_error($response)) {
            self::log('WP response error: ' . $response->get_error_message());
            return array(false, $response->get_error_message());
        } else {
            
            $result = json_decode($response['body'], true);

            if ($result === null && json_last_error() !== JSON_ERROR_NONE) {
                self::log('Error decoding JSON response: ' . json_last_error_msg());
                return array(false, 'Error decoding JSON response.');
            }

            $status_code = $response['response']['code'];

            if (in_array($status_code, array(200, 201), true)) {
                return array(true, $result);
            } else {
                $e = empty($result['error']['message']) ? '' : $result['error']['message'];
                $errors = array(
                    400 => 'Error response from API: ' . $e,
                    401 => 'Authentication error, please check your API signature.',
                    429 => 'Ebioro API rate limit exceeded.',
                );

                if (array_key_exists($status_code, $errors)) {
                    $msg = $errors[$status_code];
                } else {
                    $msg = 'Unknown response from API: ' . $status_code;
                }
                self::log($msg);

                return array(false, $status_code);
            }
        }
    }

    /**
     * Create a new payment request.
     *
     * @param int    $amount
     * @param string $currency
     * @param array  $metadata
     * @param string $redirect
     * @param string $name
     * @param string $desc
     * @param string $cancel
     *
     * @return array
     */
    public static function create_payment(
        $amount = null,
        $currency = null,
        $metadata = null,
        $redirect = null,
        $name = null,
        $desc = null,
        $cancelUrl = null,
        $webhookUrl = null
    ) {
        // Initialize $args as an associative array.
        $args = [];
    
        // Set 'name' and 'description' in $args.
        $args['name'] = is_null($name) ? get_bloginfo('name') : $name;
        $args['description'] = sanitize_text_field(is_null($desc) ? get_bloginfo('description') : $desc);
    
        // Validate and set 'amount' and 'currency' in $args.
        if (is_null($amount)) {
            self::log('Error: amount cannot be missing (in create_payment()).', 'error');
            return [false, 'Missing amount.'];
        } elseif (is_null($currency)) {
            self::log('Error: if amount is given, currency must be given (in create_payment()).', 'error');
            return [false, 'Missing currency.'];
        } else {
            $args['amount'] = [
                'value' => is_numeric($amount) ? number_format((float)$amount, 2, '.', '') : 0.0,
                'currency' => $currency
            ];
        }

        $args['redirectUrl'] = $redirect;

        $data = get_option( 'woocommerce_ebioro_settings' );
        $args['locale'] = $data['api_locale'];
    
        // Set optional parameters in $args.
        $optionalParams = ['metadata', 'cancelUrl', 'webhookUrl'];
        foreach ($optionalParams as $param) {
            if (!is_null($$param)) {
                $args[$param] = $$param;
            }
        }
    
        // Make the API request.
        $result = self::send_request('/payments', $args, 'POST');
    
        return $result;
    }

    /**
     * Automatically generates authentication headers.
     *
     * @param string $path
     * @param string $method
     * @param array  $params
     *
     * @return array
     */
    private static function buildAuthHeaders($path, $method, $params = array()) {
       
        $timestamp = time();
        $body = $method != 'GET' ? (count($params) ? is_string($params) ? $params : json_encode($params) : null) : null;

        // this is because json_encode in php escapes forward slashes but JSON.stringify on the server does not.
        // we therefore need to be consistent to be able to form the signature.
        $tosign = $path . $timestamp . $method .str_replace('\/', '/', $body);
        
      
        $signature = hash_hmac('sha256', $tosign, self::$api_secret);

        $headers = array(
            'Content-Type'          => 'application/json',
            'X-Digest-Key'          => self::$api_key,
            'X-Digest-Signature'    => $signature,
            'X-Digest-Timestamp'    => $timestamp,
        );

        return $headers;
    }

    /**
     * Get the API URL
     */
    private static function api_url()
    {
        $data = get_option( 'woocommerce_ebioro_settings' );
        $url = ( 'yes' == $data['test_mode'] ) ? self::$test_api_url : self::$api_url;
        return $url;
    }
}
