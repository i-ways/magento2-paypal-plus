<?php

namespace Iways\PayPalPlus\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Class HidePaypalMethods
 *
 * @package Iways\PayPalPlus\Plugin
 */
class HidePaypalMethods
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * HidePaypalMethods constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param \Magento\Payment\Model\MethodList     $subject
     * @param callable                              $proceed
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @return array|\Magento\Payment\Model\MethodInterface[]
     */
    public function aroundGetAvailableMethods(
        /** @noinspection PhpUnusedParameterInspection */
        $subject,
        callable $proceed,
        \Magento\Quote\Api\Data\CartInterface $quote
    ) {
        /** @var \Magento\Payment\Model\MethodInterface[] $methods */
        $methods = $proceed($quote);

        // hide paypal, amazon and third party methods
        $activePath = 'payment/iways_paypalplus_payment/active';
        if (!$this->scopeConfig->getValue($activePath, ScopeInterface::SCOPE_STORE)) {
            return $methods;
        }

        $thirdPartyPath    = 'payment/iways_paypalplus_payment/third_party_moduls';
        $allowedPPPMethods = explode(',', $this->scopeConfig->getValue($thirdPartyPath, ScopeInterface::SCOPE_STORE));

        $result = [];
        foreach ($methods as $method) {
            if (strpos($method->getCode(), 'paypal_') === 0) {
                continue;
            }

            if (\in_array($method->getCode(), $allowedPPPMethods)) {
                continue;
            }

            $result[] = $method;
        }

        return $result;
    }
}