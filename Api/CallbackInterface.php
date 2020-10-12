<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Api;

/**
 * @package Resursbank\Ordermanagement\Api
 */
interface CallbackInterface
{
    /**
     * Payment is unfrozen, which means it can be captured.
     *
     * @param string $paymentId
     * @param string $digest
     * @return void
     */
    public function unfreeze(string $paymentId, string $digest): void;

    /**
     * Payment has been booked by Resursbank. This means the payment has been
     * unfrozen and is preparing to be finalized.
     *
     * @param string $paymentId
     * @param string $digest
     * @return void
     */
    public function booked(string $paymentId, string $digest): void;

    /**
     * Payment has been updated at Resursbank.
     *
     * @param string $paymentId
     * @param string $digest
     * @return void
     */
    public function update(string $paymentId, string $digest): void;

    /**
     * Handling inbound callback test from Resurs Bank. Store values in config
     * table.
     *
     * @param string $param1
     * @param string $param2
     * @param string $param3
     * @param string $param4
     * @param string $param5
     * @param string $digest
     * @return void
     */
    public function test(
        string $param1,
        string $param2,
        string $param3,
        string $param4,
        string $param5,
        string $digest
    ): void;
}
