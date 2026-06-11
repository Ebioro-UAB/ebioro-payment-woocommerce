=== Ebioro for WooCommerce ===
Contributors: ebioro
Tags: woocommerce, crypto, cryptocurrency, payments, payment gateway
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.2
Stable tag: 1.2.0
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

= API Dependency =

This plugin relies on the **Ebioro API** for payment processing. By using this plugin, you are agreeing to the terms of service and privacy policy of Ebioro.

* [Ebioro API Documentation](https://merchant-api.ebioro.com)
* [Ebioro Terms of Use](https://www.ebioro.com/terms-of-service)
* [Ebioro Privacy Policy](https://www.ebioro.com/privacy)

= Customer journey =

1. The customer adds items to their shopping cart and proceeds to checkout.
2. The customer selects Ebioro as the payment method.
3. A payment request is generated, and the customer completes the payment using their cryptocurrency wallet.
4. Once the transaction is fully confirmed, Ebioro notifies the merchant and the corresponding amount is credited to the Ebioro merchant account.

== Development ==
To develop or modify the plugin, you can access the original unminified source files located in `/assets/js/admin` and `/assets/js/frontend`

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

== Third-Party Services ==

This plugin connects your store to the Ebioro payments platform, operated by Ebioro UAB (Vilnius, Lithuania), to process stablecoin payments:

* When a customer checks out, the plugin sends the order amount, currency, order reference, store name and product description to the Ebioro API to create the payment, and redirects the customer to the Ebioro payment page.
* Ebioro notifies your store of payment status changes via signed webhooks so order statuses update automatically.
* API endpoints used: https://merchant-api.ebioro.com (live) and https://test-merchant.ebioro.com (test mode).

An Ebioro merchant account is required. Service terms and data handling:

* Terms of Service: https://www.ebioro.com/terms-of-service
* Privacy Policy: https://www.ebioro.com/privacy

== Source Code ==

All JavaScript ships in human-readable form: the uncompressed sources for every minified file are included alongside them (`assets/js/admin/payment.js` next to `payment.min.js`, `assets/js/frontend/blocks.js` next to `blocks.min.js`). The minified builds are produced with webpack from the same sources. The complete development setup is maintained by Ebioro UAB and available on request via support@ebioro.com.

== Changelog ==

= 1.2.0 =
* Idempotency: payment creation now sends an Idempotency-Key header so a retry or double-submit replays the original payment instead of creating a duplicate charge.
* Checkout title now defaults to "Pay with crypto" (existing saved titles are unaffected).

= 1.1.5 =
* Documentation: added Third-Party Services disclosure (Ebioro API endpoints, terms, privacy policy).
* Documentation: added Source Code section documenting the uncompressed JavaScript sources shipped in assets/js/.
* Hardening: webhook signature header is now read individually instead of iterating the whole request header set.
* Fix: settings link on the Plugins page is now escaped with esc_url().

= 1.1.4 =
* Fix: webhook receiver was never executed (wrong WooCommerce wc-api hook name) — order statuses now update automatically on payment.
* Fix: removed invalid nonce requirement on server-to-server webhooks; HMAC signature remains the authentication.
* Fix: reliable signature header reading on nginx/FastCGI hosts; removed deprecated PHP filters (PHP 8.1+).
* Fix: orders complete on a late or manually resent 'paid' webhook (previously only the first delivery could complete an order).
* Fix: correct UTC timestamp in API request signatures (stores with non-UTC timezone).
* Hardening: clearer error responses (400/404/405), guards against malformed payloads and failed API responses.

= 1.1.1 =
* Initial release of Ebioro Payment Gateway for WooCommerce.

== Upgrade Notice ==

= 1.2.0 =
Prevents duplicate charges on checkout retries (Idempotency-Key); checkout title now defaults to "Pay with crypto".

= 1.1.1 =
Initial release.

= 1.1.2 =
Code optimization following Wordpress standards

= 1.1.3 =
Update: Stable tag bumped to 1.1.3

= 1.1.4 =
Important: fixes automatic order status updates via webhooks. All merchants should update.

= 1.1.5 =
Documentation and hardening release.

== License ==
This plugin is distributed under the GPL-3.0+ license. For more information, please see [https://www.gnu.org/licenses/gpl-3.0.html](https://www.gnu.org/licenses/gpl-3.0.html).

