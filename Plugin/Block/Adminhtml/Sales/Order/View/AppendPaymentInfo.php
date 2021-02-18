<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Plugin\Block\Adminhtml\Sales\Order\View;

use Exception;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Block\Adminhtml\Order\View\Info;
use Resursbank\Core\Model\Payment\Resursbank as ResursbankPayment;
use Resursbank\Ordermanagement\Block\Adminhtml\Sales\Order\View\Info\PaymentInformation as PaymentBlock;
use Resursbank\Ordermanagement\Helper\ApiPayment;
use Resursbank\Ordermanagement\Helper\Log;

/**
 * Prepends payment information (invoice information from Resurs Bank) to the
 * information block on the order view.
 *
 * See: Block\Adminhtml\Sales\Order\View\Info\PaymentInformation
 */
class AppendPaymentInfo
{
    /**
     * @var PaymentBlock
     */
    private $paymentBlock;

    /**
     * Log helper.
     *
     * @var Log
     */
    private $log;

    /**
     * Payment helper.
     *
     * @var ApiPayment
     */
    private $apiPayment;

    /**
     * @param PaymentBlock $paymentBlock
     * @param Log $log
     * @param ApiPayment $apiPayment
     */
    public function __construct(
        PaymentBlock $paymentBlock,
        Log $log,
        ApiPayment $apiPayment
    ) {
        $this->paymentBlock = $paymentBlock;
        $this->log = $log;
        $this->apiPayment = $apiPayment;
    }

    /**
     * Prepend checkout information to order information block.
     *
     * @param Info $subject
     * @param string $result
     * @return string
     * @throws Exception
     */
    public function afterToHtml(Info $subject, string $result): string
    {
        try {
            if ($this->isEnabled($subject->getOrder())) {
                $result = $this->paymentBlock->toHtml() . $result;
            }
        } catch (Exception $e) {
            $this->log->error($e);
        }

        return $result;
    }

    /**
     * Check whether or not this plugin should execute.
     *
     * @param OrderInterface $order
     * @return bool
     * @throws Exception
     */
    private function isEnabled(OrderInterface $order): bool
    {
        return $this->validate($order);
    }

    /**
     * Validate payment:
     *
     * 1) Selected payment method was provided by Resurs Bank.
     * 2) Order grand total is above 0.
     * 3) A payment matching the order actually exists at Resurs Bank.
     *
     * NOTE: Orders with a grand total of 0 won't get payments created at Resurs
     * Bank, we therefore avoid an unnecessary API call by including a separate
     * check for this since it's a much less expensive process.
     *
     * @param OrderInterface $order
     * @return bool
     * @throws Exception
     */
    public function validate(OrderInterface $order): bool
    {
        return is_string($order->getIncrementId()) && (
            $this->validateMethod($order) &&
            $order->getGrandTotal() > 0 &&
            $this->apiPayment->exists($order->getIncrementId())
        );
    }

    /**
     * Validate payment method:
     *
     * 1) Payment method must have been provided by Resurs Bank.
     *
     * @param OrderInterface $order
     * @return bool
     */
    public function validateMethod(OrderInterface $order): bool
    {
        $code = '';

        if ($order->getPayment() !== null) {
            $code = substr(
                $order->getPayment()->getMethod(),
                0,
                strlen(ResursbankPayment::CODE_PREFIX)
            );
        }

        return ($code === ResursbankPayment::CODE_PREFIX);
    }
}
