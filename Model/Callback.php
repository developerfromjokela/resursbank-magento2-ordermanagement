<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model;

use Resursbank\Ordermanagement\Api\CallbackInterface;

class Callback implements CallbackInterface
{
    /**
     * @inheritDoc
     */
    public function unfreeze(string $paymentId, string $digest)
    {
        return 'Unfreeze';
    }

    /**
     * @inheritDoc
     */
    public function booked(string $paymentId, string $digest)
    {
        return 'Booked';
    }

    /**
     * @inheritDoc
     */
    public function update(string $paymentId, string $digest)
    {
        return 'Update';
    }

    /**
     * @inheritDoc
     */
    public function test(
        string $param1,
        string $param2,
        string $param3,
        string $param4,
        string $param5,
        string $digest
    ) {
        return 'Test';
    }
}
