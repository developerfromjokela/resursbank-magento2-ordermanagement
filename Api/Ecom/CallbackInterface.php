<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Api\Ecom;

/**
 * Implementation of callback methods.
 */
interface CallbackInterface
{
    /**
     * Process authorization callback.
     *
     * @return void
     */
    public function authorization(): void;

    /**
     * Process test callback.
     *
     * @return void
     */
    public function test(): void;
}
