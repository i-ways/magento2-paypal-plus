<?php
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

namespace Iways\PayPalPlus\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\Method\Free;

class MethodList extends \Magento\Payment\Model\MethodList
{
    const AMAZON_PAYMENT = 'amazon_payment';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * Construct
     *
     * @param \Magento\Payment\Helper\Data $paymentHelper
     * @param \Magento\Payment\Model\Checks\SpecificationFactory $specificationFactory
     */
    public function __construct(
        \Magento\Payment\Helper\Data $paymentHelper,
        \Magento\Payment\Model\Checks\SpecificationFactory $specificationFactory,
        ScopeConfigInterface $scopeConfig

    ) {
        $this->scopeConfig = $scopeConfig;
        parent::__construct($paymentHelper, $specificationFactory);
    }

    /**
     * Get Available Methods
     *
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @param boolean $checkPPP
     * @return \Magento\Payment\Model\MethodInterface[]
     * @api
     */
    public function getAvailableMethods(\Magento\Quote\Api\Data\CartInterface $quote = null, $checkPPP = true)
    {

        $pppEnabled = $this->scopeConfig->getValue(
            'payment/iways_paypalplus_payment/active',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        if ($checkPPP) {
            $allowedPPPMethods = explode(
                ',',
                $this->scopeConfig->getValue(
                    'payment/iways_paypalplus_payment/third_party_moduls',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                )
            );
        }
        $store = $quote ? $quote->getStoreId() : null;
        $methods = [];
        $isFreeAdded = false;
        foreach ($this->paymentHelper->getStoreMethods($store, $quote) as $method) {
            if ($this->_canUseMethod($method, $quote)) {
                $method->setInfoInstance($quote->getPayment());

                if ($pppEnabled && strpos($method->getCode(), 'paypal_') === 0) {
                    continue;
                }

                if ($checkPPP) {
                    if (
                    ($method->getCode() == Payment::CODE
                        || $method->getCode() == self::AMAZON_PAYMENT
                        || in_array($method->getCode(), $allowedPPPMethods))
                    ) {
                        $methods[] = $method;
                    }
                } else {
                    $methods[] = $method;
                }
                if ($method->getCode() == Free::PAYMENT_METHOD_FREE_CODE) {
                    $isFreeAdded = true;
                }
            }
        }
        if (!$isFreeAdded && !$quote->getGrandTotal()) {
            $methods = $this->addFree($methods, $quote);
        }
        return $methods;
    }

    /**
     * Adds Free Method
     *
     * @param \Magento\Payment\Model\MethodInterface[] $methods
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @return \Magento\Payment\Model\MethodInterface[] $methods
     */
    protected function addFree($methods, \Magento\Quote\Api\Data\CartInterface $quote)
    {
        /** @var \Magento\Payment\Model\Method\Free $freeMethod */
        $freeMethod = $this->paymentHelper->getMethodInstance(Free::PAYMENT_METHOD_FREE_CODE);
        if ($freeMethod->isAvailableInConfig()) {
            $freeMethod->setInfoInstance($quote->getPayment());
            $methods[] = $freeMethod;
        }
        return $methods;
    }
}
