let testMode				= document.getElementById('woocommerce_ebioro_test_mode');
let testApiKey				= document.getElementById('woocommerce_ebioro_test_api_key');
let testApiKeyWrapper		= testApiKey.closest('tr');
let testApiSecret			= document.getElementById('woocommerce_ebioro_test_api_secret');
let testApiSecretWrapper	= testApiSecret.closest('tr');

if ( testMode.checked === true ){
	testApiKeyWrapper.setAttribute('style','display:inline-flex')
	testApiSecretWrapper.setAttribute('style','display:inline-flex')
} else {
	testApiKeyWrapper.setAttribute('style','display:none')
	testApiSecretWrapper.setAttribute('style','display:none')
}

testMode.addEventListener('change', function(){
	if ( this.checked === true ){
		testApiKeyWrapper.setAttribute('style','display:inline-flex')
		testApiSecretWrapper.setAttribute('style','display:inline-flex')
	} else {
		testApiKeyWrapper.setAttribute('style','display:none')
		testApiSecretWrapper.setAttribute('style','display:none')
	}
})
