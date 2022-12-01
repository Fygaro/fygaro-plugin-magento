define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'fygaro',
                component: 'Fygaro_FygaroPayment/js/view/payment/method-renderer/fygaro'
            }
        );
        return Component.extend({});
    }
);
