<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model\Ecom;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Validation\ValidationException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\Payment\Operations\SaleOperation;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Lib\Model\Callback\Authorization;
use Resursbank\Ecom\Lib\Model\Callback\Enum\Status;
use Resursbank\Ecom\Lib\Model\PaymentHistory\Entry;
use Resursbank\Ecom\Lib\Model\PaymentHistory\Event;
use Resursbank\Ecom\Lib\Model\PaymentHistory\User;
use Resursbank\Ecom\Module\PaymentHistory\Repository as EcomPaymentHistoryRepository;
use Resursbank\Ecom\Module\Callback\Http\AuthorizationController;
use Resursbank\Ecom\Module\Callback\Repository;
use Resursbank\Core\Helper\Config as CoreConfig;
use Resursbank\Core\Helper\Ecom;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Model\CallbackQueue;
use Resursbank\Ordermanagement\Helper\Config as OrdermanagementConfig;
use Resursbank\Core\Helper\PaymentMethods;
use Resursbank\Ordermanagement\Api\Ecom\CallbackInterface;
use Resursbank\Core\Helper\Order as OrderHelper;
use Resursbank\Ordermanagement\Helper\PaymentHistory as PaymentHistoryHelper;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\InventoryReservations\Model\ReservationBuilder;
use Magento\InventoryReservations\Model\ResourceModel\SaveMultiple;
use Throwable;

/**
 * Callback integration.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Callback implements CallbackInterface
{
    /**
     * @param Log $log
     * @param OrderHelper $orderHelper
     * @param CallbackQueue $callbackQueue
     * @param OrderSender $orderSender
     * @param CoreConfig $coreConfigHelper
     * @param OrderRepositoryInterface $orderRepository
     * @param DateTime $dateTime
     * @param OrdermanagementConfig $ordermanagementConfig
     * @param PaymentMethods $paymentMethods
     * @param PaymentHistoryHelper $paymentHistoryHelper
     * @param SaleOperation $saleOperation
     * @param ReservationBuilder $reservationBuilder
     * @param SaveMultiple $saveMultiple
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        private readonly Log $log,
        private readonly OrderHelper $orderHelper,
        private readonly CallbackQueue $callbackQueue,
        private readonly OrderSender $orderSender,
        private readonly CoreConfig $coreConfigHelper,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly DateTime $dateTime,
        private readonly OrdermanagementConfig $ordermanagementConfig,
        private readonly PaymentMethods $paymentMethods,
        private readonly PaymentHistoryHelper $paymentHistoryHelper,
        private readonly SaleOperation $saleOperation,
        private readonly ReservationBuilder $reservationBuilder,
        private readonly SaveMultiple $saveMultiple
    ) {
    }

    /**
     * Process incoming authorization callback.
     *
     * @throws WebapiException
     */
    public function authorization(): void
    {
        try {
            $callback = (new AuthorizationController())->getRequestData();

            $id = $this->getId(callback: $callback);

            if ($id === '') {
                throw new HttpException(
                    __('rb-callback-error-no-resource-id')->getText()
                );
            }

            $order = $this->orderHelper->getOrderFromPaymentId(paymentId: $id);

            if ($order === null) {
                throw new HttpException(
                    message: __('rb-callback-error-order-not-found'),
                    code: 503
                );
            }

            if (!$this->isReadyForCallback(order: $order, callback: $callback)) {
                throw new HttpException(
                    message: __('rb-called-error-order-not-ready')->getText(),
                    code: 503
                );
            }

            $code = Repository::process(
                callback: $callback,
                process: $this->getCallbackFunction()
            );
        } catch (Throwable $error) {
            $code = 503;
            $this->log->exception(error: $error);
        }

        if ($code > 299) {
            throw new WebapiException(
                phrase: __('rb-callback-error-failed-processing-authorization'),
                httpCode: $code
            );
        }
    }

    /**
     * Process incoming test callback.
     *
     * @throws WebapiException
     * @return void
     */
    public function test(): void
    {
        try {
            $this->callbackQueue->test(
                param1: '',
                param2: '',
                param3: '',
                param4: '',
                param5: ''
            );
        } catch (Throwable $error) {
            $this->log->exception(error: $error);

            throw new WebapiException(
                phrase: __('Failed to process test callback.'),
                httpCode: 503
            );
        }
    }

    /**
     * Generate callback function used for processing.
     *
     * @return callable
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getCallbackFunction(): callable
    {
        return function (
            Authorization $callback
        ): void {
            $order = $this->orderHelper->getOrderFromPaymentId(
                paymentId: $this->getId(callback: $callback)
            );

            if ($order === null) {
                throw new HttpException(
                    message: 'Order not found.',
                    code: 503
                );
            }

            $storeId = $order->getStoreId();

            // Handle rejected orders.
            if ($callback->status === Status::REJECTED &&
                $order->getState() === Order::STATE_CANCELED &&
                $this->coreConfigHelper->isReuseErroneouslyCreatedOrdersEnabled(
                    scopeCode: $storeId
                )
            ) {
                $this->cleanUpInventoryReservation(order: $order);
                $this->orderRepository->delete(entity: $order);
            }

            if ($callback->status !== Status::REJECTED &&
                $order instanceof Order &&
                !EcomPaymentHistoryRepository::hasExecuted(
                    paymentId: $this->getId(callback: $callback),
                    event: Event::CALLBACK_COMPLETED
                )
            ) {
                $this->orderSender->send(order: $order);
                $this->createInvoice(order: $order, callback: $callback);

                // Creating invoice affects order values (like state/status), save it to commit changes to DB.
                $this->orderRepository->save(entity: $order);
            }
        };
    }

    /**
     * Checks if the order is ready for callbacks.
     *
     * @param OrderInterface $order
     * @param Authorization $callback
     * @return bool
     * @throws ConfigException
     */
    private function isReadyForCallback(
        OrderInterface $order,
        Authorization $callback
    ): bool {
        $createdAt = $order->getCreatedAt();
        $createdAtTimestamp = strtotime(datetime: $createdAt);
        $currentTime = $this->dateTime->gmtTimestamp();
        $timeoutReached = $currentTime > $createdAtTimestamp + 60;

        return EcomPaymentHistoryRepository::hasExecuted(
            paymentId: $this->getId(callback: $callback),
            event: Event::REACHED_ORDER_SUCCESS_PAGE
        ) || $timeoutReached;
    }

    /**
     * Automatically creates and captures invoice.
     *
     * @param OrderInterface $order
     * @param Authorization $callback
     * @return void
     */
    private function createInvoice(OrderInterface $order, Authorization $callback): void
    {
        $this->log->info(text: 'Checking if order ' . $order->getIncrementId() .
            ' should be automatically captured.');

        try {
            if ($callback->status === Status::CAPTURED && $this->isAutomaticInvoiceEnabled(order: $order)) {
                EcomPaymentHistoryRepository::write(
                    entry: new Entry(
                        paymentId: $this->getId(callback: $callback),
                        event: Event::INVOICE_CREATED,
                        user: User::RESURSBANK
                    )
                );

                $this->log->info(text: 'Invoicing ' . $order->getIncrementId());
                $this->saleOperation->execute(payment: $order->getPayment());
            }
        } catch (Throwable $error) {
            $this->log->exception(error: $error);
        }
    }

    /**
     * Attempt to manually correct the inventory reservation state.
     *
     * @param OrderInterface $order
     * @return void
     * @throws \JsonException
     * @throws ValidationException
     */
    private function cleanUpInventoryReservation(OrderInterface $order): void
    {
        $incrementId = $order->getIncrementId();
        /** @var Item $item */
        foreach ($order->getAllVisibleItems() as $item) {
            if ($item->getQtyOrdered()) {
                $reservation = $this->reservationBuilder
                    ->setSku(sku: $item->getSku())
                    ->setQuantity(quantity: (float)$item->getQtyOrdered())
                    ->setStockId(stockId: 1)
                    ->setMetadata(metadata: json_encode(
                        value: [
                            'event_type' => 'order_place_failed',
                            'object_type' => 'order',
                            'object_id' => '',
                            'object_increment_id' => $incrementId
                        ],
                        flags: JSON_THROW_ON_ERROR
                    ))
                    ->build();
                $this->saveMultiple->execute(reservations: [$reservation]);
            }
        }
    }

    /**
     * Check if auto invoice is enabled for order.
     *
     * @param OrderInterface $order
     * @return bool
     * @throws ConfigException
     * @throws LocalizedException
     */
    private function isAutomaticInvoiceEnabled(OrderInterface $order): bool
    {
        return (
            $this->ordermanagementConfig->isAutoInvoiceEnabled(
                scopeCode: (string) $order->getStoreId()
            ) &&
            $order->getPayment() instanceof OrderPaymentInterface &&
            $this->paymentMethods->isResursBankMethod(
                code: $order->getPayment()->getMethod()
            ) &&
            (float) $order->getTotalInvoiced() === 0.0 &&
            !$this->paymentHistoryHelper->hasCreatedInvoice(order: $order)
        );
    }

    /**
     * Resolve checkout/payment id from callback.
     *
     * @param Authorization $callback
     * @return string
     */
    private function getId(
        Authorization $callback
    ): string {
        return $callback->checkoutId ?? $callback->paymentId;
    }
}