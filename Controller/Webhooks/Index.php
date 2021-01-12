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

namespace Iways\PayPalPlus\Controller\Webhooks;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Exception\LocalizedException;

/**
 * Unified IPN controller for all supported PayPal methods
 */
class Index extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    /**
     * Protected $_logger
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger; // phpcs:ignore PSR2.Classes.PropertyDeclaration

    /**
     * Protected $_webhookEventFactory
     *
     * @var \Iways\PayPalPlus\Model\Webhook\EventFactory
     */
    protected $_webhookEventFactory; // phpcs:ignore PSR2.Classes.PropertyDeclaration

    /**
     * Protected $_apiFactory
     *
     * @var \Iways\PayPalPlus\Model\ApiFactory
     */
    protected $_apiFactory; // phpcs:ignore PSR2.Classes.PropertyDeclaration

    /**
     * Protected $_driver
     *
     * @var \Magento\Framework\Filesystem\DriverInterface
     */
    protected $_driver; // phpcs:ignore PSR2.Classes.PropertyDeclaration

    /**
     * Function validateForCsrf
     *
     * @param \Magento\Framework\App\RequestInterface $request
     *
     * @return bool|null
     */
    public function validateForCsrf(\Magento\Framework\App\RequestInterface $request):
    ?bool
    {
        return true;
    }

    /**
     * Function createCsrfValidationException
     *
     * @param \Magento\Framework\App\RequestInterface $request
     *
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(\Magento\Framework\App\RequestInterface $request):
    ?InvalidRequestException
    {
        return null;
    }

    /**
     * Class constructor
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Iways\PayPalPlus\Model\Webhook\EventFactory $webhookEventFactory
     * @param \Iways\PayPalPlus\Model\ApiFactory $apiFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Filesystem\DriverInterface $driver
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Iways\PayPalPlus\Model\Webhook\EventFactory $webhookEventFactory,
        \Iways\PayPalPlus\Model\ApiFactory $apiFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Filesystem\Driver\File $driver
    ) {
        $this->_logger = $logger;
        $this->_webhookEventFactory = $webhookEventFactory;
        $this->_apiFactory = $apiFactory;
        $this->_driver = $driver;
        parent::__construct($context);
    }

    /**
     * Instantiate Event model and pass Webhook request to it
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.ExitExpression)
     */
    public function execute()
    {
        if (!$this->getRequest()->isPost()) {
            return;
        }

        try {
            $data = $this->_driver->fileGetContents('php://input'); // file_get_contents('php://input');
            $webhookEvent = $this->_apiFactory->create()->validateWebhook($data);
            if (!$webhookEvent) {
                throw new LocalizedException(__('Event not found.'));
            }
            $this->_webhookEventFactory->create()->processWebhookRequest($webhookEvent);
        } catch (\Exception $e) {
            $this->_logger->critical($e);
            $this->getResponse()->setStatusHeader(503, '1.1', 'Service Unavailable')->sendResponse();
        }
    }
}
