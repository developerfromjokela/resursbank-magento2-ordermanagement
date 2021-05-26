<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Resursbank\Ordermanagement\Api\PaymentHistoryRepositoryInterface;
use function constant;
use Exception;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\OrderRepository;
use Magento\Store\Model\ScopeInterface;
use Resursbank\Core\Helper\Api;
use Resursbank\Core\Helper\Api\Credentials;
use Resursbank\Ordermanagement\Api\CallbackInterface;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Exception\CallbackValidationException;
use Resursbank\Ordermanagement\Exception\OrderNotFoundException;
use Resursbank\Ordermanagement\Exception\ResolveOrderStatusFailedException;
use Resursbank\Ordermanagement\Helper\Callback as CallbackHelper;
use Resursbank\Ordermanagement\Helper\CallbackLog;
use Resursbank\Ordermanagement\Helper\Config as ConfigHelper;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Helper\PaymentHistory as PaymentHistoryHelper;
use Resursbank\Ordermanagement\Helper\ResursbankStatuses;
use Resursbank\RBEcomPHP\RESURS_PAYMENT_STATUS_RETURNCODES;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @noinspection EfferentObjectCouplingInspection
 */
class Callback implements CallbackInterface
{
    /**
     * @var Api
     */
    private $api;

    /**
     * @var CallbackHelper
     */
    private $callbackHelper;

    /**
     * @var Credentials
     */
    private $credentials;

    /**
     * @var Log
     */
    private $log;

    /**
     * @var CallbackLog
     */
    private $callbackLog;

    /**
     * @var OrderInterface
     */
    private $orderInterface;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @var ConfigHelper
     */
    private $config;

    /**
     * @var OrderSender
     */
    private $orderSender;

    /**
     * @var PaymentHistoryHelper
     */
    private $phHelper;

    /**
     * @var PaymentHistoryRepositoryInterface
     */
    private $phRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchBuilder;

    /**
     * @var TypeListInterface
     */
    private $cacheTypeList;

    /**
     * @param Api $api
     * @param CallbackHelper $callbackHelper
     * @param ConfigHelper $config
     * @param Credentials $credentials
     * @param Log $log
     * @param CallbackLog $callbackLog
     * @param OrderInterface $orderInterface
     * @param OrderRepository $orderRepository
     * @param OrderSender $orderSender
     * @param PaymentHistoryHelper $phHelper
     * @param PaymentHistoryRepositoryInterface $phRepository
     * @param SearchCriteriaBuilder $searchBuilder
     * @param TypeListInterface $cacheTypeList
     * @noinspection PhpUndefinedClassInspection
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Api $api,
        CallbackHelper $callbackHelper,
        ConfigHelper $config,
        Credentials $credentials,
        Log $log,
        CallbackLog $callbackLog,
        OrderInterface $orderInterface,
        OrderRepository $orderRepository,
        OrderSender $orderSender,
        PaymentHistoryHelper $phHelper,
        PaymentHistoryRepositoryInterface $phRepository,
        SearchCriteriaBuilder $searchBuilder,
        TypeListInterface $cacheTypeList
    ) {
        $this->api = $api;
        $this->callbackHelper = $callbackHelper;
        $this->config = $config;
        $this->credentials = $credentials;
        $this->log = $log;
        $this->callbackLog = $callbackLog;
        $this->orderInterface = $orderInterface;
        $this->orderRepository = $orderRepository;
        $this->orderSender = $orderSender;
        $this->phHelper = $phHelper;
        $this->phRepository = $phRepository;
        $this->searchBuilder = $searchBuilder;
        $this->cacheTypeList = $cacheTypeList;
    }

    /**
     * @inheritDoc
     */
    public function unfreeze(
        string $paymentId,
        string $digest
    ): void {
        try {
            $this->execute('unfreeze', $paymentId, $digest);
        } catch (Exception $e) {
            $this->handleError($e);
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
            $order = $this->execute('booked', $paymentId, $digest);

            // Send order confirmation email if this is first BOOKED.
            if (!$this->receivedCallback($order)) {
                $this->orderSender->send($order);
            }
        } catch (Exception $e) {
            $this->handleError($e);
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
            $this->execute('update', $paymentId, $digest);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * @inheritDoc
     */
    public function test(
        string $param1,
        string $param2,
        string $param3,
        string $param4,
        string $param5
    ): void {
        try {
            $this->logIncoming('test', '', '');

            $this->config->setCallbackTestReceivedAt(
                (int) $param1,
                $param2,
            );

            // Clear the config cache so this value show up.
            $this->cacheTypeList->cleanType('config');
        } catch (Exception $e) {
            $this->handleError($e);
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
     */
    private function execute(
        string $type,
        string $paymentId,
        string $digest
    ): Order {
        $this->validate($paymentId, $digest);

        $this->logIncoming($type, $paymentId, $digest);

        /** @var Order $order */
        /** @phpstan-ignore-next-line */
        $order = $this->orderInterface->loadByIncrementId($paymentId);

        if (!$order->getId()) {
            throw new OrderNotFoundException(
                __('Failed to locate order ' . $paymentId)
            );
        }

        if (!($order->getPayment() instanceof OrderPaymentInterface)) {
            throw new RuntimeException(
                __('Missing payment data on order %1', $order->getId())
            );
        }

        $oldStatus = $order->getStatus();
        $oldState = $order->getState();

        [$newStatus, $newState] = $this->syncStatusFromResurs($order);

        $historyEvent = constant(sprintf(
            '%s::%s',
            PaymentHistoryInterface::class,
            'EVENT_CALLBACK_' . strtoupper($type)
        ));

        $this->phHelper->createEntry(
            (int) $order->getPayment()->getEntityId(),
            $historyEvent,
            PaymentHistoryInterface::USER_RESURS_BANK,
            $oldState,
            $newState,
            $oldStatus,
            $newStatus
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
                __('Missing payment data on order %1', $order->getId())
            );
        }

        $criteria = $this->searchBuilder->addFilter(
            PaymentHistoryInterface::ENTITY_PAYMENT_ID,
            $order->getPayment()->getEntityId()
        )->addFilter(
            PaymentHistoryInterface::ENTITY_EVENT,
            PaymentHistoryInterface::EVENT_CALLBACK_BOOKED
        )->create();

        /** @var PaymentHistoryInterface[] $items */
        $items = $this->phRepository
            ->getList($criteria)
            ->getItems();

        return count($items) > 1;
    }

    /**
     * Validate the digest.
     *
     * @param string $paymentId
     * @param string $digest
     * @throws CallbackValidationException
     * @throws FileSystemException
     * @throws RuntimeException
     */
    private function validate(
        string $paymentId,
        string $digest
    ): void {
        $ourDigest = strtoupper(
            sha1($paymentId . $this->callbackHelper->salt())
        );

        if ($ourDigest !== $digest) {
            throw new CallbackValidationException(
                __("Invalid digest - PaymentId: {$paymentId}. Digest: {$digest}")
            );
        }
    }

    /**
     * @param Exception $exception
     * @return void
     * @throws WebapiException
     */
    private function handleError(
        Exception $exception
    ): void {
        $this->log->exception($exception);

        if ($exception instanceof CallbackValidationException) {
            throw new WebapiException(
                __($exception->getMessage()),
                0,
                WebapiException::HTTP_NOT_ACCEPTABLE
            );
        }
    }

    /**
     * Resolve the status and state for the order by asking Resurs Bank.
     *
     * @param Order $order
     * @return array<string>
     * @throws ValidatorException
     * @throws Exception
     */
    private function syncStatusFromResurs(
        Order $order
    ): array {
        $connection = $this->api->getConnection(
            $this->credentials->resolveFromConfig(
                (string) $order->getStore()->getCode(),
                ScopeInterface::SCOPE_STORES
            )
        );

        $status = $connection->getOrderStatusByPayment(
            $order->getIncrementId()
        );

        [$newStatus, $newState] = $this->mapStateStatusFromResurs($status);

        if ($newState === Order::STATE_CANCELED) {
            $order->cancel();
        }

        $order->setStatus($newStatus);
        $order->setState($newState);

        $this->orderRepository->save($order);

        return [$newStatus, $newState];
    }

    /**
     * Get the new order status and state based on constants from eCom.
     *
     * @param int $status
     * @return array<string>
     * @throws Exception
     */
    private function mapStateStatusFromResurs(
        int $status
    ): array {
        switch ($status) {
            case RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PENDING:
                $orderStatus = ResursbankStatuses::PAYMENT_REVIEW;
                $orderState = Order::STATE_PAYMENT_REVIEW;
                break;
            case RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_PROCESSING:
                $orderStatus = ResursbankStatuses::CONFIRMED;
                $orderState = Order::STATE_PENDING_PAYMENT;
                break;
            case RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_COMPLETED:
                $orderStatus = ResursbankStatuses::FINALIZED;
                $orderState = Order::STATE_PROCESSING;
                break;
            case RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_ANNULLED:
                $orderStatus = ResursbankStatuses::CANCELLED;
                $orderState = Order::STATE_CANCELED;
                break;
            case RESURS_PAYMENT_STATUS_RETURNCODES::PAYMENT_CREDITED:
                $orderStatus = Order::STATE_CLOSED;
                $orderState = Order::STATE_CLOSED;
                break;
            default:
                throw new ResolveOrderStatusFailedException(__(
                    sprintf(
                        'Failed to resolve order status (%s) from Resurs Bank.',
                        $status
                    )
                ));
        }

        return [$orderStatus, $orderState];
    }

    /**
     * Log incoming callbacks.
     *
     * @param string $type
     * @param string $paymentId
     * @param string $digest
     */
    private function logIncoming(
        string $type,
        string $paymentId,
        string $digest
    ): void {
        $this->callbackLog->info(
            "[{$type}] - PaymentId: {$paymentId}. Digest: {$digest}"
        );
    }
}
