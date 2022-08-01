<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Plugin\Order;

use Magento\Sales\Model\Order;
use Resursbank\Core\Helper\Order as OrderHelper;
use Resursbank\Core\Helper\PaymentMethods;

/**
 * Prevent order confirmation email from being sent when the order is created.
 * We will submit the email when we receive callback BOOKED from Resurs Bank.
 */
class StopConfirmationEmail
{
    /**
     * @var OrderHelper
     */
    private OrderHelper $orderHelper;

    /**
     * @var PaymentMethods
     */
    private PaymentMethods $paymentMethods;

    /**
     * @param OrderHelper $orderHelper
     * @param PaymentMethods $paymentMethods
     */
    public function __construct(
        OrderHelper $orderHelper,
        PaymentMethods  $paymentMethods
    ) {
        $this->orderHelper = $orderHelper;
        $this->paymentMethods = $paymentMethods;
    }

    /**
     * @param Order $subject
     * @param Order $result
     * @return Order
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterBeforeSave(
        Order $subject,
        Order $result
    ): Order {
        $method = $subject->getPayment() !== null ?
            $subject->getPayment()->getMethod() :
            '';
        
        if ($this->orderHelper->isNew($subject) &&
            $this->paymentMethods->isResursBankMethod($method)
        ) {
            $result->setCanSendNewEmailFlag(false);
        }

        return $result;
    }
}
