<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Helper;

use Exception;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Resursbank\Core\Helper\Api;
use Resursbank\Ecommerce\Types\OrderStatus;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Api\PaymentHistoryRepositoryInterface;
use Resursbank\Ordermanagement\Exception\ResolveOrderStatusFailedException;
use Resursbank\Ordermanagement\Model\PaymentHistoryFactory;

class PaymentHistory extends AbstractHelper
{
    /**
     * @var Api
     */
    private Api $api;

    /**
     * @var PaymentHistoryFactory
     */
    private PaymentHistoryFactory $phFactory;

    /**
     * @var PaymentHistoryRepositoryInterface
     */
    private PaymentHistoryRepositoryInterface $phRepository;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepo;

    /**
     * @param Context $context
     * @param PaymentHistoryFactory $phFactory
     * @param PaymentHistoryRepositoryInterface $phRepository
     * @param OrderRepositoryInterface $orderRepo
     * @param Api $api
     */
    public function __construct(
        Context $context,
        PaymentHistoryFactory $phFactory,
        PaymentHistoryRepositoryInterface $phRepository,
        OrderRepositoryInterface $orderRepo,
        Api $api
    ) {
        $this->phFactory = $phFactory;
        $this->phRepository = $phRepository;
        $this->orderRepo = $orderRepo;
        $this->api = $api;

        parent::__construct($context);
    }

    /**
     * @throws AlreadyExistsException
     * @throws ResolveOrderStatusFailedException
     * @throws LocalizedException
     * @throws Exception
     */
    public function syncOrderStatus(
        OrderInterface $order,
        string $event = ''
    ): void {
        $entry = $this->phFactory->create();
        $payment = $order->getPayment();

        if (!($payment instanceof OrderPaymentInterface)) {
            throw new LocalizedException(__(
                'Payment does not exist for order ' .
                $order->getIncrementId()
            ));
        }

        $paymentStatus = $this->getPaymentStatus($order);
        $orderStatus = $this->paymentStatusToOrderStatus($paymentStatus);
        $orderState = $this->paymentStatusToOrderState($paymentStatus);

        $entry
            ->setPaymentId((int) $payment->getEntityId())
            ->setEvent($event)
            ->setUser(PaymentHistoryInterface::USER_RESURS_BANK)
            ->setStateFrom($order->getState())
            ->setStatusFrom($order->getStatus());

        $order->setStatus($orderStatus);
        $order->setState($orderState);

        $this->orderRepo->save($order);

        // Reload order from database.
        $updatedOrder = $this->orderRepo->get($order->getId());

        // Set entry status /state based on actual data from order.
        $entry->setStateTo($updatedOrder->getState());
        $entry->setStatusTo($updatedOrder->getStatus());

        $this->phRepository->save($entry);
    }

    /**
     * Create payment history entry from command subject data.
     *
     * @param PaymentDataObjectInterface $data
     * @param string $event
     * @throws AlreadyExistsException
     */
    public function entryFromCmd(
        PaymentDataObjectInterface $data,
        string $event
    ): void {
        $entry = $this->phFactory->create();

        /** @phpstan-ignore-next-line */
        $entry->setPaymentId((int) $data->getPayment()->getId())
            ->setEvent($event)
            ->setUser(PaymentHistoryInterface::USER_CLIENT);

        $this->phRepository->save($entry);
    }

    /**
     * Fetch Resurs Bank payment status.
     *
     * @param OrderInterface $order
     * @return int
     * @throws Exception
     */
    public function getPaymentStatus(OrderInterface $order): int
    {
        $connection = $this->api->getConnection(
            $this->api->getCredentialsFromOrder($order)
        );

        return $connection->getOrderStatusByPayment($order->getIncrementId());
    }

    /**
     * Converts a Resurs Bank payment status to a Magento order state.
     *
     * @param int $paymentStatus
     * @return string
     * @throws ResolveOrderStatusFailedException
     */
    public function paymentStatusToOrderState(int $paymentStatus): string
    {
        /* NOTE: Order state defines what actions are available for an order.
        payment_review for example will disable all order controls, like
        invoice, shipment etc. Which is why we need to change it depending on
        the status of our payment at Resurs Bank. */
        switch ($paymentStatus) {
            case OrderStatus::PENDING:
                $result = Order::STATE_PAYMENT_REVIEW;
                break;
            case OrderStatus::PROCESSING:
                $result = Order::STATE_PENDING_PAYMENT;
                break;
            case OrderStatus::COMPLETED:
                $result = Order::STATE_PROCESSING;
                break;
            case OrderStatus::ANNULLED:
                $result = Order::STATE_CANCELED;
                break;
            case OrderStatus::CREDITED:
                $result = Order::STATE_CLOSED;
                break;
            default:
                throw new ResolveOrderStatusFailedException(__(
                    sprintf(
                        'Order state (%s) could not be converted.',
                        $paymentStatus
                    )
                ));
        }

        return $result;
    }

    /**
     * Converts a Resurs Bank payment status to a Magento order status.
     *
     * @param int $paymentStatus
     * @return string
     * @throws ResolveOrderStatusFailedException
     */
    public function paymentStatusToOrderStatus(int $paymentStatus): string
    {
        switch ($paymentStatus) {
            case OrderStatus::PENDING:
                $result = ResursbankStatuses::PAYMENT_REVIEW;
                break;
            case OrderStatus::PROCESSING:
                $result = ResursbankStatuses::CONFIRMED;
                break;
            case OrderStatus::COMPLETED:
                $result = ResursbankStatuses::FINALIZED;
                break;
            case OrderStatus::ANNULLED:
                $result = ResursbankStatuses::CANCELLED;
                break;
            case OrderStatus::CREDITED:
                $result = Order::STATE_CLOSED;
                break;
            default:
                throw new ResolveOrderStatusFailedException(__(
                    sprintf(
                        'Order status (%s) could not be converted.',
                        $paymentStatus
                    )
                ));
        }

        return $result;
    }
}
