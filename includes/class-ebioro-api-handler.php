<?php
/**
 * Stablecoin (USDC) Payment Processor - A payment gateway that allows your customers to pay with stablecoins via Ebioro.
 *
 * @package ebioro-payment-woocommerce
 * @author  Ebioro UAB
 * @license GPL-3.0+
 */

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
	 * @param string $level   Optional. Default 'info'. emergency|alert|critical|error|warning|notice|info|debug.
	 */
	public static function log( $message, $level = 'info' ) {
		return call_user_func( self::$log, $message, $level );
	}

	/**
	 * Get the response from an API request.
	 *
	 * @param string $endpoint La URL o el endpoint al que se realiza la solicitud.
	 * @param array  $params  Los parámetros que se pasan en la solicitud.
	 * @param string $endpoint The URL or endpoint to which the request is made.
	 * @param array  $params  The parameters passed in the request.
	 * @param string $method  The HTTP method (GET, POST, PUT, etc.).
	 *
	 * @return array
	 */
	public static function send_request( $endpoint, $params = array(), $method = 'GET' ) {

		if ( defined( 'WP_DEBUG' ) ) {
			self::log( 'Ebioro Request Args for ' . esc_html( $endpoint ) . ': ' . wp_json_encode( $params ) );
		}

		$authHeaders = self::buildAuthHeaders( $endpoint, $method, $params );

		$args = array(
			'method'  => $method,
			'headers' => $authHeaders,
		);

		$url = self::api_url() . $endpoint;

		if ( in_array( $method, array( 'POST', 'PUT' ), true ) ) {
			$args['body'] = wp_json_encode( $params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		} else {
			$url = add_query_arg( $params, $url );
		}

		$response = wp_remote_request( esc_url_raw( $url ), $args );

		self::log( 'the body to send: ' . esc_html( wp_json_encode( $args, true ) ) );

		if ( is_wp_error( $response ) ) {
			self::log( 'WP response error: ' . $response->get_error_message() );
			return array( false, $response->get_error_message() );
		} else {
			$result = json_decode( $response['body'], true );

			if ( null === $result && JSON_ERROR_NONE !== json_last_error() ) {
				self::log( 'Error decoding JSON response: ' . json_last_error_msg() );
				return array( false, 'Error decoding JSON response.' );
			}

			$status_code = $response['response']['code'];

			if ( in_array( $status_code, array( 200, 201 ), true ) ) {
				return array( true, $result );
			} else {
				$e = empty( $result[1]['message'] ) ? '' : $result[1]['message'];

				$errors = array(
					400 => 'Error response from API: ' . $e,
					401 => 'Authentication error, please check your API signature.',
					429 => 'Ebioro API rate limit exceeded.',
				);

				if ( array_key_exists( $status_code, $errors ) ) {
					$msg = $errors[ $status_code ];
				} else {
					$msg = 'Unknown response from API: ' . $status_code;
				}

				self::log( $msg );

				return array( false, $status_code );
			}
		}
	}

	/**
	 * Creates a payment with the given parameters.
	 *
	 * @param mixed  $amount      The amount of the payment.
	 * @param string $currency    The currency of the payment.
	 * @param array  $metadata    Additional data related to the payment.
	 * @param string $redirect    The URL to which the user will be redirected after completing the payment.
	 * @param string $name        The name associated with the payment.
	 * @param string $desc        The description of the payment.
	 * @param string $cancelUrl   The URL to which the user will be redirected if they cancel the payment.
	 * @param string $webhookUrl  The webhook URL for receiving event notifications.
	 *
	 * @return mixed The result of the payment creation, could be an array or object depending on implementation.
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
		$args = array();

		// Set 'name' and 'description' in $args.
		$args['name']  = is_null( $name ) ? get_bloginfo( 'name' ) : $name;
		$args['description'] = sanitize_text_field( is_null( $desc ) ? get_bloginfo( 'description' ) : $desc );

		// Validate and set 'amount' and 'currency' in $args.
		if ( is_null( $amount ) ) {
			self::log( 'Error: amount cannot be missing (in create_payment()).', 'error' );
			return array( false, 'Missing amount.' );
		} elseif ( is_null( $currency ) ) {
			self::log( 'Error: if amount is given, currency must be given (in create_payment()).', 'error' );
			return array( false, 'Missing currency.' );
		} else {
			// Convert amount to smallest unit before creating the request.
			$amount         = self::convert_to_smallest_unit( $amount, $currency );
			$args['amount'] = array(
				'value' => $amount,
				'currency' => $currency,
			);
		}

		$args['redirectUrl'] = $redirect;

		$data         = get_option( 'woocommerce_ebioro_settings' );
		$args['locale'] = $data['api_locale'];

		// Set optional parameters in $args.
		$optional_params = array( 'metadata', 'cancelUrl', 'webhookUrl' );

		foreach ( $optional_params as $param ) {
			if ( ! is_null( $$param ) ) {
				$args[ $param ] = $$param;
			}
		}

		// Make the API request.
		$result = self::send_request( '/payments', $args, 'POST' );

		return $result;
	}

	/**
	 * Automatically generates authentication headers based on the provided request parameters.
	 *
	 * This function is responsible for constructing the necessary authentication headers for making API requests, typically by generating a signature or adding authorization tokens to the request.
	 *
	 * @param string $path   The API endpoint path for the request.
	 * @param string $method The HTTP method to be used for the request (e.g., "GET", "POST").
	 * @param array  $params The parameters to be included in the request body or query string.
	 *
	 * @return array An associative array containing the generated authentication headers.
	 */
	private static function buildAuthHeaders( $path, $method, $params = array() ) {
		$timestamp = current_time( 'timestamp' );

		$body = 'GET' != $method ? wp_json_encode( $params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : '';

		// Logging the string to be signed.
		$tosign = $path . $timestamp . $method . $body;

		self::log( 'String to be signed: ' . esc_html( wp_json_encode( $tosign, true ) ) );

		// Generate signature.
		$signature = hash_hmac( 'sha256', $tosign, self::$api_secret );

		// Prepare headers.
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
	private static function api_url() {
		$data = get_option( 'woocommerce_ebioro_settings' );
		$url = ( 'yes' === $data['test_mode'] ) ? self::$test_api_url : self::$api_url;
		return $url;
	}

	/**
	 * Converts an amount to the smallest unit (e.g., cents) to deal with decimal precision issues.
	 *
	 * @param float  $amount   The amount to be converted.
	 * @param string $currency The currency of the amount (used for future modifications if necessary).
	 *
	 * @return int The amount converted to the smallest unit (e.g., cents).
	 */
	private static function convert_to_smallest_unit( $amount, $currency ) {
		// Assuming a simple conversion rate of 100 for most currencies.
		// This will need to be modified in case of special currencies that
		// does not comply with this.
		return (int) round( $amount * 100 );
	}
}
