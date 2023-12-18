<?php
/**
 * Plugin Name: Ebioro for WooCommerce
 * Description: A payment gateway that allows your customers to pay with cryptocurrency via Ebioro.
 * Author: Ebioro UAB
 * Author URI: https://www.ebioro.com/
 * Version: 1.0.7
 * Text domain: ebioro-payment-woocommerce
 * Domain Path: /languages
 * WC tested up to: 8.2.1
 */

 if ( ! defined( 'ABSPATH' ) ) {
	exit;
 }

/**
 * Initialize Ebioro gateway and related WooCommerce features.
 */
function eb_init_gateway() {

	if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
		require_once plugin_dir_path(__FILE__) . 'class-wc-gateway-ebioro.php';
		add_action('init', 'eb_wc_register_settlement_status');
		add_filter('woocommerce_valid_order_statuses_for_payment', 'eb_wc_status_valid_for_payment', 10, 2);
		add_action('eb_check_orders', 'eb_wc_check_orders');
		add_filter('woocommerce_payment_gateways', 'eb_wc_add_ebioro_class');
		add_filter('wc_order_statuses', 'eb_wc_add_status');
		add_action('woocommerce_admin_order_data_after_order_details', 'eb_order_meta_general');
		add_action('woocommerce_order_details_after_order_table', 'eb_order_meta_general');
		add_filter( 'plugin_action_links_ebioro-payment-woocommerce/ebioro-payment-woocommerce.php', 'eb_add_setting_link' );
		add_filter( 'network_admin_plugin_action_links_ebioro-payment-woocommerce/ebioro-payment-woocommerce.php', 'eb_add_setting_link' );
		add_action( 'admin_enqueue_scripts', 'eb_enqueue' );
	}
}
add_action('plugins_loaded', 'eb_init_gateway');

// Setup cron job.
function eb_activation() {
	if (!wp_next_scheduled('eb_check_orders')) {
		wp_schedule_event(time(), 'hourly', 'eb_check_orders');
	}
}
register_activation_hook(__FILE__, 'eb_activation');

function eb_deactivation() {
	wp_clear_scheduled_hook('eb_check_orders');
}
register_deactivation_hook(__FILE__, 'eb_deactivation');

/**
 * Add Settings link in plugin list page
 */
function eb_add_setting_link( $actions ){
	$args = array(
		'page'      => 'wc-settings',
		'tab'       => 'checkout',
		'section'   => 'ebioro'
	);
	return array_merge( array(
		'settings' => '<a href="'. add_query_arg( $args, admin_url( 'admin.php' ) ) .'">'. __( 'Settings', 'ebioro-payment-woocommerce' ) .'</a>'
	), $actions );
}

/**
 * Enqueue scripts
 */
function eb_enqueue(){
	wp_register_script( 'ebioro-payment', plugin_dir_url( __FILE__ ) .'assets/js/admin/payment.js', null, null, true );
	wp_enqueue_script( 'ebioro-payment' );
}

/**
 * The Blocks support
 */
function eb_blocks_support(){
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		require_once 'includes/blocks/class-wc-gateway-ebioro-blocks-support.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				$payment_method_registry->register( new WC_Gateway_Ebioro_Blocks_Support() );
			}
		);
	}
}
add_action( 'woocommerce_blocks_loaded', 'eb_blocks_support' );

add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);


// WooCommerce

function eb_wc_add_ebioro_class($methods) {
	$methods[] = 'WC_Gateway_Ebioro';
	return $methods;
}

function eb_wc_check_orders() {
	$gateway = WC()->payment_gateways()->payment_gateways()['ebioro'];
	return $gateway->check_orders();
}

/**
 * Register new status with ID "wc-ebiorosettlementpending" and label "Ebioro Settlement Pending"
 */
function eb_wc_register_settlement_status() {
	register_post_status('wc-ebiorosettlementpending', array(
		'label'                     => __('Ebioro Settlement Pending', 'ebioro-payment-woocommerce'),
		'public'                    => true,
		'show_in_admin_status_list' => true,
		/* translators: WooCommerce order count in blockchain pending. */
		'label_count'               => _n_noop('Ebioro Settlement Pending <span class="count">(%s)</span>', 'Ebioro Settlement Pending <span class="count">(%s)</span>'),
	));
}

/**
 * Register wc-ebiorosettlementpending status as valid for payment.
 */
function eb_wc_status_valid_for_payment($statuses, $order) {
	$statuses[] = 'wc-ebiorosettlementpending';
	return $statuses;
}

/**
 * Add registered status to list of WC Order statuses
 *
 * @param array $wc_statuses_arr Array of all order statuses on the website.
 */
function eb_wc_add_status($wc_statuses_arr) {
	$new_statuses_arr = array();

	// Add new order status after payment pending.
	foreach ($wc_statuses_arr as $id => $label) {
		$new_statuses_arr[$id] = $label;

		if ('wc-pending' === $id) {  // after "Payment Pending" status.
			$new_statuses_arr['wc-ebiorosettlementpending'] = __('Ebioro Settlement Pending', 'ebioro-payment-woocommerce');
		}
	}

	return $new_statuses_arr;
}

/**
 * Add order Ebioro meta after General and before Billing
 *
 * @param WC_Order $order WC order instance
 */
function eb_order_meta_general($order) {
	if ($order->get_payment_method() == 'ebioro') {
		?>

		<br class="clear"/>
		<h3>Ebioro Payments Data</h3>
		<div class="">
			<p>Ebioro Payments Reference # <?php echo esc_html($order->get_meta('_ebioro_payment_id')); ?></p>
		</div>

		<?php
	}
}

/**
 * Add admin screen notice if API keys are not set
 */
function eb_admin_notice_api_keys(){
	$data = get_option( 'woocommerce_ebioro_settings' );
	$api_key 	= $data['api_key'];
	$api_secret	= $data['api_secret'];

	if ( $api_key && $api_secret )
		return;

	$plugin = get_plugin_data( __FILE__ );
	$args = array(
		'page'      => 'wc-settings',
		'tab'       => 'checkout',
		'section'   => 'ebioro'
	);
?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				__( '%s. API keys missing. <a href="%s">Please, set your API keys here</a>.', 'ebioro-payment-woocommerce' ),
				$plugin['Name'],
				add_query_arg( $args, admin_url( 'admin.php' ) )
			) ?>
		</p>
	</div>
<?php
}
add_action( 'admin_notices', 'eb_admin_notice_api_keys' );

/**
 * Add admin screen notice if Test Mode is active
 */
function eb_admin_notice_test_mode(){
	$data = get_option( 'woocommerce_ebioro_settings' );
	$test_mode 	= $data['test_mode'];

	if ( 'no' == $test_mode )
		return;

	$plugin = get_plugin_data( __FILE__ );
	$args = array(
		'page'      => 'wc-settings',
		'tab'       => 'checkout',
		'section'   => 'ebioro'
	);
?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				__( '%s. The test mode is active, <a href="%s">disable it</a> before deploying into production.', 'ebioro-payment-woocommerce' ),
				$plugin['Name'],
				add_query_arg( $args, admin_url( 'admin.php' ) )
			) ?>
		</p>
	</div>
<?php
}
add_action( 'admin_notices', 'eb_admin_notice_test_mode' );

/**
 * Admin screen notices if currency is not in supported list
 */
function eb_admin_notice_currency(){
	$currencies = array(
		'USD',
		'EUR',
		'CAD',
		'GBP'
	);
	if ( in_array( get_woocommerce_currency(), $currencies ) )
		return;

	$args = array(
		'page'      => 'wc-settings',
		'tab'       => 'general#woocommerce_currency'
	);

	$plugin = get_plugin_data( __FILE__ );
?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				__( '%s. Your currency is <strong>%s</strong>, Ebioro wallet uses <strong>%s</strong>. <a href="%s">Change it to USD</a> to connect properly with our wallet.', 'ebioro-payment-woocommerce' ),
				$plugin['Name'],
				get_woocommerce_currency(),
				join( ', ', $currencies ),
				add_query_arg( $args, admin_url( 'admin.php' ) )
			) ?>
		</p>
	</div>
<?php
}
add_action( 'admin_notices', 'eb_admin_notice_currency' );
