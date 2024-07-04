=== Ebioro Payment Gateway for WooCommerce ===
Contributors: ebioro
Tags: woocommerce, crypto, cryptocurrency, stablecoins, USDC, payments, payment gateway, payment processing, digital currencies
Requires at least: 5.0
Tested up to: 8.2.1
Requires PHP: 7.2
Stable tag: 1.1.0
License: GPL-3.0+
License URI: https://www.gnu.org/licenses/gpl-3.0.html

The most secure and fastest way to accept stablecoin payments with USDC.

== Description ==

Accept stablecoin payments through Ebioro Payment Gateway for WooCommerce. This plugin integrates Ebioro as a payment option, allowing customers to pay with USDC directly from your WooCommerce store.

= Key features =

* Accept USDC payments from your customers.
* Price in your local currency.
* Get settled via USDC.
* No chargebacks.
* View all incoming payments and manage refunds via your Ebioro merchant dashboard.

= Customer journey =

1. The customer adds items to their shopping cart and proceeds to checkout.
2. The customer selects Ebioro as the payment method.
3. A payment request is generated, and the customer completes the payment using their cryptocurrency wallet.
4. Once the transaction is fully confirmed, Ebioro notifies the merchant and the corresponding amount is credited to the Ebioro merchant account.

== Installation ==

= Requirements =

* This plugin requires [WooCommerce](https://wordpress.org/plugins/woocommerce/).
* An Ebioro merchant account ([Sign up here](https://enterprise.ebioro.com/))

= Plugin installation =

1. Download the Ebioro Payment Gateway plugin.
2. Upload the entire `ebioro-payment-woocommerce` folder to the `/wp-content/plugins/` directory.
3. Activate the plugin through the **Plugins** screen in WordPress.
4. Go to **WooCommerce > Settings** and click on the **Payments** tab.
5. On the **Payments** tab, find Ebioro Payment and click the **Manage** button.

= Plugin configuration =

1. Get your API key and secret from your Ebioro merchant account.
2. Enter the API key and secret into the Ebioro settings page.
3. Enable Ebioro payments.
4. Completed payments will automatically update WooCommerce orders to 'processing'.
5. Manage all payments in your [Ebioro merchant account](https://enterprise.ebioro.com/).

== Frequently Asked Questions ==

= How do I configure the Ebioro Payment Gateway? =
Navigate to WooCommerce > Settings > Payments, and click on "Ebioro" to configure the gateway settings by entering your API key and secret.

= Is there any requirement for using this plugin? =
You need to have an active Ebioro Merchant account and WooCommerce installed and activated on your WordPress site.

= What stablecoins does the plugin support? =
The initial version supports USDC. More stablecoins will be supported in future updates.

= Is it possible to use this plugin on a sandbox/test environment? =
Yes, just reach out to our support team at support@ebioro.com to get a test account. You will then receive test API keys that you can use to configure this plugin by clicking on 'Enable test mode'. Please do not forget to disable the test mode before moving to production!


== Screenshots ==

1. Payment Gateway Settings - Configure your Ebioro account and other settings.
2. Checkout Page - Ebioro payment payment page.
3. Merchant Dashboard - Overview of payments and transactions in the Ebioro dashboard.

== Changelog ==

= 1.1.0 =
* Initial release of Ebioro Payment Gateway for WooCommerce.

== Upgrade Notice ==

= 1.1.0 =
Initial release.

== License ==
This plugin is distributed under the GPL-3.0+ license. For more information, please see [https://www.gnu.org/licenses/gpl-3.0.html](https://www.gnu.org/licenses/gpl-3.0.html).
