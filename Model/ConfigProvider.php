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

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Escaper;
use Magento\Payment\Helper\Data as PaymentHelper;
use Psr\Log\LoggerInterface;

class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string[]
     */
    protected $methodCode = Payment::CODE;

    /**
     * @var Checkmo
     */
    protected $method;

    /**
     * @var Escaper
     */
    protected $escaper;

    /**
     * @var \Iways\PayPalPlus\Helper\Data
     */
    protected $payPalPlusHelper;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Payment\Model\Config
     */
    protected $paymentConfig;

    /**
     * Url Builder
     *
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var \Magento\Payment\Api\PaymentMethodListInterface
     */
    protected $paymentMethodList;

    /**
     * @param PaymentHelper $paymentHelper
     * @param Escaper $escaper
     * @param \Iways\PayPalPlus\Helper\Data $payPalPlusHelper
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Payment\Model\Config  $paymentConfig
     * @param \Magento\Payment\Api\PaymentMethodListInterface $paymentMethodList
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        PaymentHelper $paymentHelper,
        Escaper $escaper,
        \Iways\PayPalPlus\Helper\Data $payPalPlusHelper,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Payment\Model\Config $paymentConfig,
        \Magento\Payment\Api\PaymentMethodListInterface $paymentMethodList,
        \Magento\Framework\UrlInterface $urlBuilder,
        LoggerInterface $logger
    ) {
        $this->escaper = $escaper;
        $this->method = $paymentHelper->getMethodInstance($this->methodCode);
        $this->payPalPlusHelper = $payPalPlusHelper;
        $this->scopeConfig = $scopeConfig;
        $this->checkoutSession = $checkoutSession;
        $this->paymentConfig = $paymentConfig;
        $this->paymentMethodList = $paymentMethodList;
        $this->urlBuilder = $urlBuilder;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        return $this->method->isAvailable() ? [
            'payment' => [
                'iways_paypalplus_payment' => [
                    'paymentExperience' => $this->payPalPlusHelper->getPaymentExperience(),
                    'showPuiOnSandbox' => $this->scopeConfig->getValue('iways_paypalplus/dev/pui_sandbox',
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE) ? true : false,
                    'showLoadingIndicator' => $this->scopeConfig->getValue('payment/iways_paypalplus_payment/show_loading_indicator',
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE) ? true : false,
                    'mode' => $this->scopeConfig->getValue('iways_paypalplus/api/mode',
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
                    'country' => $this->getCountry(),
                    'language' => $this->scopeConfig->getValue('general/locale/code',
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
                    'thirdPartyPaymentMethods' => $this->getThirdPartyMethods()
                ],
            ],
        ] : [];
    }

    protected function getCountry()
    {
        $billingAddress = $this->checkoutSession->getQuote()->getBillingAddress();
        if ($billingAddress->getCountryId()) {
            return $billingAddress->getCountryId();
        }

        $shippingAddress = $this->checkoutSession->getQuote()->getShippingAddress();
        if ($shippingAddress->getCountryId()) {
            return $shippingAddress->getCountryId();
        }

        return $this->scopeConfig->getValue('paypal/general/merchant_country',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    protected function getThirdPartyMethods()
    {
        $storeId = $this->checkoutSession->getQuote()->getStoreId();
        $paymentMethods = $this->paymentMethodList->getActiveList($storeId);
        $allowedPPPMethods = explode(
            ',',
            $this->scopeConfig->getValue(
                'payment/iways_paypalplus_payment/third_party_moduls',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            )
        );
        $methods = [];
        foreach ($paymentMethods as $paymentMethod) {
            if (strpos($paymentMethod->getCode(), 'paypal') === false && in_array($paymentMethod->getCode(), $allowedPPPMethods)) {
                $method = [
                    'redirectUrl' => $this->urlBuilder->getUrl('checkout', ['_secure' => true]),
                    'methodName' => $paymentMethod->getTitle(),
                    'imageUrl' => '',
                    'description' => $this->scopeConfig->getValue(
                        'payment/iways_paypalplus_section/third_party_modul_info/text_'.$paymentMethod->getCode(),
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                    ),
                ];
                $methods[$paymentMethod->getCode()] = $method;
            }
        }
        return $methods;
    }
}