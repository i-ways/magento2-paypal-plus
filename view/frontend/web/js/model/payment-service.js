/*browser:true*/
/*global define, window*/
define(
    [
        'underscore',
        'mage/utils/wrapper',
        'Magento_Checkout/js/model/payment/method-list',
        'Iways_PayPalPlus/js/model/payment/method-list'
    ], function (_, wrapper, methodList, methodListWrapper) {
        'use strict';

        var paypalplusConfig = (((window.checkoutConfig || {}).payment || {}).iways_paypalplus_payment || {}),
            pppMethodName    = 'iways_paypalplus_payment';

        /**
         * inject thirdPartyMethods as valid, so they not set to null after e.g. address validation
         */
        return function (paymentService) {
            paymentService.setPaymentMethods = wrapper.wrap(paymentService.setPaymentMethods, function (parentMethod, methods) {

                var thirdPartyMethods = [],
                    isPPP = false;
                // manipulate list, that third party methods are valid after ajax reload (e.g. custom OSC)
                if (!_.isUndefined(paypalplusConfig) && !_.isEmpty(paypalplusConfig.thirdPartyPaymentMethods)) {
                    // check, if PPP is active in payment list, then allow third party methods
                    if (_.findWhere(methods, {method: pppMethodName})) {
                      _.each(paypalplusConfig.thirdPartyPaymentMethods, function (thirdParty, name) {
                            isPPP = true;
                            thirdPartyMethods.push(name);
                            methods.push({
                                method: name,
                                title : thirdParty.methodName
                            });
                        });
                    }
                }

                // use wrapper to prevent rendering third party methods
                methodListWrapper(methodList());

                // call original method
                parentMethod(methods);

                if (isPPP) {
                    // after checking, disable third party payments
                    methodListWrapper(_.filter(methodListWrapper(), function (paymentMethod) {
                        return thirdPartyMethods.indexOf(paymentMethod.method) < 0;
                    }));
                }

                // and populate list for subscribers
                methodList(methodListWrapper());
            });
            return paymentService;
        }
    });
