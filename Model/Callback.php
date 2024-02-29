<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model;

use function constant;
use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Resursbank\Core\Helper\Order as OrderHelper;
use Resursbank\Core\Helper\Scope;
use Resursbank\Ordermanagement\Api\CallbackInterface;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Exception\CallbackValidationException;
use Resursbank\Ordermanagement\Exception\OrderNotFoundException;
use Resursbank\Ordermanagement\Helper\Callback as CallbackHelper;
use Resursbank\Ordermanagement\Helper\CallbackLog;
use Resursbank\Ordermanagement\Helper\Config as ConfigHelper;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Api\PaymentHistoryRepositoryInterface;
use Resursbank\Ordermanagement\Helper\PaymentHistory;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @noinspection EfferentObjectCouplingInspection
 */
class Callback implements CallbackInterface
{
    /**
     * @var PaymentHistoryFactory
     */
    private PaymentHistoryFactory $phFactory;

    /**
     * @var CallbackHelper
     */
    private CallbackHelper $callbackHelper;

    /**
     * @var Log
     */
    private Log $log;

    /**
     * @var CallbackLog
     */
    private CallbackLog $callbackLog;

    /**
     * @var OrderInterface
     */
    private OrderInterface $orderInterface;

    /**
     * @var ConfigHelper
     */
    private ConfigHelper $config;

    /**
     * @var OrderSender
     */
    private OrderSender $orderSender;

    /**
     * @var PaymentHistoryRepositoryInterface
     */
    private PaymentHistoryRepositoryInterface $phRepository;

    /**
     * @var Scope
     */
    private Scope $scope;

    /**
     * @var SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $searchBuilder;

    /**
     * @var TypeListInterface
     */
    private TypeListInterface $cacheTypeList;

    /**
     * @var PaymentHistory
     */
    private PaymentHistory $phHelper;

    /**
     * @var OrderHelper
     */
    private OrderHelper $orderHelper;

    /**
     * @param CallbackHelper $callbackHelper
     * @param ConfigHelper $config
     * @param Log $log
     * @param CallbackLog $callbackLog
     * @param OrderInterface $orderInterface
     * @param OrderSender $orderSender
     * @param PaymentHistoryRepositoryInterface $phRepository
     * @param Scope $scope
     * @param SearchCriteriaBuilder $searchBuilder
     * @param TypeListInterface $cacheTypeList
     * @param PaymentHistory $phHelper
     * @param PaymentHistoryFactory $phFactory
     * @param OrderHelper $orderHelper
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        CallbackHelper $callbackHelper,
        ConfigHelper $config,
        Log $log,
        CallbackLog $callbackLog,
        OrderInterface $orderInterface,
        OrderSender $orderSender,
        PaymentHistoryRepositoryInterface $phRepository,
        Scope $scope,
        SearchCriteriaBuilder $searchBuilder,
        TypeListInterface $cacheTypeList,
        PaymentHistory $phHelper,
        PaymentHistoryFactory $phFactory,
        OrderHelper $orderHelper
    ) {
        $this->callbackHelper = $callbackHelper;
        $this->config = $config;
        $this->log = $log;
        $this->callbackLog = $callbackLog;
        $this->orderInterface = $orderInterface;
        $this->orderSender = $orderSender;
        $this->phRepository = $phRepository;
        $this->searchBuilder = $searchBuilder;
        $this->scope = $scope;
        $this->cacheTypeList = $cacheTypeList;
        $this->phHelper = $phHelper;
        $this->phFactory = $phFactory;
        $this->orderHelper = $orderHelper;
    }

    /**
     * @inheritDoc
     */
    public function unfreeze(
        string $paymentId,
        string $digest
    ): void {
        try {
            $this->execute(type: 'unfreeze', paymentId: $paymentId, digest: $digest);
        } catch (Exception $e) {
            $this->handleError(exception: $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function booked(
        string $paymentId,
        string $digest
    ): void {
        try {
            $order = $this->execute(type: 'booked', paymentId: $paymentId, digest: $digest);

            // Send order confirmation email if this is first BOOKED.
            if (!$this->receivedCallback(order: $order)) {
                $this->orderSender->send(order: $order);
            }
        } catch (Exception $e) {
            $this->handleError(exception: $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function update(
        string $paymentId,
        string $digest
    ): void {
        try {
            $this->execute(type: 'update', paymentId: $paymentId, digest: $digest);
        } catch (Exception $e) {
            $this->handleError(exception: $e);
        }
    }

    /**
     * General callback instructions.
     *
     * @param string $type
     * @param string $paymentId
     * @param string $digest
     * @return Order
     * @throws CallbackValidationException
     * @throws FileSystemException
     * @throws OrderNotFoundException
     * @throws RuntimeException
     * @throws ValidatorException
     * @throws AlreadyExistsException
     * @throws LocalizedException
     * @throws Exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function execute(
        string $type,
        string $paymentId,
        string $digest
    ): Order {
        // Required for PHPStan to validate that loadByIncrementId() exists as
        // a method.
        if (!($this->orderInterface instanceof Order)) {
            throw new LocalizedException(
                phrase: __('orderInterface not an instance of Order')
            );
        }

        /** @var Order $order */
        $order = $this->orderInterface->loadByIncrementId(incrementId: $paymentId);

        if (!$order->getId()) {
            throw new OrderNotFoundException(
                phrase: __('Failed to locate order ' . $paymentId)
            );
        }

        $payment = $order->getPayment();

        $entry = $this->phFactory->create();
        $entry
            ->setPaymentId(identifier: (int) $payment->getEntityId())
            ->setEvent(event: constant(name: sprintf(
                '%s::%s',
                PaymentHistoryInterface::class,
                'EVENT_CALLBACK_' . strtoupper(string: $type)
            )))
            ->setUser(user: PaymentHistoryInterface::USER_RESURS_BANK);

        $this->phRepository->save(entry: $entry);

        $orderStatus = $this->phHelper->getPaymentStatus(order: $order);
        $newState = $this->phHelper->paymentStatusToOrderState(paymentStatus: $orderStatus);

        if ($newState === Order::STATE_CANCELED) {
            $this->orderHelper->setPendingPaymentState(order: $order);
            $this->orderHelper->cancelOrder(order: $order);
        }

        $this->phHelper->syncOrderStatus(
            order: $order,
            event: constant(name: sprintf(
                '%s::%s',
                PaymentHistoryInterface::class,
                'EVENT_CALLBACK_' . strtoupper(string: $type) . '_COMPLETED'
            ))
        );

        return $order;
    }

    /**
     * Check to see if an order has received BOOKED callback.
     *
     * @param Order $order
     * @return bool
     * @throws LocalizedException
     */
    public function receivedCallback(Order $order): bool
    {
        if (!($order->getPayment() instanceof OrderPaymentInterface)) {
            throw new RuntimeException(
                phrase: __('Missing payment data on order %1', $order->getId())
            );
        }

        $criteria = $this->searchBuilder->addFilter(
            field: PaymentHistoryInterface::ENTITY_PAYMENT_ID,
            value: $order->getPayment()->getEntityId()
        )->addFilter(
            field: PaymentHistoryInterface::ENTITY_EVENT,
            value: PaymentHistoryInterface::EVENT_CALLBACK_BOOKED
        )->create();

        /** @var PaymentHistoryInterface[] $items */
        $items = $this->phRepository
            ->getList(searchCriteria: $criteria)
            ->getItems();

        // NOTE: This needs to be 1.
        return count($items) > 1;
    }

    /**
     * Handle error.
     *
     * @param Exception $exception
     * @return void
     * @throws Exception
     */
    private function handleError(
        Exception $exception
    ): void {
        $this->log->exception(error: $exception);

        if ($exception instanceof CallbackValidationException) {
            throw new Exception(message: $exception->getMessage());
        }
    }
}
