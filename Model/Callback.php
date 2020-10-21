<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model;

use Exception;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Resursbank\Core\Helper\Api;
use Resursbank\Core\Helper\Api\Credentials;
use Resursbank\Ordermanagement\Api\CallbackInterface;
use Resursbank\Ordermanagement\Exception\CallbackValidationException;
use Resursbank\Ordermanagement\Exception\OrderNotFoundException;
use Resursbank\Ordermanagement\Exception\ResolveOrderStatusFailedException;
use Resursbank\Ordermanagement\Helper\Callback as CallbackHelper;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Helper\ResursbankStatuses;
use Resursbank\RBEcomPHP\RESURS_PAYMENT_STATUS_RETURNCODES;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
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
     * @var OrderInterface
     */
    private $orderInterface;

    /**
     * Callback constructor.
     *
     * @param Api $api
     * @param CallbackHelper $callbackHelper
     * @param Credentials $credentials
     * @param Log $log
     * @param OrderInterface $orderInterface
     */
    public function __construct(
        Api $api,
        CallbackHelper $callbackHelper,
        Credentials $credentials,
        Log $log,
        OrderInterface $orderInterface
    ) {
        $this->api = $api;
        $this->callbackHelper = $callbackHelper;
        $this->credentials = $credentials;
        $this->log = $log;
        $this->orderInterface = $orderInterface;
    }

    /**
     * @inheritDoc
     */
    public function unfreeze(string $paymentId, string $digest): void
    {
        try {
            $this->execute($paymentId, $digest);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * @inheritDoc
     */
    public function booked(string $paymentId, string $digest): void
    {
        try {
            $this->execute($paymentId, $digest);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * @inheritDoc
     */
    public function update(string $paymentId, string $digest): void
    {
        try {
            $this->execute($paymentId, $digest);
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
        string $param5,
        string $digest
    ): void {
        var_dump('Not implemented');
    }

    /**
     * General callback instructions.
     *
     * @param string $paymentId
     * @param string $digest
     * @return Order
     * @throws CallbackValidationException
     * @throws FileSystemException
     * @throws OrderNotFoundException
     * @throws RuntimeException
     * @throws ValidatorException
     */
    private function execute(
        string $paymentId,
        string $digest
    ): Order {
        $this->validate($paymentId, $digest);

        $order = $this->orderInterface->loadByIncrementId($paymentId);

        if (!$order->getId()) {
            throw new OrderNotFoundException('Failed to locate order ' . $paymentId);
        }

        $this->syncStatusFromResurs($order);

        return $order;
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
    private function validate(string $paymentId, string $digest): void
    {
        $ourDigest = strtoupper(
            sha1($paymentId . $this->callbackHelper->salt())
        );

        if ($ourDigest !== $digest) {
            throw new CallbackValidationException('Invalid callback digest.');
        }
    }

    /**
     * @param Exception $exception
     * @return void
     * @throws WebapiException
     */
    private function handleError(Exception $exception): void
    {
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
     * @throws ValidatorException
     * @throws Exception
     */
    private function syncStatusFromResurs(Order $order): void
    {
        $connection = $this->api->getConnection(
            $this->credentials->resolveFromConfig()
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
    }

    /**
     * Get the new order status and state based on constants from eCom.
     *
     * @param int $status
     * @return array
     * @throws Exception
     */
    private function mapStateStatusFromResurs(int $status): array
    {
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
                throw new ResolveOrderStatusFailedException(
                    sprintf(
                        'Failed to resolve order status (%s) from Resurs Bank.',
                        $status
                    )
                );
        }

        return [$orderStatus, $orderState];
    }
}
