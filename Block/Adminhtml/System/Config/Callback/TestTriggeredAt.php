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
use Resursbank\Core\Helper\Scope;
use Resursbank\Ordermanagement\Helper\Config;

class TestTriggeredAt extends Field
{
    /**
     * @var Scope
     */
    public $scope;

    /**
     * @var Config
     */
    private $config;

    /**
     * @param Context $context
     * @param Scope $scope
     * @param Config $config
     */
    public function __construct(
        Context $context,
        Scope $scope,
        Config $config
    ) {
        $this->scope = $scope;
        $this->config = $config;

        parent::__construct($context);
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
     * Render date when test callback was last requested.
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
        return $this->config->getCallbackTestTriggeredAt(
            $this->scope->getId(),
            $this->scope->getType()
        );
    }
}
