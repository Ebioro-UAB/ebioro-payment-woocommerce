<?php
/**
 * Ebioro Payment Gateway
 *
 * This file contains the Ebioro Payment Gateway class for WooCommerce.
 *
 * @package   ebioro-payment-woocommerce
 * @subpackage Ebioro_Payment_Gateway
 * @since     1.1.2
 * @author    Ebioro UAB
 * @link      https://www.ebioro.com/
 * @license   GPL-3.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Class that handles the Ebioro payment gateway.
 *
 * @class Ebioro_Payment_Gateway
 * @extends WC_Payment_Gateway
 */
class Ebioro_Payment_Gateway extends WC_Payment_Gateway {
	/**
	 * Log_enabled - whether or not logging is enabled
	 *
	 * @var bool Whether or not logging is enabled
	 */
	public static $log_enabled = false;

	/**
	 * WC_Logger Logger instance
	 *
	 * @var WC_Logger Logger instance
	 * */
	public static $log = false;

	/**
	 * Resolved API key for the active mode (live or test).
	 *
	 * @var string
	 */
	protected $api_key;

	/**
	 * Resolved API secret for the active mode (live or test).
	 *
	 * @var string
	 */
	protected $api_secret;

	/**
	 * Whether the "Debug log" option is enabled.
	 *
	 * @var bool
	 */
	protected $debug = false;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id = 'ebioro';
		$this->has_fields = false;
		$this->order_button_text = __( 'Proceed to Ebioro', 'ebioro-payment-woocommerce' );
		$this->method_title = __( 'Ebioro', 'ebioro-payment-woocommerce' );
		$this->method_description = '<p>' . esc_html__( 'A payment gateway allows your customers to pay with the ebioro wallet', 'ebioro-payment-woocommerce' ) . '</p>';

		$this->init_form_fields();
		$this->init_settings();

		$this->title = $this->get_option( 'title', __( 'Pay with crypto', 'ebioro-payment-woocommerce' ) );
		$this->description = $this->get_option( 'description' );
		$this->api_key = ( self::is_test_mode() ) ? $this->get_option( 'test_api_key' ) : $this->get_option( 'api_key' );
		$this->api_secret = ( self::is_test_mode() ) ? $this->get_option( 'test_api_secret' ) : $this->get_option( 'api_secret' );
		$this->debug = 'yes' === $this->get_option( 'debug', 'no' );

		self::$log_enabled = $this->debug;

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', array( $this, 'get_order_by_payment_id' ), 10, 2 );
		// WooCommerce lowercases the wc-api query value via sanitize_key() before firing
		// the action, so the webhook URL (?wc-api=Ebioro_Payment_Gateway) maps to this
		// exact hook name. A mismatch here makes WooCommerce answer 400 "-1" without
		// ever reaching the plugin.
		add_action( 'woocommerce_api_ebioro_payment_gateway', array( $this, 'handle_webhook' ) );
	}

	/**
	 * Logging method.
	 *
	 * @param string $message Log message.
	 * @param string $level Optional Default emergency|alert|critical|error|warning|notice|info|debug.
	 */
	public static function log( $message, $level = 'info' ) {
		if ( self::$log_enabled ) {
			if ( empty( self::$log ) ) {
				self::$log = wc_get_logger();
			}
			self::$log->log(
				$message,
				$level
			);
		}
	}

	/**
	 * Verbose logging, emitted only when WP_DEBUG is actually truthy AND the
	 * gateway's Debug log option is on. `defined('WP_DEBUG')` alone is always true
	 * (wp-settings.php defines it, defaulting to false), so it must be evaluated,
	 * not merely tested for existence.
	 *
	 * @param string $message Log message.
	 */
	public static function debug_log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			self::log( $message );
		}
	}

	/**
	 * Supported store currencies. Ebioro only quotes these; on any other
	 * currency the API rejects the charge, so the gateway must not offer itself.
	 *
	 * @var string[]
	 */
	const SUPPORTED_CURRENCIES = array( 'USD', 'EUR', 'CAD', 'GBP' );

	/**
	 * Only offer the gateway when the store currency is one Ebioro supports.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( ! in_array( get_woocommerce_currency(), self::SUPPORTED_CURRENCIES, true ) ) {
			return false;
		}
		return parent::is_available();
	}

	/**
	 * Get gateway icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		if ( $this->get_option( 'show_icons' ) === 'no' ) {
			return '';
		}

		$image_path = plugin_dir_path( __FILE__ ) . 'assets/images';
		$icon_html = '';
		$methods = get_option( 'ebioro_payment_methods', array( $this->id ) );

		// Load icon for each available payment method.
		foreach ( $methods as $m ) {
			$path = realpath( $image_path . '/' . $m . '.png' );
			if ( $path && dirname( $path ) === $image_path && is_file( $path ) ) {
				$url = WC_HTTPS::force_https_url( plugins_url( '/assets/images/' . $m . '.png', __FILE__ ) );

				// translators: %s is the payment method name.
				$alt_text = sprintf( esc_attr__( 'Payment method: %s', 'ebioro-payment-woocommerce' ), $m );

				$icon_html .= sprintf(
					'<img src="%s" alt="%s" width="40" style="vertical-align:middle; margin-right:4px;" />',
					esc_url( $url ),
					esc_attr( $alt_text )
				);
			}
		}

		/** DOCBLOCK - Makes linter happy.
		 *
		 * @since today
		 */
		return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'       => __( 'Enable/Disable', 'ebioro-payment-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Ebioro Payments', 'ebioro-payment-woocommerce' ),
				'default'     => 'no',
			),
			'title' => array(
				'title'       => __( 'Title', 'ebioro-payment-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'ebioro-payment-woocommerce' ),
				'default'     => __( 'Pay with crypto', 'ebioro-payment-woocommerce' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'ebioro-payment-woocommerce' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => __( 'This controls the description which the user sees during checkout.', 'ebioro-payment-woocommerce' ),
				'default'     => __( 'Pay with ease using the ebioro wallet.', 'ebioro-payment-woocommerce' ),
			),
			'show_icons' => array(
				'title'       => __( 'Payment icons', 'ebioro-payment-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Show payment method icons at checkout.', 'ebioro-payment-woocommerce' ),
				'default'     => 'yes',
			),
			'api_key' => array(
				'title'       => __( 'API Key', 'ebioro-payment-woocommerce' ),
				'type'        => 'password',
				'description' => esc_html__( 'Get your API Key from the Ebioro Settings page.', 'ebioro-payment-woocommerce' ),
			),
			'api_secret' => array(
				'title'       => __( 'API Secret', 'ebioro-payment-woocommerce' ),
				'type'        => 'password',
				'description' => esc_html__( 'Get your API Secret from the Ebioro Settings page.', 'ebioro-payment-woocommerce' ),
			),
			'api_locale' => array(
				'title'       => esc_html__( 'Language of Payment Page', 'ebioro-payment-woocommerce' ),
				'description' => esc_html__( 'Select the language in which your customers see the payment page', 'ebioro-payment-woocommerce' ),
				'id'          => 'woo_ebioro_api_locale',
				'type'        => 'select',
				'options'     => array(
					'en' => esc_html__( 'English', 'ebioro-payment-woocommerce' ),
					'es' => esc_html__( 'Spanish', 'ebioro-payment-woocommerce' ),
				),
				'default'     => 'en',
			),
			'test_mode' => array(
				'title'       => __( 'Enable test mode', 'ebioro-payment-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable test mode to work in development environment.', 'ebioro-payment-woocommerce' ),
				'default'     => 'no',
			),
			'test_api_key' => array(
				'title'       => __( 'Test API Key', 'ebioro-payment-woocommerce' ),
				'type'        => 'password',
				'description' => esc_html__( 'Get your Test API Key from the Ebioro Settings page.', 'ebioro-payment-woocommerce' ),
			),
			'test_api_secret' => array(
				'title'       => __( 'Test API Secret', 'ebioro-payment-woocommerce' ),
				'type'        => 'password',
				'description' => esc_html__( 'Get your Test API Secret from the Ebioro Settings page.', 'ebioro-payment-woocommerce' ),
			),
			'debug' => array(
				'title'       => __( 'Debug log', 'ebioro-payment-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging', 'ebioro-payment-woocommerce' ),
				'default'     => 'no',
				'description' => sprintf(
					// translators: %s is the file path where the Ebioro API events are logged.
					esc_html__( 'Log Ebioro API events inside %s', 'ebioro-payment-woocommerce' ),
					'<code>' . esc_html( \WC_Log_Handler_File::get_log_file_path( $this->id ) ) . '</code>'
				),
			),
		);
	}
	


	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id The ID of the order to process payment for.
	 * @return array Returns an array with the result of the payment process, including a 'result' key and optionally a 'redirect' URL.
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		// Create description for charge based on order's products. Ex: 1 x Product1, 2 x Product2.
		try {
			$order_items = array_map( function ( $item ) {
				return $item['quantity'] . ' x ' . $item['name'];
			}, $order->get_items() );

			$description = mb_substr( implode( ', ', $order_items ), 0, 200 );
		} catch ( Exception $e ) {
			$description = null;
		}

		$this->init_api();

		// Create a new charge. Only order_id is needed to resolve the order on the
		// webhook; order_key is deliberately NOT sent — it is a capability secret
		// for the order-pay/received pages and must not travel through the API or logs.
		$metadata = array(
			'order_id' => $order->get_id(),
			'source' => 'woocommerce',
		);
		$result = Ebioro_API_Handler::create_payment(
			$order->get_total(),
			// Charge in the ORDER's currency (multi-currency stores diverge from the
			// store base currency returned by get_woocommerce_currency()).
			$order->get_currency(),
			$metadata,
			$this->get_return_url( $order ),
			null,
			$description,
			$this->get_cancel_url( $order ),
			$this->get_webhook_url(),
			// Idempotency key: namespaced by site + order + order-key so two stores
			// sharing one merchant credential (staging/prod, multi-store) never collide
			// on the same auto-increment order id. A genuine same-order retry (same
			// site, same order) reuses the key so the API replays instead of
			// double-creating.
			$this->get_idempotency_key( $order )
		);

		self::debug_log( 'Payment creation result: ' . wp_json_encode( array( 'ok' => (bool) $result[0], 'id' => is_array( $result[1] ) ? ( $result[1]['id'] ?? null ) : $result[1] ) ) );

		// $result is [ success(bool), data|error ] — on failure the second element
		// is an error string/status code, not a payment object.
		if ( ! $result[0] || ! is_array( $result[1] ) || empty( $result[1]['id'] ) ) {
			self::log( 'Payment creation failed: ' . esc_html( wp_json_encode( is_array( $result[1] ) ? ( $result[1]['id'] ?? '' ) : $result[1] ) ), 'error' );
			wc_add_notice(
				__( 'We could not start your Ebioro payment. Please try again or choose another payment method.', 'ebioro-payment-woocommerce' ),
				'error'
			);
			// WooCommerce convention is 'failure' (not 'fail'); anything but 'success'
			// aborts checkout, and 'failure' makes core surface the notice above.
			return array( 'result' => 'failure' );
		}

		$order->update_meta_data( '_ebioro_payment_id', $result[1]['id'] );
		// Record the mode the payment was created in so webhooks signed by the other
		// environment (test vs live) can be rejected in _update_order_status().
		$order->update_meta_data( '_ebioro_mode', self::is_test_mode() ? 'test' : 'live' );
		$order->save();

		// Prefer the tokenless short link over hostedUrl: hostedUrl carries a
		// merchant-scoped auth token in the query string, which would then sit in the
		// customer's browser history and referrer headers.
		$redirect = ! empty( $result[1]['shortUrl'] ) ? $result[1]['shortUrl'] : $result[1]['hostedUrl'];

		return array(
			'result' => 'success',
			'redirect' => $redirect,
		);
	}

	/**
	 * Build a per-site, per-order idempotency key.
	 *
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	protected function get_idempotency_key( $order ) {
		$seed = home_url() . '|' . $order->get_id() . '|' . $order->get_order_key();
		return 'wc-' . substr( hash( 'sha256', $seed ), 0, 40 );
	}

	/**
	 * Get the return url (thank you page).
	 *
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	public function get_return_url( $order = null ) {
		if ( $order ) {
			$return_url = $order->get_checkout_order_received_url();
		} else {
			$return_url = wc_get_endpoint_url( 'order-received', '', wc_get_checkout_url() );
		}

		return apply_filters( 'woocommerce_get_return_url', html_entity_decode( $return_url ), $order );
	}

	/**
	 * Get the cancel url.
	 *
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	public function get_cancel_url( $order ) {

		$return_url = $order->get_cancel_order_url();

		if ( is_ssl() || get_option( 'woocommerce_force_ssl_checkout' ) === 'yes' ) {
			$return_url = str_replace( 'http:', 'https:', $return_url );
		}

		return apply_filters( 'woocommerce_get_cancel_url', html_entity_decode( $return_url ), $order );
	}

	/**
	 * Ge the webhook url.
	 *
	 * @return string
	 */
	public function get_webhook_url() {
		// Force https: Ebioro refuses to deliver webhooks to an http endpoint, and
		// get_home_url() can return an http scheme on mixed-config stores.
		return add_query_arg( 'wc-api', 'Ebioro_Payment_Gateway', set_url_scheme( trailingslashit( get_home_url() ), 'https' ) );
	}

	/**
	 * Reconcile still-pending Ebioro orders against the API (hourly cron backstop
	 * for missed webhooks). Paginates so a backlog larger than one page is not
	 * silently truncated.
	 */
	public function check_orders() {
		$this->init_api();

		$page = 1;
		$per_page = 50;

		do {
			$orders = wc_get_orders(
				array(
					'status'     => array( 'wc-pending' ),
					'limit'      => $per_page,
					'page'       => $page,
					'orderby'    => 'ID',
					'order'      => 'ASC',
					'meta_query' => array(
						array(
							'key'     => '_ebioro_payment_id',
							'compare' => 'EXISTS',
						),
					),
				)
			);

			foreach ( $orders as $order ) {
				$payment_id = $order->get_meta( '_ebioro_payment_id' );
				if ( empty( $payment_id ) ) {
					continue;
				}

				usleep( 300000 );  // Ensure we don't hit the rate limit.
				$result = Ebioro_API_Handler::send_request( '/payments/' . rawurlencode( $payment_id ) );

				if ( ! $result[0] ) {
					self::log( 'Failed to fetch order updates for: ' . $order->get_id() );
					continue;
				}

				$data = $result[1];
				self::debug_log( 'Reconciling order ' . $order->get_id() . ': status=' . ( $data['status'] ?? '' ) . ' settlement=' . ( $data['settlement_status'] ?? '' ) );
				$this->_update_order_status( $order, $data );
			}

			++$page;
		} while ( count( $orders ) === $per_page );
	}

	/**
	 * Handle requests sent to webhook.
	 */
	public function handle_webhook() {
		// Server-to-server call from Ebioro: authentication is the HMAC signature
		// checked in validate_webhook(). WordPress nonces are session-bound CSRF
		// tokens and can never be supplied by an external server — do not add one here.
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
		if ( 'POST' !== $request_method ) {
			$this->webhook_response( 405, 'Method not allowed' );
		}

		$payload = file_get_contents( 'php://input' );

		if ( empty( $payload ) || ! $this->validate_webhook( $payload ) ) {
			self::log( 'Incoming webhook failed signature validation' );
			$this->webhook_response( 401, 'Invalid signature' );
		}

		$payload_decoded = json_decode( $payload, true );

		if ( ! is_array( $payload_decoded ) || ! isset( $payload_decoded['data'] ) || ! is_array( $payload_decoded['data'] ) ) {
			self::log( 'Webhook payload is not valid JSON with a data object' );
			$this->webhook_response( 400, 'Invalid payload' );
		}

		$event_data = $payload_decoded['data'];

		self::debug_log( 'Webhook received: id=' . ( $event_data['id'] ?? '' ) . ' type=' . ( $event_data['type'] ?? '' ) . ' status=' . ( $event_data['status'] ?? '' ) . ' order=' . ( $event_data['metadata']['order_id'] ?? '' ) );

		if ( ! isset( $event_data['metadata']['order_id'] ) ) {
			self::log( 'Webhook payload missing metadata.order_id' );
			$this->webhook_response( 400, 'Invalid payload' );
		}

		$order = wc_get_order( $event_data['metadata']['order_id'] );

		if ( ! $order ) {
			self::log( 'Webhook references unknown order: ' . esc_html( $event_data['metadata']['order_id'] ) );
			$this->webhook_response( 404, 'Order not found' );
		}

		// Defence in depth: only act on orders that are actually paying via Ebioro.
		if ( $order->get_payment_method() !== $this->id ) {
			self::log( 'Webhook for non-Ebioro order ' . $order->get_id() . ' ignored.' );
			$this->webhook_response( 200, 'Ignored: not an Ebioro order' );
		}

		// Bind the event to the payment currently attached to the order. A superseded
		// payment (a retry created a newer one) or a spoofed id for another order must
		// not drive this order's state. Answer 200 so Ebioro does not record a failed
		// delivery for a legitimately stale event.
		$bound_payment_id = $order->get_meta( '_ebioro_payment_id' );
		if ( $bound_payment_id && ! empty( $event_data['id'] ) && ! hash_equals( (string) $bound_payment_id, (string) $event_data['id'] ) ) {
			self::log( 'Webhook payment ' . esc_html( $event_data['id'] ) . ' does not match bound payment for order ' . $order->get_id() . ' — ignored.' );
			$this->webhook_response( 200, 'Ignored: stale payment' );
		}

		$this->_update_order_status( $order, $event_data );
		$this->webhook_response( 200, 'OK' );
	}

	/**
	 * Send a short plain-text webhook response and stop.
	 *
	 * The webhook caller (Ebioro) only acts on the HTTP status code; a terse body
	 * keeps the delivery log readable (wp_die() would emit a full HTML page that
	 * gets truncated to head boilerplate in the dashboard).
	 *
	 * @param int    $code    HTTP status code.
	 * @param string $message Short plain-text body.
	 * @return never
	 */
	private function webhook_response( $code, $message ) {
		status_header( (int) $code );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo esc_html( $message );
		exit;
	}

	/**
	 * Reads a single request header, checking $_SERVER first and falling back
	 * to getallheaders() (some FastCGI setups don't populate HTTP_* vars).
	 * Only the requested key is touched — never the whole $_SERVER array.
	 *
	 * @param string $server_key Key in $_SERVER form (e.g. 'HTTP_X_WEBHOOK_AUTH').
	 * @param string $header_name Header name (e.g. 'X-Webhook-Auth').
	 * @return string Sanitized header value, or empty string when absent.
	 */
	public function get_request_header( $server_key, $header_name ) {
		if ( isset( $_SERVER[ $server_key ] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER[ $server_key ] ) );
		}

		if ( function_exists( 'getallheaders' ) ) {
			$wanted = strtolower( $header_name );
			foreach ( getallheaders() as $name => $value ) {
				if ( strtolower( $name ) === $wanted ) {
					return sanitize_text_field( wp_unslash( $value ) );
				}
			}
		}

		return '';
	}

	/**
	 * Validates the webhook by checking the signature and payload.
	 *
	 * @param mixed $payload The data sent by the webhook (can be a string or an array).
	 * @return bool True if the webhook is valid, false otherwise.
	 */
	public function validate_webhook( $payload ) {
		self::log( 'Checking Webhook response is valid' );

		// Read only the one header we need ($_SERVER first, getallheaders() fallback) —
		// filter_input( INPUT_SERVER ) is unreliable under nginx/FastCGI.
		$sig = $this->get_request_header( 'HTTP_X_WEBHOOK_AUTH', 'X-Webhook-Auth' );

		if ( empty( $sig ) ) {
			self::log( 'Missing or invalid X-WEBHOOK-AUTH header' );
			return false;
		}

		// The signature is computed over the raw request body, byte for byte.
		$jsonString = is_string( $payload ) ? $payload : wp_json_encode( $payload );

		$api_secret = $this->api_secret;

		if ( ! $api_secret ) {
			self::log( 'API secret key not available' );
			return false;
		}

		// Calculate the HMAC signature using SHA-256.
		$sig2 = hash_hmac( 'sha256', $jsonString, $api_secret );

		// Validate the signature.
		if ( hash_equals( $sig2, $sig ) ) {
			return true;
		} else {
			self::log( 'Invalid signature' );
			return false;
		}
	}


	/**
	 * Init the API class and set the API key etc.
	 */
	protected function init_api() {
		include_once dirname( __FILE__ ) . '/includes/class-ebioro-api-handler.php';

		Ebioro_API_Handler::$log = get_class( $this ) . '::log';
		Ebioro_API_Handler::$api_key = $this->api_key;
		Ebioro_API_Handler::$api_secret = $this->api_secret;
	}

	/**
	 * Apply an Ebioro payment status to an order.
	 *
	 * Called from two sources with the same payload shape:
	 *  - webhook `transaction_updated` events (real-time), and
	 *  - the hourly reconciliation poll, whose GET response `type` is `order`
	 *    (not a `transaction_*` event). Anything that is not a `transaction_*`
	 *    event is therefore treated as a status snapshot.
	 *
	 * Cancellation-class statuses (`expired`/`canceled`/`underpaid`) only ever
	 * downgrade an order that is still awaiting payment — they can never undo an
	 * order that has already been paid (here, via a second Ebioro delivery, or
	 * out of band through another gateway).
	 *
	 * @param WC_Order $order      The WooCommerce order object.
	 * @param array    $event_data The transaction/payment data.
	 */
	public function _update_order_status( $order, $event_data ) {
		$payload_type      = $event_data['type'] ?? '';
		$order_status      = $event_data['status'] ?? '';
		$settlement_status = $event_data['settlement_status'] ?? '';
		$event_mode        = $event_data['mode'] ?? '';
		$event_updated_at  = isset( $event_data['updatedAt'] ) ? strtotime( (string) $event_data['updatedAt'] ) : 0;

		// Reject events from the other environment (a live webhook while the gateway
		// is in test mode, or vice versa) — they must not settle this order.
		$order_mode = $order->get_meta( '_ebioro_mode' );
		if ( $order_mode && $event_mode && $order_mode !== $event_mode ) {
			self::log( 'Ignoring ' . esc_html( $event_mode ) . '-mode event for ' . esc_html( $order_mode ) . '-mode order ' . $order->get_id() );
			return;
		}

		// Drop a stale replay: an event older than the last one we applied. Equal
		// timestamps are allowed so a reconciliation poll can still complete an order
		// whose webhook was missed.
		$last_event_at = (int) $order->get_meta( '_ebioro_last_event_at' );
		if ( $event_updated_at && $last_event_at && $event_updated_at < $last_event_at ) {
			self::debug_log( 'Dropping stale event for order ' . $order->get_id() );
			return;
		}

		// Per-order lock so two concurrent deliveries can't both run payment_complete
		// (double emails / double stock reduction). Self-heals after 30s if a request
		// dies mid-flight.
		$lock_key = 'ebioro_lock_' . $order->get_id();
		if ( get_transient( $lock_key ) ) {
			self::debug_log( 'Order ' . $order->get_id() . ' locked by a concurrent event — skipping.' );
			return;
		}
		set_transient( $lock_key, 1, 30 );

		try {
			// Re-read inside the lock so status checks see the latest persisted state.
			$order = wc_get_order( $order->get_id() );

			if ( 'transaction_failed' === $payload_type ) {
				$order->add_order_note( __( 'Ebioro payment failed to be delivered to your merchant account.', 'ebioro-payment-woocommerce' ) );
			} elseif ( 'transaction_created' === $payload_type ) {
				// WooCommerce already set the order to pending on creation — nothing to do.
				$order->add_order_note( __( 'Ebioro payment was created and is awaiting the customer.', 'ebioro-payment-woocommerce' ) );
			} else {
				// transaction_updated OR a reconciliation snapshot: drive off status.
				$this->apply_payment_status( $order, $order_status, $settlement_status );
			}

			$statuses = array(
				'customer_payment_status'  => esc_attr( $order_status ),
				'ebioro_settlement_status' => esc_attr( $settlement_status ),
			);
			$order->update_meta_data( '_ebioro_payment_state', $statuses );
			if ( $event_updated_at ) {
				$order->update_meta_data( '_ebioro_last_event_at', $event_updated_at );
			}
			$order->save();
		} finally {
			delete_transient( $lock_key );
		}
	}

	/**
	 * Apply a customer-payment / settlement status pair to an order.
	 *
	 * @param WC_Order $order             Order object (already lock-protected, freshly read).
	 * @param string   $order_status      Ebioro customer payment status.
	 * @param string   $settlement_status Ebioro settlement status.
	 */
	protected function apply_payment_status( $order, $order_status, $settlement_status ) {
		$awaiting = array( 'pending', 'on-hold', 'failed' );

		switch ( $order_status ) {
			case 'paid':
				// Only transition while still awaiting payment. payment_complete() sets
				// the status (processing/completed for virtual orders), the payment date
				// and the transaction id, and fires woocommerce_payment_complete — so it
				// MUST run before any manual status change, not after (a preceding
				// update_status() would push the order out of the valid-for-completion
				// set and neutralise it). The Ebioro payment reference is stored as the
				// WooCommerce transaction id.
				if ( in_array( $order->get_status(), $awaiting, true ) ) {
					$order->payment_complete( $order->get_meta( '_ebioro_payment_id' ) );
					$order->add_order_note( __( 'Customer payment received via Ebioro.', 'ebioro-payment-woocommerce' ) );
				}

				// Settlement notes, guarded so a replay/poll does not repeat them.
				if ( 'processing' === $settlement_status && ! $order->get_meta( '_ebioro_settlement_initiated_note' ) ) {
					$order->add_order_note( __( 'Ebioro payment has been initiated to your merchant account.', 'ebioro-payment-woocommerce' ) );
					$order->update_meta_data( '_ebioro_settlement_initiated_note', 1 );
				}
				if ( 'paid' === $settlement_status && ! $order->get_meta( '_ebioro_settlement_delivered_note' ) ) {
					$order->add_order_note( __( 'Ebioro payment has been delivered to your merchant account.', 'ebioro-payment-woocommerce' ) );
					$order->update_meta_data( '_ebioro_settlement_delivered_note', 1 );
				}
				break;

			case 'underpaid':
				// Put on hold only from a still-awaiting state; never disturb a paid order.
				if ( in_array( $order->get_status(), array( 'pending', 'failed' ), true ) ) {
					$order->update_status( 'on-hold', __( 'Ebioro payment has been underpaid by customer.', 'ebioro-payment-woocommerce' ) );
				}
				break;

			case 'expired':
			case 'canceled':
				// Cancel only an order that was never paid. A paid/processing/completed
				// order (paid here or through another gateway) is left untouched; we only
				// note the event for the merchant.
				if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
					$note = ( 'expired' === $order_status )
						? __( 'Ebioro payment expired.', 'ebioro-payment-woocommerce' )
						: __( 'Ebioro payment canceled.', 'ebioro-payment-woocommerce' );
					$order->update_status( 'cancelled', $note );
				} else {
					$order->add_order_note(
						sprintf(
							/* translators: 1: Ebioro payment status, 2: current WooCommerce order status. */
							__( 'Ebioro reported "%1$s" but the order is already "%2$s" — no change made.', 'ebioro-payment-woocommerce' ),
							$order_status,
							$order->get_status()
						)
					);
				}
				break;
		}
	}

	/**
	 * Handle a custom '_ebioro_payment_id' query var to get orders with the '_ebioro_payment_id' meta.
	 *
	 * @param array $query - Args for WP_Query.
	 * @param array $query_vars - Query vars from WC_Order_Query.
	 * @return array modified $query
	 */
	public function get_order_by_payment_id( $query, $query_vars ) {
		if ( ! empty( $query_vars['_ebioro_payment_id'] ) ) {
			$query['meta_query'][] = array(
				'key'   => '_ebioro_payment_id',
				'value' => esc_attr( $query_vars['_ebioro_payment_id'] ),
			);
		}

		return $query;
	}

	/**
	 * Check for Test Mode
	 *
	 * @return bool
	 */
	private static function is_test_mode() {
		$data = get_option( 'woocommerce_ebioro_settings' );
		return is_array( $data ) && isset( $data['test_mode'] ) && 'yes' === $data['test_mode'];
	}

	/**
	 * Plugin url.
	 *
	 * @return string
	 */
	public static function plugin_url() {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

	/**
	 * Plugin path.
	 *
	 * @return string
	 */
	public static function plugin_abspath() {
		return trailingslashit( plugin_dir_path( __FILE__ ) );
	}
}
