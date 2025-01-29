<?php

if (!defined('ABSPATH')) {
	exit;
}

class Ebioro_Payment_Gateway extends WC_Payment_Gateway
{

	/**
	 * Log_enabled - whether or not logging is enabled
	 * 
	 * @var bool	Whether or not logging is enabled 
	 */
	public static $log_enabled = false;

	/** 
	 * WC_Logger Logger instance
	 * 
	 * @var WC_Logger Logger instance
	 * */
	public static $log = false;

	/**
	 * Timeout for archiving orders
	 *
	 * @var WC_DateTime Timeout for archiving orders
	 */
	protected $timeout;


	/**
	 * Constructor for the gateway.
	 */
	public function __construct()
	{
		$this->id = 'ebioro';
		$this->has_fields = false;
		$this->order_button_text = __('Proceed to Ebioro', 'ebioro-payment-woocommerce');
		$this->method_title = __('Ebioro', 'ebioro-payment-woocommerce');
		$this->method_description = '<p>' .
    		esc_html__('A payment gateway allows your customers to pay with the ebioro wallet', 'ebioro-payment-woocommerce') . '</p>';

		$this->init_form_fields();
		$this->init_settings();

		$this->timeout = (new WC_DateTime())->sub(new DateInterval('P1D'));

		$this->title = $this->get_option('title');
		$this->description = $this->get_option('description');
		$this->api_key = ( self::is_test_mode() ) ? $this->get_option( 'test_api_key' ) : $this->get_option('api_key');
		$this->api_secret = ( self::is_test_mode() ) ? $this->get_option( 'test_api_secret' ) : $this->get_option('api_secret');
		$this->debug = 'yes' === $this->get_option('debug', 'no');

		self::$log_enabled = $this->debug;

		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_filter('woocommerce_order_data_store_cpt_get_orders_query', array($this, 'get_order_by_payment_id'), 10, 2);
		add_action('woocommerce_api_wc_gateway_ebioro_payment_gateway', array($this, 'handle_webhook'));
	}

	/**
	 * Logging method.
	 *
	 * @param string $message Log message.
	 * @param string $level   Optional. Default 'info'.
	 *     emergency|alert|critical|error|warning|notice|info|debug
	 */
	public static function log($message, $level = 'info')
	{
		if (self::$log_enabled) {
			if (empty(self::$log)) {
				self::$log = wc_get_logger();
			}
			self::$log->log(
				$message,
				$level
			);
		}
	}

	/**
	 * Get gateway icon.
	 * 
	 * @return string
	 */
	public function get_icon()
	{
		if ($this->get_option('show_icons') === 'no') {
			return '';
		}

		$image_path = plugin_dir_path(__FILE__) . 'assets/images';
		$icon_html = '';
		$methods = get_option('ebioro_payment_methods', array($this->id));

		// Load icon for each available payment method.
		foreach ($methods as $m) {
			$path = realpath($image_path . '/' . $m . '.png');
			if ($path && dirname($path) === $image_path && is_file($path)) {
				$url = WC_HTTPS::force_https_url(plugins_url('/assets/images/' . $m . '.png', __FILE__));

				// translators: %s is the payment method name
				$alt_text = sprintf(esc_attr__('Payment method: %s', 'ebioro-payment-woocommerce'), $m);
				$icon_html .= wp_get_attachment_image($attachment_id, 'thumbnail', false, array('alt' => $alt_text, 'width' => '40'));

			}
		}
		

		/** DOCBLOCK - Makes linter happy.
		 *  
		 * @since today
		 */
		return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled' => array(
				'title' => __('Enable/Disable', 'ebioro-payment-woocommerce'),
				'type' => 'checkbox',
				'label' => __('Enable Ebioro Payments', 'ebioro-payment-woocommerce'),
				'default' => 'no',
			),
			'title' => array(
				'title' => __('Title', 'ebioro-payment-woocommerce'),
				'type' => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'ebioro-payment-woocommerce'),
				'default' => __('Ebioro wallet', 'ebioro-payment-woocommerce'),
				'desc_tip' => true,
			),
			'description' => array(
				'title' => __('Description', 'ebioro-payment-woocommerce'),
				'type' => 'text',
				'desc_tip' => true,
				'description' => __('This controls the description which the user sees during checkout.', 'ebioro-payment-woocommerce'),
				'default' => __('Pay with ease using the ebioro wallet.', 'ebioro-payment-woocommerce'),
			),
			'api_key' => array(
				'title' => __('API Key', 'ebioro-payment-woocommerce'),
				'type' => 'text',
				'description' => esc_html__('Get your API Key from the Ebioro Settings page.', 'ebioro-payment-woocommerce'),
			),
			'api_secret' => array(
				'title' => __('API Secret', 'ebioro-payment-woocommerce'),
				'type' => 'text',
				'description' => esc_html__('Get your API Secret from the Ebioro Settings page.', 'ebioro-payment-woocommerce'),
			),
			'api_locale' => array(
				'title' => esc_html__('Language of Payment Page', 'ebioro-payment-woocommerce'),
				'description' => esc_html__('Select the language in which your customers see the payment page', 'ebioro-payment-woocommerce'),
				'id' => 'woo_ebioro_api_locale',
				'type' => 'select',
				'options' => array(
					'en' => esc_html__('English', 'ebioro-payment-woocommerce'),
					'es' => esc_html__('Spanish', 'ebioro-payment-woocommerce'),
				),
				'default' => 'en',
			),
			'test_mode' => array(
				'title' => __('Enable test mode', 'ebioro-payment-woocommerce'),
				'type' => 'checkbox',
				'label' => __('Enable test mode to work in development environment.', 'ebioro-payment-woocommerce'),
				'default' => 'no',
			),
			'test_api_key' => array(
				'title' => __('Test API Key', 'ebioro-payment-woocommerce'),
				'type' => 'text',
				'description' => esc_html__('Get your Test API Key from the Ebioro Settings page.', 'ebioro-payment-woocommerce'),
			),
			'test_api_secret' => array(
				'title' => __('Test API Secret', 'ebioro-payment-woocommerce'),
				'type' => 'text',
				'description' => esc_html__('Get your Test API Secret from the Ebioro Settings page.', 'ebioro-payment-woocommerce'),
			),
			'debug' => array(
				'title' => __('Debug log', 'ebioro-payment-woocommerce'),
				'type' => 'checkbox',
				'label' => __('Enable logging', 'ebioro-payment-woocommerce'),
				'default' => 'no',
				'description' => sprintf(
					// translators: %s is the file path where the Ebioro API events are logged
					esc_html__('Log Ebioro API events inside %s', 'ebioro-payment-woocommerce'),
					'<code>' . esc_html(\WC_Log_Handler_File::get_log_file_path($this->id)) . '</code>'
				),
			),
		);
	}


	/**
	 * Process the payment and return the result.
	 * 
	 * @param  int $order_id
	 * @return array
	 */
	public function process_payment($order_id)
	{

		$order = wc_get_order($order_id);

		// Create description for charge based on order's products. Ex: 1 x Product1, 2 x Product2
		try {
			$order_items = array_map(function ($item) {
				return $item['quantity'] . ' x ' . $item['name'];
			}, $order->get_items());

			$description = mb_substr(implode(', ', $order_items), 0, 200);
		} catch (Exception $e) {
			$description = null;
		}

		$this->init_api();

		// Create a new charge.
		$metadata = array(
			'order_id' => $order->get_id(),
			'order_key' => $order->get_order_key(),
			'source' => 'woocommerce'
		);
		$result = Ebioro_API_Handler::create_payment(
			$order->get_total(),
			get_woocommerce_currency(),
			$metadata,
			$this->get_return_url($order),
			null,
			$description,
			$this->get_cancel_url($order),
			$this->get_webhook_url()
		);

		if (defined('WP_DEBUG')) {
			self::log("API Result: " . esc_html(wp_json_encode($result, true)));
		}
		
		if (!$result[1]['id']) {
			return array('result' => 'fail');
		}

		if (defined('WP_DEBUG')) {
			self::log("Redirect url: " . esc_url(wp_json_encode($result[1]['hostedUrl'], true)));
		}

		$order->update_meta_data('_ebioro_payment_id', $result[1]['id']);
		$order->save();

		return array(
			'result' => 'success',
			'redirect' => $result[1]['hostedUrl'],
		);
	}

	/**
	 * Get the return url (thank you page).
	 *
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	public function get_return_url($order = null)
	{
		if ($order) {
			$return_url = $order->get_checkout_order_received_url();
		} else {
			$return_url = wc_get_endpoint_url('order-received', '', wc_get_checkout_url());
		}

		return apply_filters('woocommerce_get_return_url', html_entity_decode($return_url), $order);
	}

	/**
	 * Get the cancel url.
	 *
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	public function get_cancel_url($order)
	{

		$return_url = $order->get_cancel_order_url();

		if (is_ssl() || get_option('woocommerce_force_ssl_checkout') == 'yes') {
			$return_url = str_replace('http:', 'https:', $return_url);
		}

		return apply_filters('woocommerce_get_cancel_url', html_entity_decode($return_url), $order);
	}

	/**
	 * Ge the webhook url.
	 *
	 * @return string
	 */
	public function get_webhook_url()
	{
		return add_query_arg('wc-api', 'Ebioro_Payment_Gateway', trailingslashit(get_home_url()));
	}

	/**
	 * Check payment statuses on orders and update order statuses.
	 */
	public function check_orders()
	{
		$this->init_api();

		// Check the status of non-archived Ebioro orders.
		$orders = wc_get_orders(
			array(
				'ebioro_archived' => false,
				'status' => array('wc-pending'),
				'meta_query' => array(
					array(
						'key' => '_ebioro_archived',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key' => '_ebioro_payment_id',
						'compare' => 'EXISTS',
					)
				)
			)
		);

		foreach ($orders as $order) {
			$payment_id = $order->get_meta('_ebioro_payment_id');

			usleep(300000);  // Ensure we don't hit the rate limit.
			$result = Ebioro_API_Handler::send_request('/payments/' . $payment_id);

			if (!$result[0]) {
				self::log('Failed to fetch order updates for: ' . $order->get_id());
				continue;
			}

			$data = $result[1];
			if (defined('WP_DEBUG')) {
				self::log('Updating status for order: ' . esc_html(wp_json_encode($data, true)));
			}
			$this->_update_order_status($order, $data);
		}
	}

	/**
	 * Handle requests sent to webhook.
	 */
	public function handle_webhook()
	{

		if (
			'POST' !== filter_input( INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_STRING )
			|| !isset( $_GET['wc-api'] )
			|| 'Ebioro_Payment_Gateway' !== sanitize_text_field( wp_unslash( $_GET['wc-api'] ) )
			|| !isset( $_GET['_wpnonce'] ) 
			|| !wp_verify_nonce( wp_unslash( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) ), 'ebioro_payment_action' )
		) {
			return;
		}

		$payload = file_get_contents('php://input');
		
		if (empty($payload) || !$this->validate_webhook($payload)) {
			self::log('Incoming webhook failed validation: ' . esc_html( wp_json_encode($payload, true) ) );
			wp_die('Webhook validation failed', 'Webhook Error', array('response' => 401));
		}

		self::log('Webhook received event: ' . esc_html(wp_json_encode($payload, true)));

		if (!empty($payload)) {
			$payload_decoded = json_decode($payload, true);
			$event_data = $payload_decoded['data'];

			if (!isset($event_data['metadata']['order_id'])) {
				self::log('Webhook with signature not created by ebioro');
				wp_die('Webhook validation failed', 'Webhook Error', array('response' => 401));
			}

			$order_id = $event_data['metadata']['order_id'];
			$this->_update_order_status(wc_get_order($order_id), $event_data);
			wp_die('Webhook processed successfully', 'Webhook Success', array('response' => 200));
		}

		wp_die('Ebioro Webhook Request Failure', 'Ebioro Webhook', array('response' => 500));


	}

	/**
	 * Gets the incoming request headers. Some servers are not using
	 * Apache and "getallheaders()" will not work so we may need to
	 * build our own headers.
	 */
	public function get_request_headers()
	{
		if (!function_exists('getallheaders')) {
			$headers = array();

			$headers = [];
			foreach ($_SERVER as $name => $value) {
				if (strpos($name, 'HTTP_') === 0) {
					$sanitized_value = filter_var($value, FILTER_SANITIZE_STRING);
					$header_name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
					$headers[$header_name] = $sanitized_value;
				}
			}

			return $headers;
		} else {
			return getallheaders();
		}
	}

	/**
	 * Check Ebioro webhook request is valid.
	 * 
	 * @param  string $payload
	 * @param  object $request_headers
	 */
	public function validate_webhook($payload)
	{
		self::log('Checking Webhook response is valid');

		$webhookAuthHeader = filter_input(INPUT_SERVER, 'HTTP_X_WEBHOOK_AUTH', FILTER_SANITIZE_STRING);

		if (empty($webhookAuthHeader)) {
			self::log('Missing or invalid X-WEBHOOK-AUTH header');
			return false;
		}

		$sig = filter_input(INPUT_SERVER, 'HTTP_X_WEBHOOK_AUTH', FILTER_SANITIZE_STRING);

		if (empty($sig)) {
			self::log('Invalid or missing X-WEBHOOK-AUTH header');
			return false;
		}

		// Check if $payload is already a JSON string
		$jsonString = is_string($payload) ? $payload : json_encode($payload);

		$api_secret = $this->api_secret;

		if (!$api_secret) {
			self::log('API secret key not available');
			return false;
		}

		// Calculate the HMAC signature using SHA-256
		$sig2 = hash_hmac('sha256', $jsonString, $api_secret);
		
		// Validate the signature
		if (hash_equals($sig2, $sig)) {
			return true;
		} else {
			self::log('Invalid signature');
			return false;
		}
	}


	/*
	 * Init the API class and set the API key etc.
	 */
	protected function init_api()
	{
		include_once dirname(__FILE__) . '/includes/class-ebioro-api-handler.php';

		Ebioro_API_Handler::$log = get_class($this) . '::log';
		Ebioro_API_Handler::$api_key = $this->api_key;
		Ebioro_API_Handler::$api_secret = $this->api_secret;
	}

	/**
	 * Update the status of an order from a given timeline.
	 * 
	 * @param  WC_Order $order
	 * @param  object    $event_data: the transaction data provided by the webhook.
	 */
	public function _update_order_status($order, $event_data)
	{

        $ebioro_payload_state = $event_data['type'];
		$ebioro_order_status = $event_data['status'];
		$ebioro_order_settlement_status = $event_data['settlement_status'];

		// webhooks
		if ($ebioro_payload_state){

			// we are ignoring the state 'transaction_created' because woocommerce automatically
		    // sets the order status to Pending when created..
			if ($ebioro_payload_state==='transaction_updated'){

				if ('paid'===$ebioro_order_status && 'open'===$ebioro_order_settlement_status && 'pending' == $order->get_status()){
	
					$order->update_status( 'processing', __( 'Customer payment was successfully processed. Pending Ebioro payment', 'ebioro-payment-woocommerce' ) );
					$order->payment_complete();
	
				}
	
				if ('processing' === $ebioro_order_settlement_status && 'paid' === $ebioro_order_status ){
	
					$order->add_order_note( __( 'Ebioro payment has been initiated to your merchant account.', 'ebioro-payment-woocommerce' ) );
	
				}
	
				if ('paid' === $ebioro_order_settlement_status && 'paid' === $ebioro_order_status ){
	
					$order->add_order_note( __( 'Ebioro payment has been delivered to your merchant account.', 'ebioro-payment-woocommerce' ) );
	
				}
	
				if ('underpaid' === $ebioro_order_status){
					$order->update_status('on-hold');
					$order->update_status( 'on-hold', __( 'Ebioro payment has been underpaid by customer.', 'ebioro-payment-woocommerce' ) );
	
				}
	
				if ('expired' === $ebioro_order_status){
					$order->update_status( 'cancelled', __( 'Ebioro payment expired.', 'ebioro-payment-woocommerce' ) );
				}
	
				if ('canceled' ===$ebioro_order_status){
					$order->update_status( 'cancelled', __( 'Ebioro payment canceled.', 'ebioro-payment-woocommerce' ) );
				}
	
	
			}
	
			if ($ebioro_payload_state ==='transaction_failed'){
	
				$order->add_order_note( __( 'Ebioro payment failed to be delivered to your merchant account.', 'ebioro-payment-woocommerce' ) );
	
			}

		}

		// no webhooks (ie. cron job that checks for the status of the transaction)

		else {

			if ('expired' === $ebioro_order_status && 'pending' == $order->get_status()){
				
				$order->update_status( 'cancelled', __( 'Ebioro payment expired.', 'ebioro-payment-woocommerce' ) );
			}

		}

	
		$statuses = array(
			'customer_payment_status' => esc_attr($ebioro_order_status),
			'ebioro_settlement_status' => esc_attr($ebioro_order_settlement_status),
		);

		$order->update_meta_data('_ebioro_payment_state', $statuses);
		$order->save();
	}

	/**
	 * Handle a custom '_ebioro_payment_id' query var to get orders with the '_ebioro_payment_id' meta.
	 * @param array $query - Args for WP_Query.
	 * @param array $query_vars - Query vars from WC_Order_Query.
	 * @return array modified $query
	 */
	public function get_order_by_payment_id($query, $query_vars)
	{

		if (!empty($query_vars['_ebioro_payment_id'])) {
			$query['meta_query'][] = array(
				'key' => '_ebioro_payment_id',
				'value' => esc_attr($query_vars['_ebioro_payment_id']),
			);
		}

		return $query;
	}

	/**
	 * Check for Test Mode
	 *
	 * @return bool
	 */
	private static function is_test_mode()
	{
		$data = get_option( 'woocommerce_ebioro_settings' );
		if ( 'yes' == $data['test_mode'] )
			return true;
		return false;
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
