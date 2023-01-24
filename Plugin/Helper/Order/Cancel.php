<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Plugin\Helper\Order;

use Exception;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Resursbank\Core\Exception\InvalidDataException;
use Resursbank\Core\Helper\Order as Subject;
use Resursbank\Core\Helper\PaymentMethods;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Api\PaymentHistoryRepositoryInterface;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Model\PaymentHistoryFactory;

/**
 * Record a payment history event when an order has been canceled using the
 * Core module's helper method.
 *
 * @see Order::cancelOrder()
 */
class Cancel
{
    /**
     * @var PaymentHistoryFactory
     */
    private PaymentHistoryFactory $phFactory;

    /**
     * @var PaymentHistoryRepositoryInterface
     */
    private PaymentHistoryRepositoryInterface $phRepository;

    /**
     * @var Log
     */
    private Log $log;

    /**
     * @var PaymentMethods
     */
    private PaymentMethods $paymentMethods;

    /**
     * @param PaymentHistoryRepositoryInterface $phRepository
     * @param PaymentHistoryFactory $phFactory
     * @param Log $log
     */
    public function __construct(
        PaymentHistoryRepositoryInterface $phRepository,
        PaymentHistoryFactory $phFactory,
        Log $log,
        PaymentMethods $paymentMethods
    ) {
        $this->phRepository = $phRepository;
        $this->phFactory = $phFactory;
        $this->log = $log;
        $this->paymentMethods = $paymentMethods;
    }

    /**
     * @param Subject $subject
     * @param OrderInterface $result
     * @return OrderInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @noinspection PhpUnusedParameterInspection
     */
    public function afterCancelOrder(
        Subject $subject,
        OrderInterface $result
    ): OrderInterface {
        try {
            if ($this->paymentMethods->isResursBankOrder($result)) {
                $payment = $result->getPayment();

                if (!($payment instanceof OrderPaymentInterface)) {
                    throw new InvalidDataException(__(
                        'Payment does not exist for order ' .
                        $result->getIncrementId()
                    ));
                }

                /* @noinspection PhpUndefinedMethodInspection */
                $entry = $this->phFactory->create();
                $entry
                    ->setPaymentId((int)$payment->getEntityId())
                    ->setEvent(PaymentHistoryInterface::EVENT_ORDER_CANCELED)
                    ->setUser(PaymentHistoryInterface::USER_RESURS_BANK);

                $this->phRepository->save($entry);
            }
        } catch (Exception $e) {
            $this->log->exception($e);
        }

        return $result;
    }
}
