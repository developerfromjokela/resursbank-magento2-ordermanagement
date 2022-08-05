<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Api;

use Exception;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Resursbank\Ordermanagement\Api\Data\PaymentInterface;

interface PaymentRepositoryInterface
{
    /**
     * Get entry by ID.
     *
     * @param int $identifier
     * @return PaymentInterface
     * @throws LocalizedException
     */
    public function get(int $identifier): PaymentInterface;

    /**
     * Get entry by Resurs Bank payment id (order increment id).
     *
     * @param string $reference
     * @return PaymentInterface
     * @throws LocalizedException
     */
    public function getByReference(string $reference): PaymentInterface;

    /**
     * Save (update / create) entry.
     *
     * @param PaymentInterface $entry
     * @return PaymentInterface
     * @throws Exception
     * @throws AlreadyExistsException
     */
    public function save(
        PaymentInterface $entry
    ): PaymentInterface;

    /**
     * Delete entry.
     *
     * @param PaymentInterface $entry
     * @return bool
     * @throws Exception
     * @throws LocalizedException
     */
    public function delete(PaymentInterface $entry): bool;
}
