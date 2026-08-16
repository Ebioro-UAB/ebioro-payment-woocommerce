const testMode        = document.getElementById('woocommerce_ebioro_test_mode');
const testApiKey      = document.getElementById('woocommerce_ebioro_test_api_key');
const testApiSecret   = document.getElementById('woocommerce_ebioro_test_api_secret');

// Bail out unless we're on the gateway settings screen where these fields exist.
if (testMode && testApiKey && testApiSecret) {
	const testApiKeyWrapper    = testApiKey.closest('tr');
	const testApiSecretWrapper = testApiSecret.closest('tr');

	const sync = () => {
		const display = testMode.checked ? 'inline-flex' : 'none';
		if (testApiKeyWrapper)    testApiKeyWrapper.style.display = display;
		if (testApiSecretWrapper) testApiSecretWrapper.style.display = display;
	};

	sync();
	testMode.addEventListener('change', sync);
}
