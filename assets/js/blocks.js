// import { registerPaymentMethod } from '@woocommerce/blocks-registry';
// import { getSetting } from '@woocommerce/settings';


document.addEventListener("DOMContentLoaded", function () {
	const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
	const { getSetting } = window.wc.wcSettings;
	const createElement = window.wp.element.createElement;


	const NCGatewayLabel = getSetting('netcash_paynow', 'Netcash Pay Now');

	const NCPayNowPaymentMethod = {
		name: 'paynow',
		gatewayId: 'paynow',
		label: NCGatewayLabel,
		ariaLabel: NCGatewayLabel,
		content: createElement('p', null, 'Pay securely with Netcash Pay Now.'),
		edit: createElement('p', null, 'Netcash Pay Now settings.'),
		canMakePayment: () => true, // Implement actual logic
		placeOrder: () => {}, // Implement actual order processing logic
	};

	registerPaymentMethod(NCPayNowPaymentMethod);
});

