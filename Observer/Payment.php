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
use Resursbank\Core\Model\Api\Payment as ApiPayment;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Api\PaymentHistoryRepositoryInterface;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Model\PaymentHistoryFactory;
use function sprintf;

/**
 * Create payment history entry before and after the payment session has been
 * created.
 *
 * Create payment history entry before and after the payment has been booked.
 */
class Payment implements ObserverInterface
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
     * @inheritDoc
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            $orderPayment = $this->getOrderPayment($observer);

            if ($this->paymentMethods->isResursBankMethod($orderPayment->getMethod())) {
                $this->saveHistoryEntry(
                    (int) $orderPayment->getEntityId(),
                    $this->getBookPaymentStatus($observer),
                    $observer->getEvent()->getName()
                );
            }
        } catch (Exception $e) {
            $this->log->exception($e);
        }
    }

    /**
     * Get payment from order.
     *
     * @param Observer $observer
     * @return OrderPaymentInterface
     * @throws InvalidDataException
     */
    private function getOrderPayment(Observer $observer): OrderPaymentInterface
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
     * Fetch book payment status.
     *
     * The payment session will only be supplied when available (ie in events
     * dispatched after completing an API call).
     *
     * @param Observer $observer
     * @return string
     */
    private function getBookPaymentStatus(Observer $observer): string
    {
        $session = $observer->getData('paymentSession');

        return ($session instanceof ApiPayment) ?
            $session->getBookPaymentStatus() :
            '';
    }

    /**
     * Saves a history entry.
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
        $phEventName = $this->getPaymentHistoryEvent($eventName);

        if ($phEventName === '') {
            throw new InvalidDataException(__(sprintf(
                'No payment history event name found with observed event %s',
                $eventName
            )));
        }

        $entry->setPaymentId($paymentId)
            ->setEvent($this->getPaymentHistoryEvent($eventName))
            ->setUser(PaymentHistoryInterface::USER_RESURS_BANK)
            ->setExtra($paymentStatus);

        $this->phRepository->save($entry);
    }

    /**
     * Fetch payment history.
     *
     * @param string $eventName
     * @return string
     */
    private function getPaymentHistoryEvent(string $eventName): string
    {
        switch ($eventName) {
            case 'resursbank_book_signed_payment_before':
                $result = PaymentHistoryInterface::EVENT_PAYMENT_BOOK_SIGNED;
                break;

            case 'resursbank_book_signed_payment_after':
                $result = PaymentHistoryInterface::EVENT_PAYMENT_BOOK_SIGNED_COMPLETED;
                break;

            default:
                $result = '';
        }

        return $result;
    }
}
