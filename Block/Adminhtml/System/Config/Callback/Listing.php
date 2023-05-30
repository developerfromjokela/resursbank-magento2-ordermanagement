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
use Resursbank\Core\Helper\Config;
use Resursbank\Core\Helper\Scope;
use Resursbank\Ordermanagement\Helper\Callback as CallbackHelper;

class Listing extends Field
{
    /**
     * @param Context $context
     * @param CallbackHelper $callbackHelper
     * @param Config $config
     * @param Scope $scope
     */
    public function __construct(
        Context $context,
        public CallbackHelper $callbackHelper,
        private readonly Config $config,
        private readonly Scope $scope
    ) {
        $template = $this->config->isMapiActive(
            scopeCode: $this->scope->getId(),
            scopeType: $this->scope->getType()
        ) ? 'listing-mapi' : 'listing';
        $this->setTemplate(template: "system/config/callback/$template.phtml");

        parent::__construct(context: $context);
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
