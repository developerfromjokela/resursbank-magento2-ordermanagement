<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Observer;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Resursbank\Core\Exception\InvalidDataException;
use Resursbank\Core\Helper\PaymentMethods;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Api\PaymentHistoryRepositoryInterface;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Model\PaymentHistoryFactory;
use function constant;

/**
 * Create payment history entry before the customer is redirected to gateway.
 */
class RedirectToGateway implements ObserverInterface
{
    /**
     * @var Log
     */
    private Log $log;

    /**
     * @var PaymentHistoryRepositoryInterface
     */
    private PaymentHistoryRepositoryInterface $phRepository;

    /**
     * @var PaymentHistoryFactory
     */
    private PaymentHistoryFactory $phFactory;

    /**
     * @var PaymentMethods
     */
    private PaymentMethods $paymentMethods;

    /**
     * @param PaymentHistoryRepositoryInterface $phRepository
     * @param PaymentHistoryFactory $phFactory
     * @param Log $log
     * @param PaymentMethods $paymentMethods
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
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            $payment = $this->getPayment($observer);

            if ($this->paymentMethods->isResursBankMethod($payment->getMethod())) {
                $this->saveHistoryEntry((int) $payment->getEntityId());
            }
        } catch (Exception $e) {
            $this->log->exception($e);
        }
    }

    /**
     * @param Observer $observer
     * @return OrderPaymentInterface
     * @throws InvalidDataException
     */
    public function getPayment(Observer $observer): OrderPaymentInterface
    {
        /** @var OrderInterface $order */
        $order = $observer->getData('order');

        if (!($order instanceof OrderInterface)) {
            throw new InvalidDataException(__(
                'Order could not be retrieved from the observed subject\'s ' .
                'data.'
            ));
        }

        $payment = $order->getPayment();

        if (!($payment instanceof OrderPaymentInterface)) {
            throw new InvalidDataException(__(
                'Payment does not exist for order ' . $order->getIncrementId()
            ));
        }

        return $payment;
    }

    /**
     * @param int $paymentId
     * @return void
     * @throws AlreadyExistsException
     */
    public function saveHistoryEntry(int $paymentId): void
    {
        $entry = $this->phFactory->create();
        $entry
            ->setPaymentId($paymentId)
            ->setEvent(constant(sprintf(
                '%s::%s',
                PaymentHistoryInterface::class,
                'EVENT_GATEWAY_REDIRECTED_TO'
            )))
            ->setUser(PaymentHistoryInterface::USER_RESURS_BANK);

        $this->phRepository->save($entry);
    }
}
