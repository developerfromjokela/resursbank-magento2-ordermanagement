<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Plugin\Order;

use Exception;
use Magento\Checkout\Controller\Onepage\Success;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Resursbank\Core\Helper\Log;
use Resursbank\Core\Helper\Order;
use Resursbank\Core\Helper\PaymentMethods;
use Resursbank\Core\ViewModel\Session\Checkout;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Helper\PaymentHistory;

class UpdateStatus implements ArgumentInterface
{
    /**
     * @param Log $log
     * @param Order $order
     * @param PaymentHistory $phHelper
     * @param PaymentMethods $paymentMethods
     * @param Checkout $checkout
     */
    public function __construct(
        private readonly Log $log,
        private readonly Order $order,
        private readonly PaymentHistory $phHelper,
        private readonly PaymentMethods $paymentMethods,
        private readonly Checkout $checkout
    ) {
    }

    /**
     * Log order success page reached,
     *
     * @param Success $subject
     * @param ResultInterface $result
     * @return ResultInterface
     * @noinspection PhpUnusedParameterInspection
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(
        Success $subject,
        ResultInterface $result
    ): ResultInterface {
        try {
            $order = $this->order->resolveOrderFromRequest(
                lastRealOrder: $this->checkout->getLastRealOrder()
            );

            if ($this->isEnabled($order)) {
                $this->phHelper->syncOrderStatus(
                    $order,
                    PaymentHistoryInterface::EVENT_REACHED_ORDER_SUCCESS
                );
            }
        } catch (Exception $e) {
            $this->log->exception($e);
        }

        return $result;
    }

    /**
     * Check if this plugin is enabled.
     *
     * @param OrderInterface $order
     * @return bool
     */
    private function isEnabled(OrderInterface $order): bool
    {
        return (
            $this->paymentMethods->isResursBankOrder($order) &&
            $this->order->getResursbankResult($order) === null
        );
    }
}
