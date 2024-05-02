<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Block\Adminhtml\System\Config\Ecom;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Resursbank\Ordermanagement\Helper\Ecom\Callback as CallbackHelper;
use Resursbank\Ordermanagement\Helper\Log;
use Throwable;

/**
 * List callback URLs utilised by API to communicate with website.
 */
class CallbackList extends Field
{
    /**
     * @param Context $context
     * @param CallbackHelper $callbackHelper
     * @param Log $log
     */
    public function __construct(
        Context $context,
        public readonly CallbackHelper $callbackHelper,
        public readonly Log $log
    ) {
        $this->setTemplate(
            template: 'Resursbank_Ordermanagement::system/config/callback-list.phtml'
        );

        parent::__construct($context);
    }

    /**
     * Resolve callback URL.
     *
     * @param string $type
     * @return string
     */
    public function getCallbackUrl(string $type): string
    {
        $result = __('rb-failed-to-resolve-callback-url');

        try {
            $result = $this->callbackHelper->getUrl(type: $type);
        } catch (Throwable $error) {
            $this->log->exception(error: $error);
        }

        return $result;
    }

    /**
     * Unset some non-related element parameters.
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(
        AbstractElement $element
    ): string {
        /** @noinspection PhpUndefinedMethodInspection */
        /** @phpstan-ignore-next-line */
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
     * @noinspection PhpMissingParentCallCommonInspection
     */
    protected function _getElementHtml(
        AbstractElement $element
    ): string {
        return $this->_toHtml();
    }
}
