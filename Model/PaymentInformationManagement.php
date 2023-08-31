<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 7.3.17
 *
 * @category Modules
 * @package  Magento
 * @author   Robert Hillebrand <hillebrand@i-ways.net>
 * @license  http://opensource.org/licenses/osl-3.0.php Open Software License 3.0
 * @link     https://www.i-ways.net
 */

namespace Iways\PayPalPlus\Model;

class PaymentInformationManagement
{
    /**
     * Protected $payPalPlusApiFactory
     *
     * @var ApiFactory
     */
    protected $payPalPlusApiFactory;

    /**
     * Protected $quoteManagement
     *
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteManagement;

    /**
     * Protected $customerSession
     *
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    public function __construct(
        \Iways\PayPalPlus\Model\ApiFactory $payPalPlusApiFactory,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Customer\Model\Session $customerSession
    ) {
        $this->payPalPlusApiFactory = $payPalPlusApiFactory;
        $this->quoteManagement = $quoteRepository;
        $this->customerSession = $customerSession;
    }

    public function patchPayment($cartId)
    {
        $quote = $this->quoteManagement->getActive($cartId);
        $result = $this->payPalPlusApiFactory->create()->patchPayment($quote);
        if ($result === false) {
            throw new \Exception('Could not patch payment for quote');
        }
        return $result;
    }

    public function handleComment($paymentMethod)
    {
        $this->customerSession->setOrderComment(null);
        $additionalData = $paymentMethod->getAdditionalData();
        if (isset($additionalData['comments']) && !empty($additionalData['comments'])) {
            $this->customerSession->setOrderComment($additionalData['comments']);
        }
        return $paymentMethod;
    }
}
