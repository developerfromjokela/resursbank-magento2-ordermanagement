<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Plugin\Helper;

use Resursbank\Core\Helper\Ecom as Subject;
use Resursbank\Ecom\Module\PaymentHistory\DataHandler\DataHandlerInterface;
use Resursbank\Ordermanagement\Helper\PaymentHistoryDataHandler;

/**
 * Modify ECom init values.
 */
class Ecom
{
    /**
     * @param PaymentHistoryDataHandler $paymentHistoryDataHandler
     */
    public function __construct(
        private readonly PaymentHistoryDataHandler $paymentHistoryDataHandler
    ) {
    }

    /**
     * Overwrite payment history data handler to leverage Magento database.
     *
     * @param Subject $subject
     * @param DataHandlerInterface $result
     * @return DataHandlerInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetPaymentHistoryDataHandler(
        Subject $subject,
        DataHandlerInterface $result
    ): DataHandlerInterface {
        return $this->paymentHistoryDataHandler;
    }
}
