<?php

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Api;

use Magento\Framework\Webapi\Exception as WebapiException;
use Resursbank\Ordermanagement\Helper\Log;

interface CallbackQueueInterface
{
    /** @var int  */
    public const ENTITY_ID = 'id';

    /** @var string  */
    public const ENTITY_CREATED_AT = 'created_at';

    /** @var string  */
    public const ENTITY_TYPE = 'type';

    /** @var string  */
    public const ENTITY_PAYMENT_ID = 'payment_id';

    /** @var string  */
    public const ENTITY_DIGEST = 'digest';

    /**
     * Payment is unfrozen, which means it can be captured.
     *
     * @param string $paymentId
     * @param string $digest
     * @return void
     * @throws WebapiException
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
     * @throws WebapiException
     */
    public function booked(string $paymentId, string $digest): void;

    /**
     * Payment has been updated at Resursbank.
     *
     * @param string $paymentId
     * @param string $digest
     * @return void
     * @throws WebapiException
     */
    public function update(string $paymentId, string $digest): void;

    /**
     * Handles inbound callback test.
     *
     * Handling inbound callback test from Resurs Bank. Store values in config
     * table.
     *
     * @param string $param1
     * @param string $param2
     * @param string $param3
     * @param string $param4
     * @param string $param5
     * @return void
     * @throws WebapiException
     */
    public function test(
        string $param1,
        string $param2,
        string $param3,
        string $param4,
        string $param5
    ): void;
}
