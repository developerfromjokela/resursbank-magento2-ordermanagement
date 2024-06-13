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
use Resursbank\Core\Helper\Order;
use Resursbank\Core\Helper\PaymentMethods;
use Resursbank\Core\Model\Api\Payment as ApiPayment;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Api\PaymentHistoryRepositoryInterface;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Model\PaymentHistoryFactory;
use function sprintf;

/**
 * Create payment history entry before & after payment session has been created.
 *
 * Create payment history entry before and after the payment has been booked.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Payment implements ObserverInterface
{
    /**
     * @param PaymentHistoryRepositoryInterface $phRepository
     * @param PaymentHistoryFactory $phFactory
     * @param Log $log
     * @param PaymentMethods $paymentMethods
     * @param Order $order
     */
    public function __construct(
        private readonly PaymentHistoryRepositoryInterface $phRepository,
        private readonly PaymentHistoryFactory $phFactory,
        private readonly Log $log,
        private readonly PaymentMethods $paymentMethods,
        private readonly Order $order
    ) {
    }

    /**
     * Observer execution entry point.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            $orderPayment = $this->getOrderPayment(observer: $observer);

            if ($this->order->isLegacyFlow(order: $orderPayment->getOrder()) &&
                $this->paymentMethods->isResursBankMethod(
                    code: $orderPayment->getMethod()
                )
            ) {
                $this->saveHistoryEntry(
                    paymentId: (int) $orderPayment->getEntityId(),
                    paymentStatus: $this->getBookPaymentStatus(observer: $observer),
                    eventName: $observer->getEvent()->getName()
                );
            }
        } catch (Exception $e) {
            $this->log->exception(error: $e);
        }
    }

    /**
     * Get payment data from order.
     *
     * @param Observer $observer
     * @return OrderPaymentInterface
     * @throws InvalidDataException
     */
    private function getOrderPayment(Observer $observer): OrderPaymentInterface
    {
        /** @var OrderInterface $order */
        $order = $observer->getData(key: 'order');

        if (!($order instanceof OrderInterface)) {
            throw new InvalidDataException(
                phrase: __(
                    'rb-order-could-not-be-retrieved-from-subject'
                )
            );
        }

        $payment = $order->getPayment();

        if (!($payment instanceof OrderPaymentInterface)) {
            throw new InvalidDataException(
                phrase: __(
                    'rb-payment-does-not-exist-for-order',
                    $order->getIncrementId()
                )
            );
        }

        return $payment;
    }

    /**
     * Get book payment status.
     *
     * The payment session will only be supplied when available (ie in events
     * dispatched after completing an API call).
     *
     * @param Observer $observer
     * @return string
     */
    private function getBookPaymentStatus(Observer $observer): string
    {
        $session = $observer->getData(key: 'paymentSession');

        return ($session instanceof ApiPayment) ?
            $session->getBookPaymentStatus() :
            '';
    }

    /**
     * Save history entry.
     *
     * @param int $paymentId
     * @param string $paymentStatus
     * @param string $eventName
     * @return void
     * @throws AlreadyExistsException
     * @throws InvalidDataException
     */
    private function saveHistoryEntry(
        int $paymentId,
        string $paymentStatus,
        string $eventName
    ): void {
        $entry = $this->phFactory->create();
        $phEventName = $this->getPaymentHistoryEvent(eventName: $eventName);

        if ($phEventName === '') {
            throw new InvalidDataException(
                phrase: __(
                    'rb-no-payment-history-event-name-found',
                    $eventName
                )
            );
        }

        $entry->setPaymentId(identifier: $paymentId)
            ->setEvent(event: $this->getPaymentHistoryEvent(
                eventName: $eventName
            ))
            ->setUser(user: PaymentHistoryInterface::USER_RESURS_BANK)
            ->setExtra(extra: $paymentStatus);

        $this->phRepository->save(entry: $entry);
    }

    /**
     * Get payment history event.
     *
     * @param string $eventName
     * @return string
     */
    private function getPaymentHistoryEvent(string $eventName): string
    {
        return match ($eventName) {
            'resursbank_book_signed_payment_before' => PaymentHistoryInterface::EVENT_PAYMENT_BOOK_SIGNED,
            'resursbank_book_signed_payment_after' => PaymentHistoryInterface::EVENT_PAYMENT_BOOK_SIGNED_COMPLETED,
            default => '',
        };
    }
}
