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
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order\Payment;
use Resursbank\Core\Exception\PaymentDataException;
use Resursbank\Ordermanagement\Helper\Admin as AdminHelper;
use Resursbank\RBEcomPHP\ResursBank;
use stdClass;

class ApiPayment extends AbstractHelper
{
    /**
     * @var AdminHelper
     */
    private $adminHelper;

    /**
     * @param Context $context
     * @param AdminHelper $adminHelper
     */
    public function __construct(
        Context $context,
        AdminHelper $adminHelper
    ) {
        $this->adminHelper = $adminHelper;

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
}
