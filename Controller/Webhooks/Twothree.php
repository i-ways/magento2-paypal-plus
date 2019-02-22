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

namespace Iways\PayPalPlus\Controller\Webhooks;

use Iways\PayPalPlus\Compatibility\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;

/**
 * Unified IPN controller for all supported PayPal methods
 */
class Twothree extends Index implements CsrfAwareActionInterface
{
    /**
     * @param \Magento\Framework\App\RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(\Magento\Framework\App\RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * @param \Magento\Framework\App\RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(\Magento\Framework\App\RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }
}
