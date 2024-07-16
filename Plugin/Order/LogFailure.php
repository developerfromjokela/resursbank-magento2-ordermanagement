<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Plugin\Order;

use Exception;
use Magento\Checkout\Controller\Onepage\Failure;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Resursbank\Core\Exception\InvalidDataException;
use Resursbank\Core\Helper\Log;
use Resursbank\Core\Helper\Order as OrderHelper;
use Resursbank\Core\Helper\PaymentMethods;
use Resursbank\Core\ViewModel\Session\Checkout;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Api\PaymentHistoryRepositoryInterface;
use Resursbank\Ordermanagement\Model\PaymentHistoryFactory;

/**
 * Create payment history entry indicating client reach order failure page.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class LogFailure implements ArgumentInterface
{
    /**
     * Constructor.
     *
     * @param Log $log
     * @param PaymentHistoryFactory $phFactory
     * @param PaymentHistoryRepositoryInterface $phRepository
     * @param OrderHelper $orderHelper
     * @param PaymentMethods $paymentMethods
     * @param Checkout $checkout
     */
    public function __construct(
        private readonly Log $log,
        private readonly PaymentHistoryFactory $phFactory,
        private readonly PaymentHistoryRepositoryInterface $phRepository,
        private readonly OrderHelper $orderHelper,
        private readonly PaymentMethods $paymentMethods,
        private readonly Checkout $checkout
    ) {
    }

    /**
     * Log failure event.
     *
     * @param Failure $subject
     * @param ResultInterface $result
     * @return ResultInterface
     * @noinspection PhpUnusedParameterInspection
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(
        Failure $subject,
        ResultInterface $result
    ): ResultInterface {
        try {
            $order = $this->orderHelper->resolveOrderFromRequest(
                lastRealOrder: $this->checkout->getLastRealOrder()
            );

            if ($this->isEnabled(order: $order)) {
                $this->phRepository->save(
                    entry: $this->createHistoryEntry(
                        paymentId: $this->getPaymentId(order: $order)
                    )
                );
            }
        } catch (Exception $e) {
            $this->log->exception(error: $e);
        }

        return $result;
    }

    /**
     * Get payment id.
     *
     * @param OrderInterface $order
     * @return int
     * @throws InvalidDataException
     */
    private function getPaymentId(OrderInterface $order): int
    {
        $payment = $order->getPayment();

        if (!($payment instanceof OrderPaymentInterface)) {
            throw new InvalidDataException(
                phrase: __(
                    'rb-payment-does-not-exist-for-order',
                    $order->getIncrementId()
                )
            );
        }

        return (int) $payment->getEntityId();
    }

    /**
     * Create history entry.
     *
     * @param int $paymentId
     * @return PaymentHistoryInterface
     */
    private function createHistoryEntry(int $paymentId): PaymentHistoryInterface
    {
        $entry = $this->phFactory->create();
        $entry
            ->setPaymentId(identifier: $paymentId)
            ->setEvent(event: PaymentHistoryInterface::EVENT_REACHED_ORDER_FAILURE)
            ->setUser(user: PaymentHistoryInterface::USER_RESURS_BANK);

        return $entry;
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
            $this->orderHelper->isLegacyFlow(order: $order) &&
            $this->paymentMethods->isResursBankOrder(order: $order) &&
            $this->orderHelper->getResursbankResult(order: $order) === null
        );
    }
}
