<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

interface PaymentHistorySearchResultsInterface extends SearchResultsInterface
{
    /**
     * Set payment history list.
     *
     * @param PaymentHistoryInterface[] $items
     * @return PaymentHistorySearchResultsInterface
     */
    public function setItems(
        array $items
    ): PaymentHistorySearchResultsInterface;
}
