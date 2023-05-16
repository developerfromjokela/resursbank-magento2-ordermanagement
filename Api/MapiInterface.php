<?php

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Api;

use Magento\Framework\Webapi\Exception as WebapiException;
use Resursbank\Ordermanagement\Helper\Log as Log;

/**
 * MAPI callback interface.
 */
interface MapiInterface
{
    /**
     * Process authorization callback.
     *
     * @return void
     */
    public function authorization(): void;

    /**
     * @return void
     */
    public function test(): void;
}
