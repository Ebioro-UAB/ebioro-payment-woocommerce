<?php
/**
 * Plugin Name: Ebioro for WooCommerce
 * Description: A payment gateway that allows your customers to pay with cryptocurrency via Ebioro.
 * Author: Ebioro UAB
 * Author URI: https://www.ebioro.com/
 * Version: 1.1.0
 * Text domain: ebioro-for-woocommerce
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
        add_filter( 'plugin_action_links_ebioro-payment-woocommerce/ebioro-payment-commerce.php', 'eb_add_setting_link' );
        add_filter( 'network_admin_plugin_action_links_ebioro-payment-woocommerce/ebioro-payment-commerce.php', 'eb_add_setting_link' );
        add_action( 'admin_enqueue_scripts', 'eb_enqueue' );
        //add_filter('woocommerce_email_order_meta_fields', 'eb_custom_woocommerce_email_order_meta_fields', 10, 3);
        // add_filter('woocommerce_email_actions', 'eb_register_email_action');
        // add_action('woocommerce_email', 'eb_add_email_triggers');
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
        'settings' => '<a href="'. add_query_arg( $args, admin_url( 'admin.php' ) ) .'">'. __( 'Settings', 'ebioro' ) .'</a>'
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
 * We need to check how the block integration works. Below some links.
 */

// Mollie: https://github.com/mollie/WooCommerce/blob/eacc3c48ca529c680a4681b736ada3b0e0a3edc2/src/Assets/MollieCheckoutBlocksSupport.php
// Stripe https://github.com/woocommerce/woocommerce-gateway-stripe/blob/58e2f12d9beb718d7e4eb4f4a44e361d6b8b9691/includes/class-wc-stripe-blocks-support.php

// block support documentation from WooCommerce here https://developer.woo.com/2022/07/07/exposing-payment-options-in-the-checkout-block/ and here 

// add_action( 'woocommerce_blocks_loaded', 'woocommerce_gateway_ebioro_woocommerce_block_support' );

// function woocommerce_gateway_ebioro_woocommerce_block_support() {
// 	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
//         require_once dirname(__FILE__) . '/includes/class-wc-ebioro-blocks-support.php';
	
// 		add_action(
// 			'woocommerce_blocks_payment_method_type_registration',
// 			function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {

// 				$container = Automattic\WooCommerce\Blocks\Package::container();
// 				// registers as shared instance.
// 				$container->register(
// 					WC_Gateway_Ebioro_Blocks_Support::class,
// 					function() {
// 						if ( class_exists( 'WC_Gateway_Ebioro' ) ) {
// 							return new WC_Gateway_Ebioro_Blocks_Support( WC_Gateway_Ebioro::get_instance()->payment_request_configuration );
// 						} else {
// 							return new WC_Gateway_Ebioro_Blocks_Support();
// 						}
// 					}
// 				);
// 				$payment_method_registry->register(
// 					$container->get( WC_Gateway_Ebioro_Blocks_Support::class )
// 				);
// 			},
// 			5
// 		);
// 	}
// }

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
        'label'                     => __('Ebioro Settlement Pending', 'ebioro'),
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
            $new_statuses_arr['wc-ebiorosettlementpending'] = __('Ebioro Settlement Pending', 'ebioro');
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
 * Add Ebioro meta to WC emails
 *
 * @param array    $fields indexed list of existing additional fields.
 * @param bool     $sent_to_admin If should sent to admin.
 * @param WC_Order $order WC order instance
 *
 */
// function eb_custom_woocommerce_email_order_meta_fields($fields, $sent_to_admin, $order) {
//     if ($order->get_payment_method() == 'ebioro') {
//         $fields['ebioro_payments_reference'] = array(
//             'label' => __('Ebioro Payments Reference #'),
//             'value' => $order->get_meta('_ebioro_payment_id'),
//         );
//     }

//     return $fields;
// }

// /**
//  * Registers "woocommerce_order_status_blockchainpending_to_processing" as a WooCommerce email action.
//  *
//  * @param array $email_actions
//  *
//  * @return array
//  */
// function eb_register_email_action($email_actions) {
//     $email_actions[] = 'woocommerce_order_status_blockchainpending_to_processing';

//     return $email_actions;
// }

/**
 * Adds new triggers for emails sent when the order status transitions to Processing.
 *
 * @param WC_Emails $wc_emails
 */
// function eb_add_email_triggers($wc_emails) {
//     $emails = $wc_emails->get_emails();

//     /**
//      * A list of WooCommerce emails sent when the order status transitions to Processing.
//      *
//      * Developers can use the `eb_processing_order_emails` filter to add in their own emails.
//      *
//      * @param array $emails List of email class names.
//      *
//      * @return array
//      * 
//      * @since today
//      */
//     $processing_order_emails = apply_filters('eb_processing_order_emails', [
//         'WC_Email_New_Order',
//         'WC_Email_Customer_Processing_Order',
//     ]);

//     foreach ($processing_order_emails as $email_class) {
//         if (isset($emails[$email_class])) {
//             $email = $emails[$email_class];

//             add_action(
//                 'woocommerce_order_status_blockchainpending_to_processing_notification',
//                 array($email, 'trigger')
//             );
//         }
//     }
// }
