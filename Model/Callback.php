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
use Resursbank\Ordermanagement\Helper\PaymentHistory;
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
use Resursbank\Core\Helper\Scope;
use Resursbank\Ordermanagement\Api\CallbackInterface;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Exception\CallbackValidationException;
use Resursbank\Ordermanagement\Exception\OrderNotFoundException;
use Resursbank\Ordermanagement\Helper\Callback as CallbackHelper;
use Resursbank\Ordermanagement\Helper\CallbackLog;
use Resursbank\Ordermanagement\Helper\Config as ConfigHelper;
use Resursbank\Ordermanagement\Helper\Log;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @noinspection EfferentObjectCouplingInspection
 */
class Callback implements CallbackInterface
{
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
        PaymentHistory $phHelper
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
                (int) $this->scope->getId(),
                $this->scope->getType()
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
     * @throws LocalizedException
     */
    private function execute(
        string $type,
        string $paymentId,
        string $digest
    ): Order {
        $this->validate($paymentId, $digest);

        $this->logIncoming($type, $paymentId, $digest);

        if (!($this->orderInterface instanceof Order)) {
            throw new LocalizedException(
                __('orderInterface not an instance of Order')
            );
        }

        /** @var Order $order */
        $order = $this->orderInterface->loadByIncrementId($paymentId);

        if (!$order->getId()) {
            throw new OrderNotFoundException(
                __('Failed to locate order ' . $paymentId)
            );
        }

        $newState = $this->phHelper->getOrderStateFromResurs($order);

        if ($newState === Order::STATE_CANCELED) {
            $order->cancel();
        }

        $this->phHelper->syncOrderStatus(
            $order,
            constant(sprintf(
                '%s::%s',
                PaymentHistoryInterface::class,
                'EVENT_CALLBACK_' . strtoupper($type)
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
                __("Invalid digest - PaymentId: $paymentId. Digest: $digest")
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
            "[$type] - PaymentId: $paymentId. Digest: $digest"
        );
    }
}
