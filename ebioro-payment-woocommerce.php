<?php
/**
 * Plugin Name: Ebioro for WooCommerce
 * Description: Stablecoin (USDC) Payment Processor - A payment gateway that allows your customers to pay with stablecoins via Ebioro.
 * Author: Ebioro UAB
 * Author URI: https://www.ebioro.com/
 * Version: 1.1.5
 * Text domain: ebioro-payment-woocommerce
 * Domain Path: /languages
 * License: GPL-3.0+
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * WC tested up to: 8.2.1

 * @package ebioro-payment-woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Initialize Ebioro gateway and related WooCommerce features.
 */
function ebioro_init_gateway() {

	if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
		require_once plugin_dir_path( __FILE__ ) . 'class-wc-gateway-ebioro.php';
		// add_action('init', 'ebioro_wc_register_settlement_status');
		// add_filter('woocommerce_valid_order_statuses_for_payment', 'ebioro_wc_status_valid_for_payment', 10, 2);
		// add_filter('wc_order_statuses', 'ebioro_wc_add_status');
		add_action( 'ebioro_check_orders', 'ebioro_wc_check_orders' );
		add_filter( 'woocommerce_payment_gateways', 'ebioro_wc_add_ebioro_class' );
		add_action( 'woocommerce_admin_order_data_after_order_details', 'ebioro_order_meta_general' );
		add_action( 'woocommerce_order_details_after_order_table', 'ebioro_order_meta_general' );
		add_filter( 'plugin_action_links_ebioro-payment-woocommerce/ebioro-payment-woocommerce.php', 'ebioro_add_setting_link' );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'ebioro_add_setting_link' );
		add_filter( 'network_admin_plugin_action_links_' . plugin_basename( __FILE__ ), 'ebioro_add_setting_link' );
		add_action( 'admin_enqueue_scripts', 'ebioro_enqueue' );
		// add_filter( 'network_admin_plugin_action_links_ebioro-payment-woocommerce/ebioro-payment-woocommerce.php', 'ebioro_add_setting_link' );
		// add_action( 'admin_enqueue_scripts', 'ebioro_enqueue' );
	}
}
add_action( 'plugins_loaded', 'ebioro_init_gateway' );

/**
 * Activates the custom event scheduling for 'ebioro_check_orders'.
 *
 * This function checks if the custom event 'ebioro_check_orders' is already scheduled.
 * If not, it schedules the event to run hourly.
 *
 * @return void
 */
function ebioro_activation() {
	if ( ! wp_next_scheduled( 'ebioro_check_orders' ) ) {
		wp_schedule_event( time(), 'hourly', 'ebioro_check_orders' );
	}
}
register_activation_hook( __FILE__, 'ebioro_activation' );

/**
 * Deactivates the scheduled event for 'ebioro_check_orders'.
 *
 * This function clears the scheduled hook 'ebioro_check_orders' when the plugin is deactivated.
 *
 * @return void
 */
function ebioro_deactivation() {
	wp_clear_scheduled_hook( 'ebioro_check_orders' );
}
register_deactivation_hook( __FILE__, 'ebioro_deactivation' );

/**
 * Add Settings link in the plugin list page.
 *
 * This function modifies the plugin actions by adding a custom Settings link.
 *
 * @param array $actions An array of existing plugin action links.
 * @return array Modified array of plugin action links.
 */
function ebioro_add_setting_link( $actions ) {
	$args = array(
		'page'      => 'wc-settings',
		'tab'       => 'checkout',
		'section'   => 'ebioro',
	);
	$settings_link = '<a href="' . esc_url( add_query_arg( $args, admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Ebioro settings', 'ebioro-payment-woocommerce' ) . '</a>';
	// return array_merge( array(
	// 	'settings' => '<a href="'. add_query_arg( $args, admin_url( 'admin.php' ) ) .'">'. __( 'Settings', 'ebioro-payment-woocommerce' ) .'</a>'
	// ), $actions );
	return array_merge( array( 'settings' => $settings_link ), $actions );
}

/**
 * Enqueue scripts
 */
function ebioro_enqueue() {
	wp_register_script( 'ebioro-payment', plugin_dir_url( __FILE__ ) . 'assets/js/admin/payment.min.js', null, '1.0.0', true );
	wp_enqueue_script( 'ebioro-payment' );
}

/**
 * The Blocks support
 */
function ebioro_blocks_support() {
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		require_once 'includes/blocks/class-wc-gateway-ebioro-blocks-support.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				$payment_method_registry->register( new Ebioro_Gateway_Blocks_Support() );
			}
		);
	}
}
add_action( 'woocommerce_blocks_loaded', 'ebioro_blocks_support' );

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);


// WooCommerce

function ebioro_wc_add_ebioro_class( $methods ) {
	$methods[] = 'Ebioro_Payment_Gateway';
	return $methods;
}

function ebioro_wc_check_orders() {
	$gateway = WC()->payment_gateways()->payment_gateways()['ebioro'];
	return $gateway->check_orders();
}

/**
 * Register new status with ID "wc-ebiorosettlementpending" and label "Ebioro Settlement Pending"
 */
// function ebioro_wc_register_settlement_status() {
// 	register_post_status( 'wc-ebiorosettlementpending', array(
// 		'label'                     => __( 'Ebioro Settlement Pending', 'ebioro-payment-woocommerce' ),
// 		'public'                    => true,
// 		'show_in_admin_status_list' => true,
// 		/* translators: WooCommerce order count in blockchain pending. */
// 		'label_count'               => _n_noop('Ebioro Settlement Pending <span class="count">(%s)</span>', 'Ebioro Settlement Pending <span class="count">(%s)</span>'),
// 	) );
// }

// /**
//  * Register wc-ebiorosettlementpending status as valid for payment.
//  */
// function ebioro_wc_status_valid_for_payment($statuses, $order) {
// 	$statuses[] = 'wc-ebiorosettlementpending';
// 	return $statuses;
// }

// /**
//  * Add registered status to list of WC Order statuses
//  *
//  * @param array $wc_statuses_arr Array of all order statuses on the website.
//  */
// function ebioro_wc_add_status($wc_statuses_arr) {
// 	$new_statuses_arr = array();

// 	// Add new order status after payment pending.
// 	foreach ($wc_statuses_arr as $id => $label) {
// 		$new_statuses_arr[$id] = $label;

// 		if ('wc-pending' === $id) {  // after "Payment Pending" status.
// 			$new_statuses_arr['wc-ebiorosettlementpending'] = __('Ebioro Settlement Pending', 'ebioro-payment-woocommerce');
// 		}
// 	}

// 	return $new_statuses_arr;
// }

/**
 * Add order Ebioro meta after General and before Billing.
 *
 * @param WC_Order $order WC order instance.
 */
function ebioro_order_meta_general( $order ) {
	if ( $order->get_payment_method() === 'ebioro' ) {
		?>
			<br class="clear"/>
			<h3><?php echo esc_html__( 'Ebioro Payments Data', 'ebioro-payment-woocommerce' ); ?></h3>
			<div class="">
				<p><?php echo esc_html__( 'Ebioro Payments Reference # ', 'ebioro-payment-woocommerce' ) . esc_html( $order->get_meta( '_ebioro_payment_id' ) ); ?></p>
			</div>
		<?php
	}
}

/**
 * Add admin screen notice if API keys are not set
 */
function ebioro_admin_notice_api_keys() {
	$data        = get_option( 'woocommerce_ebioro_settings' );
	$api_key     = $data['api_key'];
	$api_secret  = $data['api_secret'];

	if ( $api_key && $api_secret ) {
		return;
	}

	$plugin = get_plugin_data( __FILE__ );
	$args = array(
		'page'      => 'wc-settings',
		'tab'       => 'checkout',
		'section'   => 'ebioro',
	);
	?>
	<div class="notice notice-error">
		<p>
			<?php
				$allowed_html = array(
					'a' => array(
						'href' => array(),
						'title' => array(),
					),
				);

				// translators: %1$s is the name of the plugin, %2$s is the URL for setting API keys.
				echo wp_kses(
					sprintf(
						'%1$s. API keys missing. <a href="%2$s">Please, set your API keys here</a>.',
						esc_html( $plugin['Name'] ),
						esc_url( add_query_arg( $args, admin_url( 'admin.php' ) ) )
					),
					$allowed_html
				);

			?>
		</p>
	</div>

	<?php
}
add_action( 'admin_notices', 'ebioro_admin_notice_api_keys' );

/**
 * Add admin screen notice if Test Mode is active
 */
function ebioro_admin_notice_test_mode() {
	$data = get_option( 'woocommerce_ebioro_settings' );
	$test_mode = $data['test_mode'];

	if ( 'no' === $test_mode ) {
		return;
	}

	$plugin = get_plugin_data( __FILE__ );
	$args = array(
		'page' => 'wc-settings',
		'tab' => 'checkout',
		'section' => 'ebioro',
	);
	?>
	<div class="notice notice-error">
		<p>
			<?php

			$allowed_html = array(
				'a' => array(
					'href' => array(),
					'title' => array(),
				),
			);

			printf(
				wp_kses(
					// translators: %1$s is the name of the plugin, %2$s is the URL for disabling test mode.
					'%1$s. The test mode is active, <a href="%2$s">disable it</a> before deploying into production.',
					$allowed_html
				),
				esc_html( $plugin['Name'] ),
				esc_url( add_query_arg( $args, admin_url( 'admin.php' ) ) )
			);

			?>
		</p>
	</div>

	<?php
}
add_action( 'admin_notices', 'ebioro_admin_notice_test_mode' );

/**
 * Admin screen notices if currency is not in supported list
 */
function ebioro_admin_notice_currency() {
	$currencies = array(
		'USD',
		'EUR',
		'CAD',
		'GBP',
	);
	if ( in_array( get_woocommerce_currency(), $currencies ) ) {
		return;
	}

	$args = array(
		'page'      => 'wc-settings',
		'tab'       => 'general#woocommerce_currency',
	);

	$plugin = get_plugin_data( __FILE__ );
	?>
	<div class="notice notice-error">
		<p>
			<?php
				$allowed_html = array(
					'a' => array(
						'href' => array(),
						'title' => array(),
					),
					'strong' => array(),
				);

				printf(
					wp_kses(
						// translators: %1$s is the name of the plugin, %2$s is the currency used by the store, %3$s is the list of supported currencies, %4$s is the URL to change the currency.
						'%1$s. Your currency is <strong>%2$s</strong>, Ebioro wallet uses <strong>%3$s</strong>. <a href="%4$s">Change it to USD</a> to connect properly with our wallet.',
						$allowed_html
					),
					esc_html( $plugin['Name'] ),
					esc_html( get_woocommerce_currency() ),
					esc_html( join( ', ', $currencies ) ),
					esc_url( add_query_arg( $args, admin_url( 'admin.php' ) ) )
				);

			?>
		</p>
	</div>

	<?php
}
add_action( 'admin_notices', 'ebioro_admin_notice_currency' );
