/**
 * External dependencies
 */
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { __ } from '@wordpress/i18n';
import { getPaymentMethodData } from '@woocommerce/settings';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME } from './constants';

// Fetch payment method data for your custom payment method (replace 'ebioro' with your actual payment method ID)
const settings = getPaymentMethodData('ebioro', {});
const defaultLabel = __('Ebioro Payments', 'woo-gutenberg-products-block');
const label = decodeEntities(settings?.title || '') || defaultLabel;

/**
 * Content component
 */
const Content = () => {
    return decodeEntities(settings.description || '');
};

/**
 * Label component
 *
 * @param {*} props Props from payment API.
 */
const Label = (props) => {
    const { PaymentMethodLabel } = props.components;
    return <PaymentMethodLabel text={label} />;
};

/**
 * Payment config method object.
 */
const Ebioro = {
    name: PAYMENT_METHOD_NAME,
    label: <Label />,
    content: <Content />,
    edit: <Content />,
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings?.supports || [],
    },
};

// Register payment method
registerPaymentMethod(Ebioro);
