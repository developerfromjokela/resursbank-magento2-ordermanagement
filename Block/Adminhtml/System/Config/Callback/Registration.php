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

class Registration extends Button
{
    /**
     * Get the button and scripts contents.
     *
     * @param AbstractElement $element
     * @return string
     * @throws LocalizedException
     * @codingStandardsIgnoreStart (suppress unavoidable PHPCS warnings)
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        // @codingStandardsIgnoreEnd
        return $this->create(
            $element,
            'Update callbacks',
            'resursbank_ordermanagement/callback/registration'
        );
    }
}
