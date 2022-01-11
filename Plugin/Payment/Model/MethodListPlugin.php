<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.phprotected $payPalPlusHelper
 *
 * PHP version 7.3.17
 *
 * @category Modules
 * @package  Magento
 * @author   Robert Hillebrand <hillebrand@i-ways.net>
 * @license  http://opensource.org/licenses/osl-3.0.php Open Software License 3.0
 * @link     https://www.i-ways.net
 */

namespace Iways\PayPalPlus\Plugin\Payment\Model;

use Iways\PayPalPlus\Model\Payment;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\MethodList;

class MethodListPlugin
{
    const AMAZON_PAYMENT = ['amazon_payment', 'amazon_payment_v2'];
    const CHECK_PPP_FUNCTION_NAME = 'getCheckPPP';

    /**
     * Protected $scopeConfig
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * MethodListPlugin constructor
     *
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Function afterGetAvailableMethods
     *
     * @param MethodList $methodList
     * @param $result
     *
     * @return array
     */
    public function afterGetAvailableMethods(MethodList $methodList, $result)
    {
        $checkPPP = false;
        if (method_exists($methodList, self::CHECK_PPP_FUNCTION_NAME)) {
            $checkPPP = $methodList->{self::CHECK_PPP_FUNCTION_NAME}();
        }

        if (!$checkPPP) {
            $allowedPPPMethods = explode(
                ',',
                $this->scopeConfig->getValue(
                    'payment/iways_paypalplus_payment/third_party_moduls',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                )
            );
            $allowedMethods = [];

            foreach ($result as $method) {
                if ($method->getCode() == Payment::CODE
                    || in_array($method->getCode(), self::AMAZON_PAYMENT, true)
                    || !in_array($method->getCode(), $allowedPPPMethods)
                ) {
                    $allowedMethods[] = $method;
                }
            }

            return $allowedMethods;
        }
        return $result;
    }
}
