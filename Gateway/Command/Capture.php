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
use Magento\Payment\Gateway\Helper\SubjectReader;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface as History;
use Resursbank\Ordermanagement\Helper\ApiPayment;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Helper\PaymentHistory;

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
     * @param array<mixed> $commandSubject
     * @return ResultInterface|null
     * @throws PaymentException
     * @throws AlreadyExistsException
     */
    public function execute(
        array $commandSubject
    ): ?ResultInterface {
        // Shortcut for improved readability.
        $history = &$this->paymentHistory;

        // Resolve data from command subject.
        $data = SubjectReader::readPayment($commandSubject);
        $paymentId = $data->getOrder()->getOrderIncrementId();

        /** @noinspection BadExceptionsProcessingInspection */
        try {
            // Establish API connection.
            $connection = $this->apiPayment->getConnectionCommandSubject($data);

            // Log command being called.
            $history->entryFromCmd($data, History::EVENT_CAPTURE_CALLED);

            if ($connection !== null && $connection->canDebit($paymentId)) {
                // Log API method being called.
                $history->entryFromCmd($data, History::EVENT_CAPTURE_API_CALLED);

                // Capture payment.
                $connection->finalizePayment($paymentId);
            }

            /** @phpstan-ignore-next-line */
            $data->getPayment()
                ->setTransactionId($data->getOrder()->getOrderIncrementId())
                ->setIsTransactionClosed(true);
        } catch (Exception $e) {
            // Log error.
            $this->log->exception($e);
            $history->entryFromCmd($data, History::EVENT_CAPTURE_FAILED);

            // Pass safe error upstream.
            throw new PaymentException(__('Failed to capture payment.'));
        }

        return null;
    }
}
