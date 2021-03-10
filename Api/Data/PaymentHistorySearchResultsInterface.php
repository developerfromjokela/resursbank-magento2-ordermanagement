<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\DataObject;

interface PaymentHistorySearchResultsInterface extends SearchResultsInterface
{
    /**
     * Set payment history list.
     *
     * @param array<PaymentHistoryInterface|DataObject> $items
     * @return self
     */
    public function setItems(
        array $items
    ): self;
}
