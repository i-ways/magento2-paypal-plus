<?php

namespace Iways\PayPalPlus\Compatibility;

if (!\interface_exists('\\Magento\\Framework\\App\\CsrfAwareActionInterface')) {
    interface CsrfAwareActionInterface {}
} else {
    interface CsrfAwareActionInterface extends \Magento\Framework\App\CsrfAwareActionInterface { }
}