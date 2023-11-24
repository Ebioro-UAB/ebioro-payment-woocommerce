<?php

if ( ! defined( 'ABSPATH' ) ) {
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
	 * */
	public static $log;

    /**
	 * Call the $log variable function.
	 *
	 * @param string $message Log message.
	 * @param string $level   Optional. Default 'info'.
	 *     emergency|alert|critical|error|warning|notice|info|debug
	 */
	public static function log( $message, $level = 'info' ) {
		return call_user_func( self::$log, $message, $level );
	}

    /**
	 * Ebioro API url
	 * 
	 * @var string Ebioro API url.
	 * */
	public static $api_url = 'https://test-merchant.ebioro.com/';

    /**
	 * Ebioro API version
	 * 
	 * @var string Ebioro API version.
	 * */
	public static $api_version = '2023-11-23';

    /**
	 * Ebiro API key
	 * 
	 * @var string Ebiro API key.
	 * */
	public static $api_key;


    /**
	 * Ebiro API secret key
	 * 
	 * @var string Ebiro API secret key.
	 * */
	public static $api_secret;

    /**
	 * Get the response from an API request.
	 * 
	 * @param  string $endpoint
	 * @param  array  $params
	 * @param  string $method
	 * @return array
	 */
	public static function send_request( $endpoint, $params = array(), $method = 'GET' ) {
		// phpcs:ignore
		self::log( 'Ebioro Request Args for ' . $endpoint . ': ' . print_r( $params, true ) );
        $authHeaders = $this->buildAuthHeaders($endpoint, $method, $params); 
		
        $args = array(
			'method'  => $method,
			'headers' => $authHeaders
		);

		$url = self::$api_url . $endpoint;

		if ( in_array( $method, array( 'POST', 'PUT' ) ) ) {
			$args['body'] = json_encode( $params );
		} else {
			$url = add_query_arg( $params, $url );
		}
		$response = wp_remote_request( esc_url_raw( $url ), $args );

		if ( is_wp_error( $response ) ) {
			self::log( 'WP response error: ' . $response->get_error_message() );
			return array( false, $response->get_error_message() );
		} else {
			$result = json_decode( $response['body'], true );
			if ( ! empty( $result['warnings'] ) ) {
				foreach ( $result['warnings'] as $warning ) {
					self::log( 'API Warning: ' . $warning );
				}
			}

			$code = $response['response']['code'];

			if ( in_array( $code, array( 200, 201 ), true ) ) {
				return array( true, $result );
			} else {
				$e      = empty( $result['error']['message'] ) ? '' : $result['error']['message'];
				$errors = array(
					400 => 'Error response from API: ' . $e,
					401 => 'Authentication error, please check your API signature.',
					429 => 'Ebioro API rate limit exceeded.',
				);

				if ( array_key_exists( $code, $errors ) ) {
					$msg = $errors[ $code ];
				} else {
					$msg = 'Unknown response from API: ' . $code;
				}
				self::log( $msg );

				return array( false, $code );
			}
		}
	}

    /**
	 * Create a new charge request.
	 * 
	 * @param  int    $amount
	 * @param  string $currency
	 * @param  array  $metadata
	 * @param  string $redirect
	 * @param  string $name
	 * @param  string $desc
	 * @param  string $cancel
	 * @return array
	 */
	public static function create_payment( $amount = null, $currency = null, $metadata = null,
        $redirect = null, $name = null, $desc = null,$cancel = null ) {
        
        $args = array(
        'name'        => is_null( $name ) ? get_bloginfo( 'name' ) : $name,
        'description' => is_null( $desc ) ? get_bloginfo( 'description' ) : $desc,
        );
        $args['name'] = sanitize_text_field( $args['name'] );
        $args['description'] = sanitize_text_field( $args['description'] );

        if ( is_null( $amount ) ) {
        self::log( 'Error: amount cannot be missing (in create_payment()).', 'error' );
        return array( false, 'Missing amount.' );
        } elseif ( is_null( $currency ) ) {
        self::log( 'Error: if amount is given, currency must be given (in create_payment()).', 'error' );
        return array( false, 'Missing currency.' );
        } else {
        
        $args['amount']  = array(
        'value'   => $amount,
        'currency' => $currency,
        );
        }

        if ( ! is_null( $metadata ) ) {
        $args['metadata'] = $metadata;
        }
        if ( ! is_null( $redirect ) ) {
        $args['redirect_url'] = $redirect;
        }
        if ( ! is_null( $cancel ) ) {
        $args['cancel_url'] = $cancel;
        }

        $result = self::send_request( 'payments', $args, 'POST' );

        return $result;
        }
        

    /**
     * Automatically generates authentication headers.
     *
     * @param $path
     * @param $method
     * @param array $params
     * @return array
     */
    private function buildAuthHeaders($path, $method, $params = array()) {

        $timestamp = time();
        $body = $method != 'GET' ? (count($params) ? json_encode($params) : null) : null;
        $origin = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : null;
        

        return array(
            'X-Digest-Key: ' . $this->$api_key,
            'X-Digest-Signature: ' . hash_hmac('sha256', $path . $timestamp . $method . $body, $this->$api_secret),
            'X-Digest-Timestamp: ' . $timestamp,
            'X-Origin-URL: ' . $origin,
            'X-API-Version: ' . $this->$api_version
        );

    }


}