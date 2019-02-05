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

namespace Iways\PayPalPlus\Controller\Order;

use Magento\Customer\Model\Session;
use Magento\Framework\DataObject;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\OrderFactory;

/**
 * PayPalPlus checkout controller
 *
 * @category   Iways
 * @package    Iways_PayPalPlus
 * @author robert
 */
class Create extends \Magento\Framework\App\Action\Action
{

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Checkout\Helper\Data
     */
    protected $checkoutHelper;

    /**
     * @var Session
     */
    protected $customerSession;

    /**
     * @var \Magento\Quote\Api\CartManagementInterface
     */
    protected $cartManagement;

    /**
     * @var \Magento\Quote\Api\GuestCartManagementInterface
     */
    protected $guestCartManagement;

    /**
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * @var OrderSender
     */
    protected $orderSender;

    /**
     * @var \Magento\Sales\Model\Order\Status\HistoryFactory
     */
    protected $historyFactory;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Checkout\Helper\Data $checkoutHelper,
        \Magento\Quote\Api\CartManagementInterface $cartManagement,
        \Magento\Quote\Api\GuestCartManagementInterface $guestCartManagement,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        OrderSender $orderSender,
        OrderFactory $orderFactory,
        \Magento\Sales\Model\Order\Status\HistoryFactory $historyFactory,
        Session $customerSession
    ) {
        $this->logger = $logger;
        $this->checkoutSession = $checkoutSession;
        $this->checkoutHelper = $checkoutHelper;
        $this->cartManagement = $cartManagement;
        $this->guestCartManagement = $guestCartManagement;
        $this->customerSession = $customerSession;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->orderSender = $orderSender;
        $this->orderFactory = $orderFactory;
        $this->historyFactory = $historyFactory;
        parent::__construct($context);

    }

    /**
     * Execute
     * @return void
     */
    public function execute()
    {
        try {
            $cartId = $this->checkoutSession->getQuoteId();
            $result = new DataObject();
            if ($this->customerSession->isLoggedIn()) {
                $orderId = $this->cartManagement->placeOrder($cartId);
            } else {
                $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'quote_id');
                $orderId = $this->guestCartManagement->placeOrder($quoteIdMask->getMaskedId());
            }

            if ($orderId) {
                $order = $this->orderFactory->create()->load($orderId);
                if ($order->getCanSendNewEmailFlag()) {
                    try {
						if (!$order->getEmailSent()) {
							$this->orderSender->send($order);
						}
                    } catch (\Exception $e) {
                        $this->logger->critical($e);
                    }
                }
                try {
                    // IWD_Opc Order Comment
                    if ($this->customerSession->getOrderComment()) {
                        if ($order->getData('entity_id')) {
                            /** @param string $status */
                            $status = $order->getData('status');
                            /** @param \Magento\Sales\Model\Order\Status\HistoryFactory $history */
                            $history = $this->historyFactory->create();
                            // set comment history data
                            $history->setData('comment', strip_tags($this->customerSession->getOrderComment()));
                            $history->setData('parent_id', $orderId);
                            $history->setData('is_visible_on_front', 1);
                            $history->setData('is_customer_notified', 0);
                            $history->setData('entity_name', 'order');
                            $history->setData('status', $status);
                            $history->save();
                            $this->customerSession->setOrderComment(null);
                        }
                    }
                } catch (\Exception $e) {

                }
            }
            $result->setData('success', true);
            $result->setData('error', false);

            $this->_eventManager->dispatch(
                'checkout_controller_onepage_saveOrder',
                [
                    'result' => $result,
                    'action' => $this
                ]
            );
            $this->_redirect('checkout/onepage/success');
        } catch (\Exception $e) {
            $this->messageManager->addError($e->getMessage());
            $this->_redirect('checkout/cart');
        }
    }
}
