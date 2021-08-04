<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Gateway\Command;

use Exception;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\PaymentException;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Sales\Model\Order\Payment;
use Resursbank\Core\Exception\PaymentDataException;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface as History;
use Resursbank\Ordermanagement\Helper\ApiPayment;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Helper\PaymentHistory;
use function get_class;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Capture implements CommandInterface
{
    /**
     * @var Log
     */
    private $log;

    /**
     * @var ApiPayment
     */
    private $apiPayment;

    /**
     * @var PaymentHistory
     */
    private $paymentHistory;

    /**
     * @param Log $log
     * @param ApiPayment $apiPayment
     * @param PaymentHistory $paymentHistory
     */
    public function __construct(
        Log $log,
        ApiPayment $apiPayment,
        PaymentHistory $paymentHistory
    ) {
        $this->log = $log;
        $this->apiPayment = $apiPayment;
        $this->paymentHistory = $paymentHistory;
    }

    /**
     * @param array $commandSubject
     * @return ResultInterface|null
     * @throws AlreadyExistsException
     * @throws PaymentDataException
     * @throws PaymentException
     */
    public function execute(
        array $commandSubject
    ): ?ResultInterface {
        // Shortcut for improved readability.
        $history = &$this->paymentHistory;

        // Resolve data from command subject.
        $data = SubjectReader::readPayment($commandSubject);
        $paymentId = $data->getOrder()->getOrderIncrementId();
        $payment = $this->getPayment($data);
        $amount = $this->getAmount($commandSubject);

        /** @noinspection BadExceptionsProcessingInspection */
        try {
            // Establish API connection.
            $connection = $this->apiPayment->getConnectionCommandSubject($data);

            // Log command being called.
            $history->entryFromCmd($data, History::EVENT_CAPTURE_CALLED);

            if ($connection !== null && $connection->canDebit($paymentId)) {
                // Log API method being called.
                $history->entryFromCmd($data, History::EVENT_CAPTURE_API_CALLED);

                // Perform partial debit.
                if ($this->isPartial($commandSubject, $data)) {
                    // Flag ECom to drop specLine data (remove payment lines).
                    $connection->setFinalizeWithoutSpec();

                    // Add payment line for entire amount to debit.
                    $connection->addOrderLine('', '', $amount);
                }

                // Capture payment.
                $connection->finalizePayment($paymentId);
            }

            // Set transaction id.
            $payment->setTransactionId($data->getOrder()->getOrderIncrementId());

            // Close transaction when order is paid in full.
            if ((float) $payment->getAmountAuthorized() ===
                ((float) $payment->getAmountPaid() + $amount)
            ) {
                $payment->setIsTransactionClosed(true);
            }
        } catch (Exception $e) {
            // Log error.
            $this->log->exception($e);
            $history->entryFromCmd($data, History::EVENT_CAPTURE_FAILED);

            // Pass safe error upstream.
            throw new PaymentException(__('Failed to capture payment.'));
        }

        return null;
    }

    /**
     * @param PaymentDataObjectInterface $data
     * @return Payment
     * @throws PaymentDataException
     */
    private function getPayment(
        PaymentDataObjectInterface $data
    ): Payment {
        $payment = $data->getPayment();

        if (!$payment instanceof Payment) {
            throw new PaymentDataException(__(
                'Unexpected payment entity. Expected %1 but got %2.',
                Payment::class,
                get_class($data->getPayment())
            ));
        }

        return $payment;
    }

    /**
     * @param array $data
     * @return float
     * @throws PaymentDataException
     */
    private function getAmount(
        array $data
    ): float {
        if (!isset($data['amount']) || !is_numeric($data['amount'])) {
            throw new PaymentDataException(__('Missing expected key amount.'));
        }

        return (float) $data['amount'];
    }

    /**
     * @param array $subjectData
     * @param PaymentDataObjectInterface $data
     * @return bool
     * @throws PaymentDataException
     */
    private function isPartial(
        array $subjectData,
        PaymentDataObjectInterface $data
    ): bool {
        $requested = $this->getAmount($subjectData);
        $total = (float) $this->getPayment($data)->getAmountAuthorized();

        return $requested < $total;
    }
}
