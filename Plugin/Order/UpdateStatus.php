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
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Helper\PaymentHistory;

class UpdateStatus implements ArgumentInterface
{
    /**
     * @var Log
     */
    private Log $log;

    /**
     * @var Order
     */
    private Order $order;

    /**
     * @var PaymentHistory
     */
    private PaymentHistory $phHelper;

    /**
     * @var PaymentMethods
     */
    private PaymentMethods $paymentMethods;

    /**
     * @param Log $log
     * @param Order $order
     * @param PaymentHistory $phHelper
     * @param PaymentMethods $paymentMethods
     */
    public function __construct(
        Log $log,
        Order $order,
        PaymentHistory $phHelper,
        PaymentMethods $paymentMethods
    ) {
        $this->log = $log;
        $this->order = $order;
        $this->phHelper = $phHelper;
        $this->paymentMethods = $paymentMethods;
    }

    /**
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
            $order = $this->order->resolveOrderFromRequest();

            if ($this->isEnabled($order)) {
                $this->phHelper->syncOrderStatus(
                    $this->order->resolveOrderFromRequest(),
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
