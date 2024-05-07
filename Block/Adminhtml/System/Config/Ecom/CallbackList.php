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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Resursbank\Ecom\Lib\Model\Callback\Enum\CallbackType;
use Resursbank\Ecom\Module\Callback\Widget\Callback;
use Resursbank\Ordermanagement\Helper\Ecom\Callback as CallbackHelper;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Core\Helper\Config;
use Resursbank\Core\Helper\Scope;
use Resursbank\Ecom\Config as EcomConfig;
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
     * @param Config $config
     * @param Scope $scope
     */
    public function __construct(
        Context $context,
        public readonly CallbackHelper $callbackHelper,
        public readonly Log $log,
        public readonly Config $config,
        public readonly Scope $scope
    ) {
        $this->setTemplate(
            template: 'Resursbank_Ordermanagement::system/config/callback-list.phtml'
        );

        parent::__construct($context);
    }

    /**
     * Fetch widget object.
     *
     * @return Callback|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getWidget(): ?Callback
    {
        $storeId = $this->config->getStore(
            scopeCode: $this->scope->getId(),
            scopeType: $this->scope->getType()
        );

        if ($storeId === '') {
            return null;
        }

        try {
            EcomConfig::validateInstance();

            return new Callback(
                authorizationUrl: $this->getCallbackUrl(
                    type: CallbackType::AUTHORIZATION->value
                ),
                managementUrl: $this->getCallbackUrl(
                    type: CallbackType::MANAGEMENT->value
                )
            );
        } catch (Throwable $error) {
            $this->log->exception(error: $error);
        }

        return null;
    }

    /**
     * Resolve callback URL.
     *
     * @param string $type
     * @return string|null
     */
    public function getCallbackUrl(string $type): ?string
    {
        $result = null;

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
