<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model\PaymentHistory;

use Magento\Framework\Api\SearchResults as FrameworkSearchResults;
use Resursbank\Ordermanagement\Api\Data\PaymentHistorySearchResultsInterface;

class SearchResults extends FrameworkSearchResults implements PaymentHistorySearchResultsInterface
{
    /**
     * @inheritDoc
     */
    public function setItems(
        array $items
    ): PaymentHistorySearchResultsInterface {
        parent::setItems($items);

        return $this;
    }
}
