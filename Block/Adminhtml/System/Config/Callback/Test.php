<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Block\Adminhtml\System\Config\Callback;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;
use Resursbank\Core\Block\Adminhtml\System\Config\Button;

class Test extends Button
{
    /**
     * Get the button and scripts contents.
     *
     * @param AbstractElement $element
     * @return string
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     * @noinspection PhpMissingParentCallCommonInspection
     */
    protected function _getElementHtml(
        AbstractElement $element
    ): string {
        return $this->create(
            $element,
            'Perform test',
            'resursbank_ordermanagement/callback/test'
        );
    }
}
