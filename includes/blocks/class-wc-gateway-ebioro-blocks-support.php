<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Ebioro Payments Blocks integration
 *
 * @since 1.0.0
 */
final class WC_Gateway_Ebioro_Blocks_Support extends AbstractPaymentMethodType {

    /**
     * The gateway instance.
     *
     * @var WC_Gateway_Ebioro
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
        $this->settings = get_option( 'woocommerce_ebioro_settings', [] );
        $this->gateway = new WC_Gateway_Ebioro();
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
        $script_asset_path = WC_Gateway_Ebioro::plugin_abspath() . 'assets/js/frontend/blocks.asset.php';
        $script_asset      = file_exists($script_asset_path)
            ? require($script_asset_path)
            : ['dependencies' => [], 'version' => '1.0.0'];
        $script_url        = WC_Gateway_Ebioro::plugin_url() . $script_path;

        wp_register_script(
            'wc-ebioro-payments-blocks',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        // Enqueue your custom blocks.js file
        wp_enqueue_script(
            'wc-ebioro-custom-blocks',
            WC_Gateway_Ebioro::plugin_url() . '/assets/js/frontend/blocks.js',  // Adjust the path to your custom JS file
            ['wc-ebioro-payments-blocks'],  // Dependencies
            '1.0.0',  // Version
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('wc-ebioro-payments-blocks', 'woocommerce-gateway-ebioro', WC_Gateway_Ebioro::plugin_abspath() . 'languages/');
        }

        return ['wc-ebioro-payments-blocks', 'wc-ebioro-custom-blocks'];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data() {
        return [
            'title'       => $this->get_setting('title'),
            'description' => $this->get_setting('description'),
            'supports'    => array_filter($this->gateway->supports, [$this->gateway, 'supports'])
        ];
    }
}
