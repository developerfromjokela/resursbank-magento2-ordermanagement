<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Plugin\Order;

use Magento\Sales\Model\Order;
use Resursbank\Core\Helper\Order as OrderHelper;

/**
 * Prevent order confirmation email from being sent when the order is created.
 * We will submit the email when we receive callback BOOKED from Resurs Bank.
 */
class StopConfirmationEmail
{
    /**
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     * @param OrderHelper $orderHelper
     */
    public function __construct(
        OrderHelper $orderHelper
    ) {
        $this->orderHelper = $orderHelper;
    }

    /**
     * @param Order $subject
     * @param Order $result
     * @return Order
     * @noinspection PhpUnusedParameterInspection
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterBeforeSave(
        Order $subject,
        Order $result
    ): Order {
        if ($this->orderHelper->isNew($subject)) {
            $result->setCanSendNewEmailFlag(false);
        }

        return $result;
    }
}
