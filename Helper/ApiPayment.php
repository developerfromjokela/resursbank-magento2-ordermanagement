<?php
/**
 * Copyright 2016 Resurs Bank AB
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
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
use Magento\Sales\Model\Order\Payment;
use Resursbank\Core\Exception\PaymentDataException;
use Resursbank\Core\Helper\Api;
use Resursbank\Core\Helper\Api\Credentials;
use Resursbank\Ordermanagement\Helper\Admin as AdminHelper;
use Resursbank\RBEcomPHP\ResursBank;
use ResursException;
use stdClass;

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
     * @param Context $context
     * @param AdminHelper $adminHelper
     * @param Api $api
     * @param Credentials $credentials
     * @param Log $log
     */
    public function __construct(
        Context $context,
        AdminHelper $adminHelper,
        Api $api,
        Credentials $credentials,
        Log $log
    ) {
        $this->adminHelper = $adminHelper;
        $this->api = $api;
        $this->credentials = $credentials;
        $this->log = $log;

        parent::__construct($context);
    }

    /**
     * Check if a payment debited.
     *
     * @param stdClass $apiPayment
     * @return bool
     */
    public function isFinalized(stdClass $apiPayment): bool
    {
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
     * Payment can only be captured if the payment at Resurs Bank is not marked
     * as "finalized" and the payment is not fully or partially debited.
     *
     * @param stdClass $apiPayment
     * @return bool
     */
    private function canFinalize(stdClass $apiPayment): bool
    {
        return (
            !$this->isFinalized($apiPayment) &&
            !$this->hasStatus($apiPayment, 'IS_DEBITED')
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
                    "Resursbank payment {$paymentId} could not be finalized " .
                    "because there was a problem with the payment in Magento."
                ));
            }

            if ($apiPayment->frozen) {
                throw new PaymentDataException(__(
                    "Resursbank payment {$paymentId} is still frozen."
                ));
            }

            if (!$this->isDebitable($apiPayment)) {
                throw new PaymentDataException(__(
                    "Resursbank payment {$paymentId} cannot be debited yet."
                ));
            }

            // Set platform / user reference.
            $connection->setRealClientName('Magento2');
            $connection->setLoggedInUser($this->adminHelper->getUserName());

            // Without this ECom will lose the payment reference.
            $connection->setPreferredId($paymentId);

            if (!$connection->finalizePayment($paymentId)) {
                throw new PaymentDataException(__(
                    "Failed to finalize Resursbank payment {$paymentId}."
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
     * Check if payment can be debited.
     *
     * @param stdClass $payment
     * @return bool
     */
    public function isDebitable(stdClass $payment): bool
    {
        return $this->hasStatus(
            $payment,
            'DEBITABLE'
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
