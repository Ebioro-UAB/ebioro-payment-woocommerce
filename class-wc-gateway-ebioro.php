<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class WC_Gateway_Ebioro extends WC_Payment_Gateway {

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
    public function __construct() {
        $this->id                 = 'ebioro';
        $this->has_fields         = false;
        $this->order_button_text  = __('Proceed to Ebioro', 'ebioro');
        $this->method_title       = __('Ebioro', 'ebioro');
        $this->method_description = '<p>' .
            __('A payment gateway that sends your customers to Ebioro to pay with cryptocurrency.', 'ebioro') .
            '</p><p>' .
            sprintf(
                __('If you do not currently have a Ebioro account, you can set one up here: %s', 'ebioro'),
                '<a target="_blank" href="https://ebioro.com/">https://ebioro.com/</a>'
            );

        $this->init_form_fields();
        $this->init_settings();

        $this->timeout = (new WC_DateTime())->sub(new DateInterval('P1D'));

        $this->title     = $this->get_option('title');
        $this->description   = $this->get_option('description');
        $this->api_key   = $this->get_option('api_key');
        $this->api_secret    = $this->get_option('api_secret');
        $this->debug     = 'yes' === $this->get_option('debug', 'no');

        self::$log_enabled = $this->debug;

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_filter('woocommerce_order_data_store_cpt_get_orders_query', array($this, '_custom_query_var'), 10, 2);
        add_action('woocommerce_api_wc_gateway_ebioro', array($this, 'handle_webhook'));
    }

	/**
     * Logging method.
     *
     * @param string $message Log message.
     * @param string $level   Optional. Default 'info'.
     *     emergency|alert|critical|error|warning|notice|info|debug
     */
    public static function log($message, $level = 'info') {
        if (self::$log_enabled) {
            if (empty(self::$log)) {
                self::$log = wc_get_logger();
            }
            self::$log->log($level, $message, array('source' => 'ebioro'));
        }
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
		$icon_html  = '';
		$methods    = get_option( 'ebioro_payment_methods', array('usdc' ) );

		// Load icon for each available payment method.
		foreach ( $methods as $m ) {
			$path = realpath( $image_path . '/' . $m . '.png' );
			if ( $path && dirname( $path ) === $image_path && is_file( $path ) ) {
				$url        = WC_HTTPS::force_https_url( plugins_url( '/assets/images/' . $m . '.png', __FILE__ ) );
				$icon_html .= '<img width="26" src="' . esc_attr( $url ) . '" alt="' . esc_attr__( $m, 'ebioro' ) . '" />';
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
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Ebioro Payments', 'ebioro'),
                'default' => 'no',
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                'default' => __('Bitcoin and other cryptocurrencies', 'ebioro'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce'),
                'type' => 'text',
                'desc_tip' => true,
                'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
                'default' => __( 'Pay with Bitcoin or other cryptocurrencies.', 'ebioro' ),
            ),
            'api_key' => array(
                'title' => __('API Key', 'ebioro'),
                'type' => 'text',
                'description' => sprintf(__('Get your API Key from the Ebioro Settings page.', 'ebioro')),
            ),
            'api_secret' => array(
                'title' => __('API Secret', 'ebioro'),
                'type' => 'text',
                'description' => sprintf(__('Get your API Secret from the Ebioro Settings page.', 'ebioro'))
            ),
			'debug'          => array(
				'title' => __('Debug log', 'woocommerce'),
				'type' => 'checkbox',
				'label' => __('Enable logging', 'woocommerce'),
				'default' => 'no',
				'description' => sprintf(__('Log Ebioro API events inside %s', 'ebioro'), '<code>' . \WC_Log_Handler_File::get_log_file_path('ebioro') . '</code>'),
			),
		);
	}

	/**
	 * Process the payment and return the result.
	 * 
	 * @param  int $order_id
	 * @return array
	 */
	public function process_payment( \WC_Order $order, \WP_REST_Request $request ) {
		
		$context = new PaymentContext();
		$result  = new PaymentResult();
		//$order = wc_get_order( $order_id );

		// Create description for charge based on order's products. Ex: 1 x Product1, 2 x Product2
		try {
			$order_items = array_map( function( $item ) {
				return $item['quantity'] . ' x ' . $item['name'];
			}, $order->get_items() );

			$description = mb_substr( implode( ', ', $order_items ), 0, 200 );
		} catch ( Exception $e ) {
			$description = null;
		}

		$this->init_api();

		// Create a new charge.
		$metadata = array(
			'order_id'  => $order->get_id(),
			'order_key' => $order->get_order_key(),
			'source' => 'woocommerce'
		);
		$result   = Ebioro_API_Handler::create_payment(
			$order->get_total(), get_woocommerce_currency(), $metadata,
			$this->get_return_url( $order ), null, $description,
			$this->get_cancel_url( $order )
		);

		if ( ! $result[0] ) {
			return array( 'result' => 'fail' );
		}

		$payment = $result[1]['data'];

		$order->update_meta_data( '_ebioro_payment_id', $payment['id'] );
		$order->save();

		return array(
			'result'   => 'success',
			'redirect' => $payment['hosted_url'],
		);
	}

	/**
	 * Get the cancel url.
	 *
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	public function get_cancel_url( $order ) {
		$return_url = $order->get_cancel_order_url();

		if ( is_ssl() || get_option( 'woocommerce_force_ssl_checkout' ) == 'yes' ) {
			$return_url = str_replace( 'http:', 'https:', $return_url );
		}

		/** DOCBLOCK - Makes linter happy.
		 * 
		 * @since today
		*/
		return apply_filters( 'woocommerce_get_cancel_url', $return_url, $order );
	}

	/**
	 * Check payment statuses on orders and update order statuses.
	 */
	public function check_orders() {
		$this->init_api();

		// Check the status of non-archived Ebioro orders.
		$orders = wc_get_orders(
			array(
				'ebioro_archived' => false,
				'status' => array( 'wc-pending' ),
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

		foreach ( $orders as $order ) {
			$payment_id = $order->get_meta( '_ebioro_payment_id' );

			usleep( 300000 );  // Ensure we don't hit the rate limit.
			$result = Ebioro_API_Handler::send_request( 'payments/' . $payment_id );

			if ( ! $result[0] ) {
				self::log( 'Failed to fetch order updates for: ' . $order->get_id() );
				continue;
			}

			$timeline = $result[1]['data']['timeline'];
			self::log( 'Timeline: ' . print_r( $timeline, true ) );
			$this->_update_order_status( $order, $timeline );
		}
	}

	/**
	 * Handle requests sent to webhook.
	 */
	public function handle_webhook() {
		$payload = file_get_contents( 'php://input' );
		if ( ! empty( $payload ) && $this->validate_webhook( $payload ) ) {
			$data       = json_decode( $payload, true );
			$event_data = $data['event']['data'];

			self::log( 'Webhook received event: ' . print_r( $data, true ) );

			if ( ! isset( $event_data['metadata']['order_id'] ) ) {
				// Probably a charge not created by us.
				exit;
			}

			$order_id = $event_data['metadata']['order_id'];

			$this->_update_order_status( wc_get_order( $order_id ), $event_data['timeline'] );

			exit;  // 200 response for acknowledgement.
		}

		wp_die( 'Ebioro Webhook Request Failure', 'Ebioro Webhook', array( 'response' => 500 ) );
	}

	/**
	 * Check Ebioro webhook request is valid.
	 * 
	 * @param  string $payload
	 */
	public function validate_webhook( $payload ) {
		self::log( 'Checking Webhook response is valid' );

		if ( ! isset( $_SERVER['HTTP_X_EB_WEBHOOK_SIGNATURE'] ) ) {
			return false;
		}

		$sig = $_SERVER['HTTP_X_EB_WEBHOOK_SIGNATURE'];

		$api_secret = $this->get_option( 'api_secret' );

		$sig2 = hash_hmac( 'sha256', $payload, $api_secret );

		if ( $sig === $sig2 ) {
			return true;
		}

		return false;
	}

	/*
	* Init the API class and set the API key etc.
	*/
   protected function init_api() {
	   include_once dirname(__FILE__) . '/includes/class-ebioro-api-handler.php';

	   Ebioro_API_Handler::$log     = get_class($this) . '::log';
	   Ebioro_API_Handler::$api_key = $this->get_option('api_key');
	   Ebioro_API_Handler::$api_key = $this->get_option('api_secret');
   }

	/**
	 * Update the status of an order from a given timeline.
	 * 
	 * @param  WC_Order $order
	 * @param  array    $timeline
	 */
	public function _update_order_status( $order, $timeline ) {
		$prev_status = $order->get_meta( '_ebioro_status' );

		$last_update = end( $timeline );
		$status      = $last_update['status'];
		if ( $status !== $prev_status ) {
			$order->update_meta_data( '_ebioro_status', $status );

			if ( 'EXPIRED' === $status && 'pending' == $order->get_status() ) {
				$order->update_status( 'cancelled', __( 'Ebioro payment expired.', 'ebioro' ) );
			} elseif ( 'CANCELED' === $status ) {
				$order->update_status( 'cancelled', __( 'Ebioro payment cancelled.', 'ebioro' ) );
			} elseif ( 'UNRESOLVED' === $status ) {
				if ('OVERPAID' === $last_update['context']) {
					$order->update_status( 'processing', __( 'Ebioro payment was successfully processed.', 'ebioro' ) );
					$order->payment_complete();
				} else {
					// translators: Ebioro error status for "unresolved" payment. Includes error status.
					$order->update_status( 'failed', sprintf( __( 'Ebioro payment unresolved, reason: %s.', 'ebioro' ), $last_update['context'] ) );
				}
			} elseif ( 'PENDING' === $status ) {
				$order->update_status( 'blockchainpending', __( 'Ebioro payment detected, but awaiting blockchain confirmation.', 'ebioro' ) );
			} elseif ( 'RESOLVED' === $status ) {
				// We don't know the resolution, so don't change order status.
				$order->add_order_note( __( 'Ebioro payment marked as resolved.', 'ebioro' ) );
			} elseif ( 'COMPLETED' === $status ) {
				$order->update_status( 'processing', __( 'Ebioro payment was successfully processed.', 'ebioro' ) );
				$order->payment_complete();
			}
		}

		// Archive if in a resolved state and idle more than timeout.
		if ( in_array( $status, array( 'EXPIRED', 'COMPLETED', 'RESOLVED' ), true ) &&
			$order->get_date_modified() < $this->timeout ) {
			self::log( 'Archiving order: ' . $order->get_order_number() );
			$order->update_meta_data( '_ebioro_archived', true );
		}
	}

	/**
	 * Handle a custom 'ebioro_archived' query var to get orders
	 * payed through Ebioro with the '_ebioro_archived' meta.
	 * 
	 * @param array $query - Args for WP_Query.
	 * @param array $query_vars - Query vars from WC_Order_Query.
	 * @return array modified $query
	 */
	public function _custom_query_var( $query, $query_vars ) {
		if ( array_key_exists( 'ebioro_archived', $query_vars ) ) {
			$query['meta_query'][] = array(
				'key'     => '_ebioro_archived',
				'compare' => $query_vars['ebioro_archived'] ? 'EXISTS' : 'NOT EXISTS',
			);
			// Limit only to orders payed through Ebioro.
			$query['meta_query'][] = array(
				'key'     => '_ebioro_payment_id',
				'compare' => 'EXISTS',
			);
		}

		return $query;
	}
}
