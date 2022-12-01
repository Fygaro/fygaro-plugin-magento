define(
    [
        'Magento_Checkout/js/view/payment/default',
        'jquery',
        'mage/url',
        'Magento_Ui/js/model/messageList'
    ],
    function (
        Component,
        $,
        url,
        messageList
    )
    {
        'use strict';

        return Component.extend({
            redirectAfterPlaceOrder: false,
            defaults: {
                template: 'Fygaro_FygaroPayment/payment/fygaro'
            },

            afterPlaceOrder: function () {
                $.ajax({
                    url: url.build('fygaro/ajax/token'),
                    dataType: "json",
                    type: "get",
                    cache: true,
                    /** @param {Object} response */
                    success: function (response) {
                        window.location.replace(response.url);
                    },

                    /** set empty array if error occurs */
                    error: function (xhr, status, error) {
                        const err = eval("(" + xhr.responseText + ")");
                        messageList.addErrorMessage({
                            message: err.message
                        });
                        window.location.replace("checkout/onepage/failure");
                        // console.log(err.message);
                    },
                });
                // let baseUrl = url.build('frontname/regions/index');
                // window.location.replace(baseUrl+"checkout/onepage/failure");
                // window.location.replace(buttonUrl);
            }
        });
    }
);
