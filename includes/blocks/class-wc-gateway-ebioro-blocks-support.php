<?php
/**
 * Class for handling the Ebioro payment gateway blocks support.
 *
 * This file contains the class that integrates the Ebioro payment gateway with
 * the WordPress blocks editor, providing block-based features for payment methods.
 *
 * @package   ebioro-payment-woocommerce
 * @subpackage GatewayBlocks
 * @since     1.1.2
 * @author    Ebioro UAB
 * @link      https://www.ebioro.com/
 * @license   GPL-3.0+
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Ebioro Payments Blocks integration
 *
 * @since 1.0.0
 */
final class Ebioro_Gateway_Blocks_Support extends AbstractPaymentMethodType {

	/**
	 * The gateway instance.
	 *
	 * @var Ebioro_Payment_Gateway The gateway instance.
	 */
	private $gateway;


	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = 'ebioro';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_ebioro_settings', array() );
		$this->gateway  = new Ebioro_Payment_Gateway();
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$script_path       = '/assets/js/frontend/blocks.js';
		$script_asset_path = Ebioro_Payment_Gateway::plugin_abspath() . 'assets/js/frontend/blocks.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require( $script_asset_path )
			: array(
				'dependencies' => array(),
				'version'      => '1.0.0',
			);
		$script_url        = Ebioro_Payment_Gateway::plugin_url() . $script_path;

		// Ship exactly one bundle. blocks.js and blocks.min.js were the same webpack
		// output; enqueuing both registered the 'ebioro' payment method twice. The
		// webpack build (blocks.js, with its generated blocks.asset.php deps/version)
		// is the canonical one.
		wp_register_script(
			'wc-ebioro-payments-blocks',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'wc-ebioro-payments-blocks', 'ebioro-payment-woocommerce', Ebioro_Payment_Gateway::plugin_abspath() . 'languages/' );
		}

		return array( 'wc-ebioro-payments-blocks' );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return array(
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'supports'    => array_filter( $this->gateway->supports, array( $this->gateway, 'supports' ) ),
		);
	}
}
