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

namespace Iways\PayPalPlus\Model\Webhook;

use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;

/**
 * Iways PayPalPlus Event Handler
 *
 * @author robert
 */
class Event
{
    /**
     * Payment sale completed event type code
     */
    const PAYMENT_SALE_COMPLETED = 'PAYMENT.SALE.COMPLETED';
    /**
     * Payment sale pending  event type code
     */
    const PAYMENT_SALE_PENDING = 'PAYMENT.SALE.PENDING';
    /**
     * Payment sale refunded event type
     */
    const PAYMENT_SALE_REFUNDED = 'PAYMENT.SALE.REFUNDED';
    /**
     * Payment sale reversed event type code
     */
    const PAYMENT_SALE_REVERSED = 'PAYMENT.SALE.REVERSED';
    /**
     * Risk dispute created event type code
     */
    const RISK_DISPUTE_CREATED = 'RISK.DISPUTE.CREATED';

    /**
     * Store order instance
     *
     * @var \Magento\Sales\Model\Order
     */
    protected $_order = null; // phpcs:ignore PSR2.Classes.PropertyDeclaration

    /**
     * Protected $salesOrderPaymentTransactionFactory
     *
     * @var \Magento\Sales\Model\Order\Payment\TransactionFactory
     */
    protected $salesOrderPaymentTransactionFactory;

    /**
     * Protected $salesOrderFactory
     *
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $salesOrderFactory;

    /**
     * Protected $invoiceSender
     * 
     * @var \Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    protected $invoiceSender;

    /**
     * Protected $logger
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(
        \Magento\Sales\Model\Order\Payment\TransactionFactory $salesOrderPaymentTransactionFactory,
        \Magento\Sales\Model\OrderFactory $salesOrderFactory,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->salesOrderPaymentTransactionFactory = $salesOrderPaymentTransactionFactory;
        $this->salesOrderFactory = $salesOrderFactory;
        $this->invoiceSender = $invoiceSender;
        $this->logger = $logger;
    }

    /**
     * Process the given $webhookEvent
     *
     * @param \PayPal\Api\WebhookEvent $webhookEvent
     *
     * @throws \Exception
     */
    public function processWebhookRequest(\PayPal\Api\WebhookEvent $webhookEvent)
    {
        if ($webhookEvent->getEventType() !== null
            && in_array($webhookEvent->getEventType(), $this->getSupportedWebhookEvents())
        ) {
            $this->getOrder($webhookEvent);
            $this->{$this->eventTypeToHandler($webhookEvent->getEventType())}($webhookEvent);
        }
    }

    /**
     * Get supported webhook events
     *
     * @return array
     */
    public function getSupportedWebhookEvents()
    {
        return [
            self::PAYMENT_SALE_COMPLETED,
            self::PAYMENT_SALE_PENDING,
            self::PAYMENT_SALE_REFUNDED,
            self::PAYMENT_SALE_REVERSED,
            self::RISK_DISPUTE_CREATED
        ];
    }

    /**
     * Parse event type to handler function
     *
     * @param $eventType
     *
     * @return string
     */
    protected function eventTypeToHandler($eventType)
    {
        $eventParts = explode('.', $eventType);
        foreach ($eventParts as $key => $eventPart) {
            if (!$key) {
                $eventParts[$key] = strtolower($eventPart);
                continue;
            }
            $eventParts[$key] = ucfirst(strtolower($eventPart));
        }
        return implode('', $eventParts);
    }

    /**
     * Mark transaction as completed
     *
     * @param \PayPal\Api\WebhookEvent $webhookEvent
     *
     * @throws \Exception
     */
    protected function paymentSaleCompleted(\PayPal\Api\WebhookEvent $webhookEvent)
    {
        $paymentResource = $webhookEvent->getResource();
        $parentTransactionId = $paymentResource->parent_payment;
        $payment = $this->_order->getPayment();

        $payment->setTransactionId($paymentResource->id)
            ->setCurrencyCode($paymentResource->amount->currency)
            ->setParentTransactionId($parentTransactionId)
            ->setIsTransactionClosed(true)
            ->registerCaptureNotification(
                $paymentResource->amount->total,
                true
            );
        $this->_order->save();

        // notify customer
        $invoice = $payment->getCreatedInvoice();
        if ($invoice && !$this->_order->getEmailSent()) {
            try {
                $this->invoiceSender->send($invoice);
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }

            $this->_order->addStatusToHistory(
                false,
                __(
                    'Notified customer about invoice #%1.',
                    $invoice->getIncrementId()
                ),
                true
            )->save();
        }
    }

    /**
     * Mark transaction as refunded
     *
     * @param \PayPal\Api\WebhookEvent $webhookEvent
     *
     * @throws \Exception
     */
    protected function paymentSaleRefunded(\PayPal\Api\WebhookEvent $webhookEvent)
    {
        $paymentResource = $webhookEvent->getResource();
        $parentTransactionId = $paymentResource->parent_payment;

        $payment = $this->_order->getPayment();
        $amount = $paymentResource->amount->total;

        $transactionId = $paymentResource->id;

        $payment->setPreparedMessage('')
            ->setTransactionId($transactionId)
            ->setParentTransactionId($parentTransactionId)
            ->setIsTransactionClosed(1)
            ->registerRefundNotification($amount);

        $this->_order->save();

        $creditmemo = $payment->getCreatedCreditmemo();
        if ($creditmemo) {
            $creditmemo->sendEmail();
            $this->_order
                ->addStatusHistoryComment(
                    __(
                        'Notified customer about creditmemo #%1.',
                        $creditmemo->getIncrementId()
                    )
                )->setIsCustomerNotified(true)
                ->save();
        }
    }

    /**
     * Mark transaction as pending
     *
     * @param \PayPal\Api\WebhookEvent $webhookEvent
     *
     * @throws \Exception
     */
    protected function paymentSalePending(\PayPal\Api\WebhookEvent $webhookEvent)
    {
        $paymentResource = $webhookEvent->getResource();
        $this->_order->getPayment()
            ->setPreparedMessage($webhookEvent->getSummary())
            ->setTransactionId($paymentResource->id)
            ->setIsTransactionClosed(0)
            ->registerPaymentReviewAction(\Magento\Sales\Model\Order\Payment::REVIEW_ACTION_UPDATE, false);
        $this->_order->save();
    }

    /**
     * Mark transaction as reversed
     *
     * @param \PayPal\Api\WebhookEvent $webhookEvent
     *
     * @throws \Exception
     */
    protected function paymentSaleReversed(\PayPal\Api\WebhookEvent $webhookEvent)
    {
        $this->_order->setStatus(\Magento\Paypal\Model\Info::ORDER_STATUS_REVERSED);
        $this->_order->save();
        $this->_order
            ->addStatusHistoryComment(
                $webhookEvent->getSummary(),
                \Magento\Paypal\Model\Info::ORDER_STATUS_REVERSED
            )->setIsCustomerNotified(false)
            ->save();
    }

    /**
     * Add risk dispute to order comment
     *
     * @param \PayPal\Api\WebhookEvent $webhookEvent
     */
    protected function riskDisputeCreated(\PayPal\Api\WebhookEvent $webhookEvent)
    {
        //Add IPN comment about registered dispute
        $this->_order->addStatusHistoryComment($webhookEvent->getSummary())->setIsCustomerNotified(false)->save();
    }

    /**
     * Load and validate order, instantiate proper configuration
     *
     * @return \Magento\Sales\Model\Order
     *
     * @throws \Exception
     */
    protected function getOrder(\PayPal\Api\WebhookEvent $webhookEvent)
    {
        if (empty($this->_order)) {
            // get proper order
            $resource = $webhookEvent->getResource();
            if (!$resource) {
                $this->logger->critical('Event resource not found.');
                // throw new \Exception('Event resource not found.');
            }

            $transactionId = $resource->id;

            $transaction = $this->salesOrderPaymentTransactionFactory->create()->load($transactionId, 'txn_id');
            $this->_order = $this->salesOrderFactory->create()->load($transaction->getOrderId());
            if (!$this->_order->getId()) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Order not found.'));
            }
        }
        return $this->_order;
    }
}
