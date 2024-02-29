<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Api;

use Exception;

interface CallbackInterface
{
    /**
     * Payment is unfrozen, which means it can be captured.
     *
     * @param string $paymentId
     * @param string $digest
     * @return void
     * @throws Exception
     */
    public function unfreeze(string $paymentId, string $digest): void;

    /**
     * Called when payment has been booked.
     *
     * Payment has been booked by Resursbank. This means the payment has been
     * unfrozen and is preparing to be finalized.
     *
     * @param string $paymentId
     * @param string $digest
     * @return void
     * @throws Exception
     */
    public function booked(string $paymentId, string $digest): void;

    /**
     * Payment has been updated at Resursbank.
     *
     * @param string $paymentId
     * @param string $digest
     * @return void
     * @throws Exception
     */
    public function update(string $paymentId, string $digest): void;
}
