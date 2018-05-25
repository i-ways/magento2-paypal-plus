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

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Quote\Model\Quote;
use PayPal\Api\Refund;
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\Address;
use PayPal\Api\WebProfile;
use PayPal\Api\Presentation;
use PayPal\Api\Payment as PayPalPayment;
use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\InputFields;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Api\PayerInfo;
use PayPal\Api\ShippingAddress;
use PayPal\Api\PatchRequest;
use PayPal\Api\Patch;
use PayPal\Api\PaymentExecution;
use PayPal\Exception\PayPalConnectionException;

/**
 * Iways PayPal Rest Api wrapper
 *
 * @category   Iways
 * @package    Iways_PayPalPlus
 * @author robert
 */
class Api
{
    /**
     * Webhook url already exists error code
     */
    const WEBHOOK_URL_ALREADY_EXISTS = 'WEBHOOK_URL_ALREADY_EXISTS';

    const PATCH_ADD = 'add';
    const PATCH_REPLACE = 'replace';

    const VALIDATION_ERROR = 'VALIDATION_ERROR';

    /**
     * @var null|ApiContext
     */
    protected $_apiContext = null;

    /**
     * @var mixed|null
     */
    protected $_mode = null;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Iways\PayPalPlus\Helper\Data
     */
    protected $payPalPlusHelper;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Iways\PayPalPlus\Model\Webhook\EventFactory
     */
    protected $payPalPlusWebhookEventFactory;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Backend\Model\Session
     */
    protected $backendSession;


    /**
     * @var \Magento\Framework\App\Filesystem\DirectoryList
     */
    protected $directoryList;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @var \Magento\Framework\View\Asset\Repository
     */
    protected $assetRepo;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * Prepare PayPal REST SDK ApiContent
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Iways\PayPalPlus\Helper\Data $payPalPlusHelper
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param Webhook\EventFactory $payPalPlusWebhookEventFactory
     * @param \Magento\Checkout\Model\Session $session
     * @param \Magento\Backend\Model\Session $backendSession
     * @param DirectoryList $directoryList
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param EncryptorInterface $encryptor
     * @param \Magento\Framework\View\Asset\Repository $assetRepo
     * @param \Magento\Framework\UrlInterface $urlBuilder
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Registry $registry,
        \Magento\Customer\Model\Session $customerSession,
        \Iways\PayPalPlus\Helper\Data $payPalPlusHelper,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Iways\PayPalPlus\Model\Webhook\EventFactory $payPalPlusWebhookEventFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Backend\Model\Session $backendSession,
        \Magento\Framework\App\Filesystem\DirectoryList $directoryList,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        EncryptorInterface $encryptor,
        \Magento\Framework\View\Asset\Repository $assetRepo,
        \Magento\Framework\UrlInterface $urlBuilder
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->registry = $registry;
        $this->customerSession = $customerSession;
        $this->payPalPlusHelper = $payPalPlusHelper;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->payPalPlusWebhookEventFactory = $payPalPlusWebhookEventFactory;
        $this->checkoutSession = $checkoutSession;
        $this->backendSession = $backendSession;
        $this->directoryList = $directoryList;
        $this->messageManager = $messageManager;
        $this->encryptor = $encryptor;
        $this->assetRepo = $assetRepo;
        $this->urlBuilder = $urlBuilder;
        $this->setApiContext(null);
    }

    /**
     * Set api context
     *
     * @param $website
     * @return $this
     */
    public function setApiContext($website = null)
    {
        $this->_apiContext = new ApiContext(
            new OAuthTokenCredential(
                $this->scopeConfig->getValue('iways_paypalplus/api/client_id',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $website),
                $this->scopeConfig->getValue('iways_paypalplus/api/client_secret',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $website)
            )
        );

        $this->_mode = $this->scopeConfig->getValue('iways_paypalplus/api/mode',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $website);

        $this->_apiContext->setConfig(
            [
                'http.ConnectionTimeOut' => 30,
                'http.Retry' => 1,
                'cache.enabled' => $this->scopeConfig->getValue(
                    'iways_paypalplus/dev/token_cache',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                    $website
                ),
                'mode' => $this->_mode,
                'log.LogEnabled' => $this->scopeConfig->getValue('iways_paypalplus/dev/debug',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $website),
                'log.FileName' => $this->directoryList->getPath(DirectoryList::LOG) . '/PayPal.log',
                'log.LogLevel' => 'INFO'
            ]
        );
        $this->_apiContext->addRequestHeader('PayPal-Partner-Attribution-Id', 'Magento_Cart_PayPalPlusMagento2');
        return $this;
    }

    /**
     * Get ApprovalLink for curretn Quote
     *
     * @return string
     */
    public function getPaymentExperience()
    {
        $paymentExperience = $this->registry->registry('payment_experience');
        if ($paymentExperience === null) {
            $webProfile = $this->buildWebProfile();
            if ($webProfile) {
                $payment = $this->createPayment($webProfile, $this->getQuote());
                $paymentExperience = $payment ? $payment->getApprovalLink() : false;
            } else {
                $paymentExperience = false;
            }
            $this->registry->register('payment_experience', $paymentExperience);
        }
        return $paymentExperience;
    }

    /**
     * Get a payment
     *
     * @param string $paymentId
     * @return \PayPal\Api\Payment
     */
    public function getPayment($paymentId)
    {
        return PayPalPayment::get($paymentId, $this->_apiContext);
    }

    /**
     * Create payment for current quote
     *
     * @param WebProfile $webProfile
     * @param \Magento\Quote\Model\Quote $quote
     * @return boolean|PayPalPayment
     */
    public function createPayment($webProfile, $quote, $taxFailure = false)
    {
        /**
         * Skip if grand total is zero
         */
        if ($quote->getBaseGrandTotal() == "0.0000") {
            return false;
        }

        $payer = $this->buildPayer($quote);

        $itemList = $this->buildItemList($quote, $taxFailure);

        $amount = $this->buildAmount($quote);

        $transaction = new Transaction();
        $transaction->setAmount($amount);
        $transaction->setItemList($itemList);

        $redirectUrls = new RedirectUrls();
        $redirectUrls->setReturnUrl($this->urlBuilder->getUrl('paypalplus/order/create'))
            ->setCancelUrl($this->urlBuilder->getUrl('paypalplus/checkout/cancel'));

        $payment = new PayPalPayment();
        $payment->setIntent("sale")
            ->setExperienceProfileId($webProfile->getId())
            ->setPayer($payer)
            ->setRedirectUrls($redirectUrls)
            ->setTransactions(array($transaction));

        try {
            $response = $payment->create($this->_apiContext);
            $this->customerSession->setPayPalPaymentId($response->getId());
            $this->customerSession->setPayPalPaymentPatched(null);
        } catch (PayPalConnectionException $ex) {
            if (!$taxFailure) {
                return $this->createPayment($webProfile, $quote, true);
            }
            $this->payPalPlusHelper->handleException($ex);
            return false;
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return false;
        }
        return $response;
    }

    /**
     * Adding shipping address to an existing payment.
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @return boolean
     */
    public function patchPayment($quote)
    {
        if ($this->customerSession->getPayPalPaymentId()) {
            $payment = PayPalPayment::get($this->customerSession->getPayPalPaymentId(), $this->_apiContext);
            $patchRequest = new PatchRequest();

            if (!$quote->isVirtual()) {
                $shippingAddress = $this->buildShippingAddress($quote);
                $addressPatch = new Patch();
                $addressPatch->setOp(self::PATCH_ADD);
                $addressPatch->setPath('/transactions/0/item_list/shipping_address');
                $addressPatch->setValue($shippingAddress);
                $patchRequest->addPatch($addressPatch);
            }

            $payerInfo = $this->buildBillingAddress($quote);
            $payerInfoPatch = new Patch();
            $payerInfoPatch->setOp(self::PATCH_ADD);
            $payerInfoPatch->setPath('/potential_payer_info/billing_address');
            $payerInfoPatch->setValue($payerInfo);
            $patchRequest->addPatch($payerInfoPatch);

            $itemList = $this->buildItemList($quote, false);
            $itemListPatch = new Patch();
            $itemListPatch->setOp('replace');
            $itemListPatch->setPath('/transactions/0/item_list');
            $itemListPatch->setValue($itemList);
            $patchRequest->addPatch($itemListPatch);

            $amount = $this->buildAmount($quote);
            $amountPatch = new Patch();
            $amountPatch->setOp('replace');
            $amountPatch->setPath('/transactions/0/amount');
            $amountPatch->setValue($amount);
            $patchRequest->addPatch($amountPatch);


            try {
                $response = $payment->update(
                    $patchRequest,
                    $this->_apiContext
                );
                return $response;
            } catch (\PayPal\Exception\PayPalConnectionException $ex) {
                $message = json_decode($ex->getData());
                if (
                    isset($message->name)
                    && isset($message->details)
                    && $message->name == self::VALIDATION_ERROR
                ) {
                    $validationMessage = __('Your address is invalid. Please check following errors: ');
                    foreach ($message->details as $detail) {
                        if (isset($detail->field) && isset($detail->issue)) {
                            $validationMessage .=
                                __(
                                    'Field: "%1" - %2. ',
                                    [
                                        $detail->field,
                                        $detail->issue
                                    ]
                                );
                        }
                    }
                    throw new \Exception($validationMessage);
                }
            }

        }
        return false;
    }


    /**
     * Patches invoice number to PayPal transaction
     * (Magento order increment id)
     *
     * @param string $paymentId
     * @param string $invoiceNumber
     * @return bool
     */
    public function patchInvoiceNumber($paymentId, $invoiceNumber)
    {
        $payment = PayPalPayment::get($paymentId, $this->_apiContext);

        $patchRequest = new PatchRequest();

        $invoiceNumberPatch = new Patch();
        $invoiceNumberPatch->setOp('add');
        $invoiceNumberPatch->setPath('/transactions/0/invoice_number');
        $invoiceNumberPatch->setValue($invoiceNumber);
        $patchRequest->addPatch($invoiceNumberPatch);

        $response = $payment->update($patchRequest,
            $this->_apiContext);

        return $response;
    }

    /**
     * Execute an existing payment
     *
     * @param string $paymentId
     * @param string $payerId
     * @return boolean|\PayPal\Api\Payment
     */
    public function executePayment($paymentId, $payerId)
    {
        try {
            $payment = $this->getPayment($paymentId);
            $paymentExecution = new PaymentExecution();
            $paymentExecution->setPayerId($payerId);
            return $payment->execute($paymentExecution, $this->_apiContext);
        } catch (PayPalConnectionException $ex) {
            $this->payPalPlusHelper->handleException($ex);
            return false;
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return false;
        }
    }

    /**
     * Refund a payment
     *
     * @param string $paymentId
     * @param string $amount
     * @return Refund
     */
    public function refundPayment($paymentId, $amount)
    {
        $transactions = $this->getPayment($paymentId)->getTransactions();
        $relatedResources = $transactions[0]->getRelatedResources();
        $sale = $relatedResources[0]->getSale();
        $refund = new \PayPal\Api\Refund();

        $ppAmount = new Amount();
        $ppAmount->setCurrency($this->storeManager->getStore()->getCurrentCurrencyCode())->setTotal($amount);
        $refund->setAmount($ppAmount);

        return $sale->refund($refund, $this->_apiContext);
    }

    /**
     * Get a list of all registrated webhooks for $this->_apiContext
     *
     * @return bool|\PayPal\Api\WebhookList
     */
    public function getWebhooks()
    {
        $webhooks = new \PayPal\Api\Webhook();
        try {
            return $webhooks->getAll($this->_apiContext);
        } catch (PayPalConnectionException $ex) {
            $this->payPalPlusHelper->handleException($ex);
            return false;
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return false;
        }
    }

    /**
     * Retrive an webhook event
     *
     * @param $webhookEventId
     * @return bool|\PayPal\Api\WebhookEvent
     */
    public function getWebhookEvent($webhookEventId)
    {
        try {
            $webhookEvent = new \PayPal\Api\WebhookEvent();
            return $webhookEvent->get($webhookEventId, $this->_apiContext);
        } catch (PayPalConnectionException $ex) {
            $this->payPalPlusHelper->handleException($ex);
            return false;
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return false;
        }
    }

    /**
     * Get a list of all available event types
     *
     * @return bool|\PayPal\Api\WebhookEventTypeList
     */
    public function getWebhooksEventTypes()
    {
        $webhookEventType = new \PayPal\Api\WebhookEventType();
        try {
            return $webhookEventType->availableEventTypes($this->_apiContext);
        } catch (PayPalConnectionException $ex) {
            $this->payPalPlusHelper->handleException($ex);
            return false;
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return false;
        }
        return false;
    }

    /**
     * Creates a webhook
     *
     * @return bool|\PayPal\Api\Webhook
     */
    public function createWebhook()
    {
        $webhook = new \PayPal\Api\Webhook();
        $webhook->setUrl($this->payPalPlusHelper->getWebhooksUrl());
        $webhookEventTypes = array();
        foreach ($this->payPalPlusWebhookEventFactory->create()->getSupportedWebhookEvents() as $webhookEvent) {
            $webhookEventType = new \PayPal\Api\WebhookEventType();
            $webhookEventType->setName($webhookEvent);
            $webhookEventTypes[] = $webhookEventType;
        }
        $webhook->setEventTypes($webhookEventTypes);
        try {
            $webhookData = $webhook->create($this->_apiContext);
            $this->saveWebhookId($webhookData->getId());
            return $webhookData;
        } catch (PayPalConnectionException $ex) {
            if ($ex->getData()) {
                $data = json_decode($ex->getData(), true);
                if (isset($data['name']) && $data['name'] == self::WEBHOOK_URL_ALREADY_EXISTS) {
                    return true;
                }
            }
            $this->payPalPlusHelper->handleException($ex);
            return false;
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return false;
        }
        return false;
    }

    /**
     * Delete webhook with webhookId for PayPal APP $this->_apiContext
     *
     * @param int $webhookId
     * @return bool
     */
    public function deleteWebhook($webhookId)
    {
        $webhook = new \PayPal\Api\Webhook();
        $webhook->setId($webhookId);
        try {
            return $webhook->delete($this->_apiContext);
        } catch (PayPalConnectionException $ex) {
            $this->payPalPlusHelper->handleException($ex);
            return false;
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return false;
        }
        return false;
    }

    /**
     * Validate WebhookEvent
     *
     * @param $rawBody Raw request string
     * @return bool|\PayPal\Api\WebhookEvent
     */
    public function validateWebhook($rawBody)
    {
        try {
            $webhookEvent = new \PayPal\Api\WebhookEvent();
            return $webhookEvent->validateAndGetReceivedEvent($rawBody, $this->_apiContext);
        } catch (\Exception $ex) {
            $this->logger->critical($ex);
            return false;
        }
    }


    /**
     * Build ShippingAddress from quote
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @return ShippingAddress
     */
    protected function buildShippingAddress($quote)
    {
        $address = $quote->getShippingAddress();
        $addressCheckerArray = array(
            'setRecipientName' => $this->buildFullName($address),
            'setLine1' => implode(' ', $address->getStreet()),
            'setCity' => $address->getCity(),
            'setCountryCode' => $address->getCountryId(),
            'setPostalCode' => $address->getPostcode(),
            'setState' => $address->getRegion(),
        );
        $allowedEmpty = array('setPhone', 'setState');
        $shippingAddress = new ShippingAddress();
        foreach ($addressCheckerArray as $setter => $value) {
            if (empty($value) && !in_array($setter, $allowedEmpty)) {
                return false;
            }
            $shippingAddress->{$setter}($value);
        }

        return $shippingAddress;
    }

    /**
     * Build BillingAddress from quote
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @return ShippingAddress|boolean
     */
    protected function buildBillingAddress($quote)
    {
        $address = $quote->getBillingAddress();
        $addressCheckerArray = array(
            'setLine1' => implode(' ', $address->getStreet()),
            'setCity' => $address->getCity(),
            'setCountryCode' => $address->getCountryId(),
            'setPostalCode' => $address->getPostcode(),
            'setState' => $address->getRegion(),
        );
        $allowedEmpty = array('setPhone', 'setState');
        $billingAddress = new Address();
        foreach ($addressCheckerArray as $setter => $value) {
            if (empty($value) && !in_array($setter, $allowedEmpty)) {
                return false;
            }
            $billingAddress->{$setter}($value);
        }

        return $billingAddress;
    }

    /**
     * Build Payer for payment
     *
     * @param $quote
     * @return Payer
     */
    protected function buildPayer($quote)
    {
        $payer = new Payer();
        $payer->setPaymentMethod("paypal");
        return $payer;
    }

    /**
     * Build PayerInfo for Payer
     *
     * @param $quote
     * @return PayerInfo
     */
    protected function buildPayerInfo($quote)
    {
        $payerInfo = new PayerInfo();
        $address = $quote->getBillingAddress();
        if ($address->getFirstname()) {
            $payerInfo->setFirstName($address->getFirstname());
        }
        if ($address->getMiddlename()) {
            $payerInfo->setMiddleName($address->getMiddlename());
        }
        if ($address->getLastname()) {
            $payerInfo->setLastName($address->getLastname());
        }

        $billingAddress = $this->buildBillingAddress($quote);
        if ($billingAddress) {
            $payerInfo->setBillingAddress($billingAddress);
        }
        return $payerInfo;
    }

    /**
     * Get fullname from address
     *
     * @param  \Magento\Quote\Model\Quote\Address $address
     * @return type
     */
    protected function buildFullName($address)
    {
        $name = array();
        if ($address->getFirstname()) {
            $name[] = $address->getFirstname();
        }
        if ($address->getMiddlename()) {
            $name[] = $address->getMiddlename();
        }
        if ($address->getLastname()) {
            $name[] = $address->getLastname();
        }
        return implode(' ', $name);
    }

    /**
     * Build ItemList
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @return ItemList
     */
    protected function buildItemList($quote, $taxFailure)
    {
        $itemArray = array();
        $itemList = new ItemList();
        $currencyCode = $quote->getBaseCurrencyCode();

        if (!$taxFailure) {
            foreach ($quote->getAllVisibleItems() as $quoteItem) {
                $item = new Item();
                if ($quoteItem->getQty() > 1) {
                    $item->setName($quoteItem->getName() . ' x' . $quoteItem->getQty());
                } else {
                    $item->setName($quoteItem->getName());
                }
                $item
                    ->setSku($quoteItem->getSku())
                    ->setCurrency($currencyCode)
                    ->setQuantity(1)
                    ->setPrice($quoteItem->getBaseRowTotal());

                $itemArray[] = $item;
            }

            $itemList->setItems($itemArray);
        }
        return $itemList;
    }

    /**
     * Build Amount
     *
     * @param Quote $quote
     * @return Amount
     */
    protected function buildAmount($quote)
    {
        $details = new Details();
        $shippingCost = $quote->getShippingAddress()->getFreeShipping() ? 0 : $quote->getShippingAddress()->getBaseShippingAmount();

        $details->setShipping($shippingCost)
            ->setTax(
                $quote->getShippingAddress()->getBaseTaxAmount()
                + $quote->getShippingAddress()->getBaseHiddenTaxAmount()
                + $quote->getBillingAddress()->getBaseTaxAmount()
                + $quote->getBillingAddress()->getBaseHiddenTaxAmount()
            )
            ->setSubtotal(
                $quote->getBaseSubtotal()
            );

        if ($quote->isVirtual()) {
            if($quote->getBillingAddress()->getDiscountAmount()) {
                $details->setShippingDiscount(
                    -(
                        $quote->getBillingAddress()->getDiscountAmount()
                        + $quote->getBillingAddress()->getBaseDiscountTaxCompensationAmount()
                    )
                );
            }
        } else {
            if ($quote->getShippingAddress()->getDiscountAmount()) {
                $details->setShippingDiscount(
                    -(
                        $quote->getShippingAddress()->getDiscountAmount()
                        + $quote->getShippingAddress()->getBaseDiscountTaxCompensationAmount()
                    )
                );
            }
        }

        $total = $quote->getBaseGrandTotal();
        if ((float)$quote->getShippingAddress()->getBaseShippingAmount() == 0 && (float)$quote->getShippingAddress()->getBaseShippingInclTax() >= 0) {
            $total = (float)$total - (float)$quote->getShippingAddress()->getBaseShippingInclTax();
        }

        $amount = new Amount();
        $amount->setCurrency($quote->getBaseCurrencyCode())
            ->setDetails($details)
            ->setTotal($total);

        return $amount;
    }


    /**
     * Build WebProfile
     *
     * @return boolean|WebProfile
     */
    protected function buildWebProfile()
    {
        $webProfile = new WebProfile();
        if ($this->scopeConfig->getValue('iways_paypalplus/dev/web_profile_id',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
        ) {
            $webProfile->setId($this->scopeConfig->getValue('iways_paypalplus/dev/web_profile_id',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
            return $webProfile;
        }
        try {
            $webProfile->setName('magento_' . microtime());
            $webProfile->setPresentation($this->buildWebProfilePresentation());
            $inputFields = new InputFields();
            $inputFields->setAddressOverride(1);
            $webProfile->setInputFields($inputFields);
            $response = $webProfile->create($this->_apiContext);
            $this->saveWebProfileId($response->getId());
            return $response;
        } catch (PayPalConnectionException $ex) {
            $this->payPalPlusHelper->handleException($ex);
        }
        return false;
    }

    /**
     * Build presentation
     *
     * @return Presentation
     */
    protected function buildWebProfilePresentation()
    {
        $presentation = new Presentation();
        $presentation->setBrandName($this->storeManager->getWebsite()->getName());
        $presentation->setLogoImage($this->getHeaderImage());
        $presentation->setLocaleCode(
            substr(
                $this->scopeConfig->getValue('general/locale/code', \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
                -2
            )
        );
        return $presentation;
    }

    /**
     * Get Header Logo for Web experience
     *
     * @return string
     */
    protected function getHeaderImage()
    {
        if ($this->scopeConfig->getValue('iways_paypalplus/api/hdrimg',
            \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE)
        ) {
            return $this->scopeConfig->getValue('iways_paypalplus/api/hdrimg',
                \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE);
        }
        $folderName = \Magento\Config\Model\Config\Backend\Image\Logo::UPLOAD_DIR;
        $storeLogoPath = $this->scopeConfig->getValue(
            'design/header/logo_src',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        if ($storeLogoPath) {
            $path = $folderName . '/' . $storeLogoPath;
            return $this->urlBuilder
                    ->getBaseUrl(['_type' => \Magento\Framework\UrlInterface::URL_TYPE_MEDIA]) . $path;
        }
        return $this->assetRepo->getUrlWithParams('images/logo.svg', ['_secure' => true]);

    }

    /**
     * Reset web profile id
     *
     * @return boolean
     */
    public function resetWebProfileId()
    {
        return $this->payPalPlusHelper->resetWebProfileId();
    }

    /**
     * Save WebProfileId
     *
     * @param string $id
     * @return boolean
     */
    protected function saveWebProfileId($id)
    {
        return $this->payPalPlusHelper->saveStoreConfig('iways_paypalplus/dev/web_profile_id', $id);
    }

    /**
     * Save WebhookId
     *
     * @param string $id
     * @return boolean
     */
    protected function saveWebhookId($id)
    {
        return $this->payPalPlusHelper->saveStoreConfig('iways_paypalplus/dev/webhook_id', $id);
    }

    /**
     * Get current quote
     *
     * @return \Magento\Quote\Model\Quote
     */
    protected function getQuote()
    {
        return $this->checkoutSession->getQuote();
    }

    /**
     * Check if PayPal credentails are valid for given configuration.
     *
     * Uses WebProfile::get_list()
     *
     * @param $website
     * @return bool
     */
    public function testCredentials($website)
    {
        try {
            $this->setApiContext($website);
            WebProfile::get_list($this->_apiContext);
            return true;
        } catch (PayPalConnectionException $ex) {
            $this->messageManager->addError(
                __('Provided credentials not valid.')
            );
            return false;
        } catch (Exception $e) {
            $this->logger->critical($e);
            return false;
        }
    }
}
