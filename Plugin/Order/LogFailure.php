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
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Api\PaymentHistoryRepositoryInterface;
use Resursbank\Ordermanagement\Model\PaymentHistoryFactory;

/**
 * Create payment history entry indicating client reach order failure page.
 */
class LogFailure implements ArgumentInterface
{
    /**
     * @var Log
     */
    private Log $log;

    /**
     * @var PaymentHistoryFactory
     */
    private PaymentHistoryFactory $phFactory;

    /**
     * @var PaymentHistoryRepositoryInterface
     */
    private PaymentHistoryRepositoryInterface $phRepository;

    /**
     * @var OrderHelper
     */
    private OrderHelper $orderHelper;

    /**
     * @param Log $log
     * @param PaymentHistoryFactory $phFactory
     * @param PaymentHistoryRepositoryInterface $phRepository
     * @param OrderHelper $orderHelper
     */
    public function __construct(
        Log $log,
        PaymentHistoryFactory $phFactory,
        PaymentHistoryRepositoryInterface $phRepository,
        OrderHelper $orderHelper
    ) {
        $this->log = $log;
        $this->phFactory = $phFactory;
        $this->phRepository = $phRepository;
        $this->orderHelper = $orderHelper;
    }

    /**
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
            $order = $this->orderHelper->resolveOrderFromRequest();

            if ($this->orderHelper->getResursbankResult($order) === null) {
                $this->phRepository->save(
                    $this->createHistoryEntry(
                        $this->getPaymentId(
                            $this->orderHelper->resolveOrderFromRequest()
                        )
                    )
                );
            }
        } catch (Exception $e) {
            $this->log->exception($e);
        }

        return $result;
    }

    /**
     * @param OrderInterface $order
     * @return int
     * @throws InvalidDataException
     */
    private function getPaymentId(OrderInterface $order): int
    {
        $payment = $order->getPayment();

        if (!($payment instanceof OrderPaymentInterface)) {
            throw new InvalidDataException(__(
                'Payment does not exist for order ' .
                $order->getIncrementId()
            ));
        }

        return (int) $payment->getEntityId();
    }

    /**
     * @param int $paymentId
     * @return PaymentHistoryInterface
     */
    private function createHistoryEntry(int $paymentId): PaymentHistoryInterface
    {
        /* @noinspection PhpUndefinedMethodInspection */
        $entry = $this->phFactory->create();
        $entry
            ->setPaymentId($paymentId)
            ->setEvent(PaymentHistoryInterface::EVENT_REACHED_ORDER_FAILURE)
            ->setUser(PaymentHistoryInterface::USER_RESURS_BANK);

        return $entry;
    }
}
