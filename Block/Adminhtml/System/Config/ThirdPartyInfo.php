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

/**
 * Config form fieldset renderer
 */

namespace Iways\PayPalPlus\Block\Adminhtml\System\Config;

class ThirdPartyInfo extends \Magento\Config\Block\System\Config\Form\Fieldset
{
    protected $_dummyElement;
    protected $_fieldRenderer;
    protected $_values;
    /**
     * @var \Magento\Payment\Model\Config
     */
    protected $paymentConfig;

    public function __construct(
        \Magento\Payment\Model\Config $paymentConfig,
        \Magento\Backend\Block\Context $context,
        \Magento\Backend\Model\Auth\Session $authSession,
        \Magento\Framework\View\Helper\Js $jsHelper,
        array $data = []
    ) {
        $this->paymentConfig = $paymentConfig;
        parent::__construct($context, $authSession, $jsHelper, $data);
    }

    /**
     * Render fieldset html
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $this->setElement($element);
        $html = $this->_getHeaderHtml($element);
        $dummyField = $element->getElements()[0];

        $thirdPartyMethods = explode(',', $this->_scopeConfig->getValue('payment/iways_paypalplus_payment/third_party_moduls'));
        foreach ($this->paymentConfig->getActiveMethods() as $paymentMethod) {
            if (in_array($paymentMethod->getCode(), $thirdPartyMethods)) {
                $thirdPartyMethod = $paymentMethod->getCode();

                foreach(['text', 'image'] as $item){

                    $field = clone $dummyField;
                    $field->setData('name', str_replace('item_dummy', $item.'_'.$thirdPartyMethod, $field->getName()));
                    $field->setData('label', $paymentMethod->getTitle() . ' ' . __($item));
                    $field->setData('value', $this->_scopeConfig->getValue('payment/iways_paypalplus_section/third_party_modul_info/'.$item.'_' . $thirdPartyMethod));
                    $fieldConfig = $field->getData('field_config');
                    $fieldConfig['id'] = $item.'_' . $thirdPartyMethod;
                    $fieldConfig['label'] = $paymentMethod->getTitle();
                    $fieldConfig['config_path'] =
                        'payment/iways_paypalplus_section/third_party_modul_info/'.$item.'_' . $thirdPartyMethod;

                    $field->setData('field_config', $fieldConfig);
                    $field->setData('html_id', str_replace('item_dummy', $item.'_'.$thirdPartyMethod, $field->getData('html_id')));
                    $html .= $field->toHtml();
                }

            }
        }
        $html .= $this->_getFooterHtml($element);

        return $html;
    }
}
