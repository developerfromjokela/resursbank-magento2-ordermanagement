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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\PaymentException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Payment;
use Resursbank\Core\Exception\PaymentDataException;
use Resursbank\Core\Helper\Api;
use Resursbank\Core\Helper\Api\Credentials;
use Resursbank\Ordermanagement\Helper\Admin as AdminHelper;
use Resursbank\Ordermanagement\Model\Api\Payment\Converter\CreditmemoConverter;
use Resursbank\RBEcomPHP\ResursBank;
use ResursException;
use stdClass;
use function in_array;
use function is_array;
use function is_object;
use function is_string;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ApiPayment extends AbstractHelper
{
    /**
     * @var Api
     */
    private $api;

    /**
     * @var Credentials
     */
    private $credentials;

    /**
     * @var AdminHelper
     */
    private $adminHelper;

    /**
     * @var Log
     */
    private $log;

    /**
     * @var CreditmemoConverter
     */
    private $creditmemoConverter;

    /**
     * @param Context $context
     * @param AdminHelper $adminHelper
     * @param Api $api
     * @param Credentials $credentials
     * @param Log $log
     * @param CreditmemoConverter $creditmemoConverter
     */
    public function __construct(
        Context $context,
        AdminHelper $adminHelper,
        Api $api,
        Credentials $credentials,
        Log $log,
        CreditmemoConverter $creditmemoConverter
    ) {
        $this->adminHelper = $adminHelper;
        $this->api = $api;
        $this->credentials = $credentials;
        $this->log = $log;
        $this->creditmemoConverter = $creditmemoConverter;

        parent::__construct($context);
    }

    /**
     * Check if a payment is finalized (debited).
     *
     * @param stdClass $apiPayment
     * @return bool
     */
    public function isFinalized(
        stdClass $apiPayment
    ): bool {
        return (
            is_object($apiPayment) &&
            isset($apiPayment->finalized) &&
            $apiPayment->finalized
        );
    }

    /**
     * Check if a provided payment session has a certain status applied.
     *
     * @param stdClass $apiPayment
     * @param string $status
     * @return bool
     */
    public function hasStatus(
        stdClass $apiPayment,
        string $status
    ): bool {
        $status = strtoupper($status);

        return isset($apiPayment->status) &&
            (
                (
                    is_array($apiPayment->status) &&
                    in_array($status, $apiPayment->status, true)
                ) ||
                (
                    is_string($apiPayment->status) &&
                    strtoupper($apiPayment->status) === $status
                )
            );
    }

    /**
     * Finalize Resursbank payment.
     *
     * @param InfoInterface $orderPayment
     * @param stdClass $apiPayment
     * @param ResursBank $connection
     * @param string $paymentId
     * @return self
     * @throws PaymentDataException
     * @throws Exception
     */
    public function finalizePayment(
        InfoInterface $orderPayment,
        stdClass $apiPayment,
        ResursBank $connection,
        string $paymentId
    ): self {
        if ($this->canFinalize($apiPayment)) {
            if (!($orderPayment instanceof Payment)) {
                throw new PaymentDataException(__(
                    'Resurs Bank payment %1 could not be finalized because ' .
                    'there was a problem with the payment in Magento.',
                    $paymentId
                ));
            }

            if ($apiPayment->frozen) {
                throw new PaymentDataException(__(
                    'Resurs Bank payment %1 is still frozen.',
                    $paymentId
                ));
            }

            if (!$this->isDebitable($apiPayment)) {
                throw new PaymentDataException(__(
                    'Resurs Bank payment %1 is not debitable.',
                    $paymentId
                ));
            }

            // Set platform / user reference.
            $connection->setRealClientName('Magento2');
            $connection->setLoggedInUser($this->adminHelper->getUserName());

            // Without this ECom will lose the payment reference.
            $connection->setPreferredId($paymentId);

            if (!$connection->finalizePayment($paymentId)) {
                throw new PaymentDataException(__(
                    'Failed to finalize Resurs Bank payment %1.',
                    $paymentId
                ));
            }

            $orderPayment->setTransactionId($paymentId);
            $orderPayment->setIsTransactionClosed(true);
        }

        return $this;
    }

    /**
     * @param PaymentDataObjectInterface $paymentData
     * @return bool
     * @throws LocalizedException
     * @throws PaymentException
     * @throws ValidatorException
     * @throws Exception
     */
    public function cancelPayment(
        PaymentDataObjectInterface $paymentData
    ): bool {
        $order = $paymentData->getOrder();
        $paymentId = $order->getOrderIncrementId();
        $connection = $this->api->getConnection(
            $this->credentials->resolveFromConfig()
        );

        if ($this->exists($paymentId) &&
            !$connection->getIsAnnulled([$paymentId])
        ) {
            $this->log->info("Cancelling payment {$paymentId}");

            $connection->setRealClientName('Magento2');
            $connection->setLoggedInUser($this->adminHelper->getUserName());
            $connection->setPreferredId($paymentId);

            if (!$connection->annulPayment($paymentId)) {
                throw new PaymentException(__(
                    'An error occurred while communicating with the API.'
                ));
            }

            $this->log->info(
                "Successfully cancelled payment {$paymentId}"
            );
        }

        return true;
    }

    /**
     * @param string $paymentId
     * @param Creditmemo $memo
     * @param ResursBank|null $connection
     * @return bool Whether the operation was successful. Will default to
     * false if the payment does not exist, and true if the payment has already
     * been refunded.
     * @throws ResursException
     * @throws ValidatorException
     * @throws Exception
     */
    public function refundPayment(
        string $paymentId,
        Creditmemo $memo,
        ?ResursBank $connection = null
    ): bool {
        $result = false;
        $connection = $this->getDefaultConnection($connection);
        $exists = $this->exists($paymentId, $connection);
        $canRefund = $connection->canCredit($paymentId);

        if ($exists && $canRefund) {
            // Set platform / user reference.
            $connection->setRealClientName('Magento2');
            $connection->setLoggedInUser($this->adminHelper->getUserName());
            $connection->setPreferredId($paymentId);
            $result = $connection->creditPayment(
                $paymentId,
                $this->creditmemoConverter->convertItemsToArrays(
                    $this->creditmemoConverter->convert($memo)
                )
            );
        } elseif ($exists && !$canRefund) {
            // Here we assume that the payment has already been refunded.
            $result = true;
        }

        return $result;
    }

    /**
     * @param string $paymentId
     * @param ResursBank|null $connection
     * @return bool
     * @throws ValidatorException
     * @throws Exception
     */
    public function isRefunded(
        string $paymentId,
        ?ResursBank $connection = null
    ): bool {
        $connection = $this->getDefaultConnection($connection);

        return $this->exists($paymentId, $connection) &&
            !$connection->canCredit($paymentId);
    }

    /**
     * Check if payment can be debited.
     *
     * @param stdClass $payment
     * @return bool
     */
    public function isDebitable(
        stdClass $payment
    ): bool {
        return $this->hasStatus($payment, 'DEBITABLE');
    }

    /**
     * Payment can only be captured if the payment at Resurs Bank is not marked
     * as "finalized" and the payment is not fully or partially debited.
     *
     * @param stdClass $apiPayment
     * @return bool
     */
    private function canFinalize(
        stdClass $apiPayment
    ): bool {
        return (
            !$this->isFinalized($apiPayment) &&
            !$this->hasStatus($apiPayment, 'IS_DEBITED')
        );
    }

    /**
     * Check if a payment actually exists.
     *
     * @param string $paymentId
     * @param ResursBank|null $connection - If a connection is not given, a
     * default connection resolved from config is used instead.
     * @return bool
     * @throws ResursException|ValidatorException
     */
    public function exists(
        string $paymentId,
        ?ResursBank $connection = null
    ): bool {
        $result = false;

        try {
            $result = $this->getDefaultConnection($connection)
                    ->getPayment($paymentId) !== null;
        } catch (Exception $e) {
            // If there is no payment we will receive an Exception from ECom.
            if (!$this->validateMissingPaymentException($e)) {
                throw $e;
            }
        }

        return $result;
    }

    /**
     * Validate that an Exception was thrown because a payment was actually
     * missing.
     *
     * @param Exception $error
     * @return bool
     */
    public function validateMissingPaymentException(
        Exception $error
    ): bool {
        return (
            $error->getCode() === 3 ||
            $error->getCode() === 8
        );
    }

    /**
     * Defaults a connection with credentials resolved from config, unless a
     * connection is given in which case that connection is returned.
     *
     * @param ResursBank|null $connection
     * @return ResursBank
     * @throws ValidatorException
     * @throws Exception
     */
    private function getDefaultConnection(
        ?ResursBank $connection = null
    ): ResursBank {
        return ($connection instanceof ResursBank) ?
            $connection :
            $this->api->getConnection(
                $this->credentials->resolveFromConfig()
            );
    }
}
