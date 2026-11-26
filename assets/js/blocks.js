( function() {
	const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
	const { getSetting } = window.wc.wcSettings;
	const { createElement } = window.wp.element;
	const { decodeEntities } = window.wp.htmlEntities;
	const { __ } = window.wp.i18n;

	const settings = getSetting( 'paynow_data', {} );
	const defaultLabel = __( 'Netcash Pay Now', 'wc_paynow' );
	const label = decodeEntities( settings.title ) || defaultLabel;

	/**
	 * Content component for PayNow payment method
	 */
	const Content = () => {
		return createElement(
			'div',
			{ className: 'wc-paynow-payment-method-content' },
			decodeEntities( settings.description || '' )
		);
	};

	/**
	 * Label component for PayNow payment method
	 */
	const Label = () => {
		return createElement(
			'span',
			{ className: 'wc-paynow-payment-method-label' },
			label
		);
	};

	/**
	 * PayNow payment method configuration
	 */
	const PayNowPaymentMethod = {
		name: 'paynow',
		label: createElement( Label ),
		content: createElement( Content ),
		edit: createElement( Content ),
		canMakePayment: () => true,
		ariaLabel: label,
		supports: {
			features: settings.supports || [],
		},
	};

	registerPaymentMethod( PayNowPaymentMethod );
} )();

