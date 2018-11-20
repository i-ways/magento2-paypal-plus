/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * Author Robert Hillebrand - hillebrand@i-ways.de - i-ways sales solutions GmbH
 * Copyright i-ways sales solutions GmbH © 2015. All Rights Reserved.
 * License http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

var config = {
    map   : {
        '*' : {
            paypalplus: '//www.paypalobjects.com/webstatic/ppplus/ppplus.min.js',
        },
        'Magento_Checkout/js/model/payment-service': {
            'Magento_Checkout/js/model/payment/method-list': 'Iways_PayPalPlus/js/model/payment/method-list'
        }
    },
    config: {
        mixins: {
            'Magento_Checkout/js/model/payment-service': {
                'Iways_PayPalPlus/js/model/payment-service': true
            }
        }
    }
};
