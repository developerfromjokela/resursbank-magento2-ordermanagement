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
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order\Payment;
use Resursbank\Core\Exception\PaymentDataException;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Helper\Admin as AdminHelper;
use Resursbank\RBEcomPHP\ResursBank;
use stdClass;
use function in_array;
use function is_array;
use function is_object;
use function is_string;

class ApiPayment extends AbstractHelper
{
    /**
     * @var AdminHelper
     */
    private $adminHelper;

    /**
     * @var PaymentHistory
     */
    private $paymentHistory;

    /**
     * @param Context $context
     * @param AdminHelper $adminHelper
     * @param PaymentHistory $paymentHistory
     */
    public function __construct(
        Context $context,
        AdminHelper $adminHelper,
        PaymentHistory $paymentHistory
    ) {
        $this->adminHelper = $adminHelper;
        $this->paymentHistory = $paymentHistory;

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

            // Log that we have performed the API call.
            $this->paymentHistory->createEntry(
                (int) $orderPayment->getEntityId(),
                PaymentHistoryInterface::EVENT_CAPTURE_API_CALLED,
                PaymentHistoryInterface::USER_CLIENT
            );

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
     * Check if payment is debitable.
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
}
