<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Api;

use Exception;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Api\Data\PaymentHistorySearchResultsInterface;

interface PaymentHistoryRepositoryInterface
{
    /**
     * Save (update / create) entry.
     *
     * @param PaymentHistoryInterface $entry
     * @return PaymentHistoryInterface
     * @throws Exception
     * @throws AlreadyExistsException
     */
    public function save(
        PaymentHistoryInterface $entry
    ): PaymentHistoryInterface;

    /**
     * Get entry by ID.
     *
     * @param int $identifier
     * @return PaymentHistoryInterface
     * @throws LocalizedException
     */
    public function get(int $identifier): PaymentHistoryInterface;

    /**
     * Retrieve entries matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return PaymentHistorySearchResultsInterface
     * @throws LocalizedException
     */
    public function getList(
        SearchCriteriaInterface $searchCriteria
    ): PaymentHistorySearchResultsInterface;

    /**
     * Delete entry.
     *
     * @param PaymentHistoryInterface $entry
     * @return bool
     * @throws Exception
     * @throws LocalizedException
     */
    public function delete(PaymentHistoryInterface $entry): bool;

    /**
     * Delete entry by ID.
     *
     * @param int $identifier
     * @return bool
     * @throws LocalizedException
     */
    public function deleteById(int $identifier): bool;
}
