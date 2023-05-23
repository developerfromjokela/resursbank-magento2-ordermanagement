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
use Resursbank\Core\Helper\Config;
use Resursbank\Core\Helper\Scope;
use Resursbank\Core\Helper\Order as OrderHelper;
use Resursbank\Ecom\Module\Payment\Enum\Status;
use Resursbank\Ecom\Module\Payment\Repository as PaymentRepository;
use Resursbank\Ecommerce\Types\OrderStatus;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Api\PaymentHistoryRepositoryInterface;
use Resursbank\Ordermanagement\Exception\ResolveOrderStatusFailedException;
use Resursbank\Ordermanagement\Model\PaymentHistoryFactory;
use Throwable;

class PaymentHistory extends AbstractHelper
{
    /**
     * @param Context $context
     * @param PaymentHistoryFactory $paymentHistoryFactory
     * @param PaymentHistoryRepositoryInterface $paymentHistoryRepository
     * @param OrderRepositoryInterface $orderRepo
     * @param Api $api
     * @param Scope $scope
     * @param Config $config
     * @param OrderHelper $orderHelper
     * @param Log $logHelper
     */
    public function __construct(
        Context $context,
        private readonly PaymentHistoryFactory $paymentHistoryFactory,
        private readonly PaymentHistoryRepositoryInterface $paymentHistoryRepository,
        private readonly OrderRepositoryInterface $orderRepo,
        private readonly Api $api,
        private readonly Scope $scope,
        private readonly Config $config,
        private readonly OrderHelper $orderHelper,
        private readonly Log $logHelper
    ) {
        parent::__construct(context: $context);
    }

    /**
     * Sync order status from Resurs Bank.
     *
     * @param OrderInterface $order
     * @param string $event
     * @throws AlreadyExistsException
     * @throws ResolveOrderStatusFailedException
     * @throws LocalizedException
     * @throws Exception
     */
    public function syncOrderStatus(
        OrderInterface $order,
        string $event = ''
    ): void {
        $entry = $this->paymentHistoryFactory->create();
        $payment = $order->getPayment();

        if (!($payment instanceof OrderPaymentInterface)) {
            throw new LocalizedException(phrase: __(
                'Payment does not exist for order ' .
                $order->getIncrementId()
            ));
        }

        if (!$this->config->isMapiActive(scopeCode: $this->scope->getId(), scopeType: $this->scope->getType())) {
            $updatedOrder = $this->handleLegacyPaymentStatus(order: $order);
        } else {
            $updatedOrder = $this->handleMapiPaymentStatus(order: $order);
        }

        $entry
            ->setPaymentId(identifier: (int) $payment->getEntityId())
            ->setEvent(event: $event)
            ->setUser(user: PaymentHistoryInterface::USER_RESURS_BANK)
            ->setStateFrom(state: $order->getState())
            ->setStatusFrom(status: $order->getStatus());

        // Set entry status /state based on actual data from order.
        $entry->setStateTo(state: $updatedOrder->getState());
        $entry->setStatusTo(status: $updatedOrder->getStatus());

        $this->paymentHistoryRepository->save(entry: $entry);
    }

    /**
     * Handle status changes for legacy API orders.
     *
     * @param OrderInterface $order
     * @return OrderInterface
     * @throws ResolveOrderStatusFailedException
     */
    private function handleLegacyPaymentStatus(OrderInterface $order): OrderInterface
    {
        $paymentStatus = $this->getPaymentStatus(order: $order);
        $orderStatus = $this->paymentStatusToOrderStatus(paymentStatus: $paymentStatus);
        $orderState = $this->paymentStatusToOrderState(paymentStatus: $paymentStatus);

        $order->setStatus(status: $orderStatus);
        $order->setState(state: $orderState);

        $this->orderRepo->save(entity: $order);

        // Reload order from database.
        return $this->orderRepo->get(id: $order->getId());
    }

    /**
     * Handle status changes for MAPI orders.
     *
     * @param OrderInterface $order
     * @return OrderInterface
     */
    private function handleMapiPaymentStatus(OrderInterface $order): OrderInterface
    {
        try {
            $payment = PaymentRepository::get(paymentId: $this->orderHelper->getPaymentId(order: $order));
        } catch (Throwable $error) {
            $this->logHelper->error(text: $error->getMessage());
            return $order;
        }

        if ($order->isCanceled()) {
            return $order;
        }

        switch ($payment->status) {
            case Status::REJECTED:
                $this->orderHelper->cancelOrder(order: $order);
                $order->setState(state: Order::STATE_CANCELED);
                $order->setStatus(status: Order::STATE_CANCELED);
                $this->orderRepo->save(entity: $order);
                break;
            case Status::FROZEN:
                $order->setState(state: Order::STATE_PAYMENT_REVIEW);
                $order->setStatus(status: Order::STATE_PAYMENT_REVIEW);
                $this->orderRepo->save(entity: $order);
                break;
            case Status::ACCEPTED:
                $order->setStatus(status: ResursbankStatuses::CONFIRMED);
                $this->orderRepo->save(entity: $order);
                break;
        }

        return $this->orderRepo->get(id: $order->getId());
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
        $entry = $this->paymentHistoryFactory->create();

        /** @phpstan-ignore-next-line */
        $entry->setPaymentId((int) $data->getPayment()->getId())
            ->setEvent($event)
            ->setUser(PaymentHistoryInterface::USER_CLIENT);

        $this->paymentHistoryRepository->save($entry);
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
            credentials: $this->api->getCredentialsFromOrder($order)
        );

        return $connection->getOrderStatusByPayment(paymentIdOrPaymentObject: $order->getIncrementId());
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
                throw new ResolveOrderStatusFailedException(phrase: __(
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
                throw new ResolveOrderStatusFailedException(phrase: __(
                    sprintf(
                        'Order status (%s) could not be converted.',
                        $paymentStatus
                    )
                ));
        }

        return $result;
    }
}
