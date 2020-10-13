<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Block\Adminhtml\System\Config\Callback;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Resursbank\Ordermanagement\Helper\Callback as CallbackHelper;

class Listing extends Field
{
    /**
     * @var CallbackHelper
     */
    public $callbackHelper;

    /**
     * @param Context $context
     * @param CallbackHelper $callbackHelper
     */
    public function __construct(
        Context $context,
        CallbackHelper $callbackHelper
    ) {
        $this->setTemplate('system/config/callback/listing.phtml');
        $this->callbackHelper = $callbackHelper;

        parent::__construct($context);
    }

    /**
     * Unset some non-related element parameters.
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();

        return parent::render($element);
    }

    /**
     * Get the button and scripts contents
     *
     * @param AbstractElement $element
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        return $this->_toHtml();
    }
}
