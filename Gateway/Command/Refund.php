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
use Magento\Sales\Model\Order\Creditmemo;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface as History;
use Resursbank\Ordermanagement\Helper\ApiPayment;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Helper\PaymentHistory;
use Resursbank\Ordermanagement\Model\Api\Payment\Converter\CreditmemoConverter;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Refund implements CommandInterface
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
     * @var CreditmemoConverter
     */
    private $creditmemoConverter;

    /**
     * @param Log $log
     * @param ApiPayment $apiPayment
     * @param PaymentHistory $paymentHistory
     * @param CreditmemoConverter $creditmemoConverter
     */
    public function __construct(
        Log $log,
        ApiPayment $apiPayment,
        PaymentHistory $paymentHistory,
        CreditmemoConverter $creditmemoConverter
    ) {
        $this->log = $log;
        $this->apiPayment = $apiPayment;
        $this->paymentHistory = $paymentHistory;
        $this->creditmemoConverter = $creditmemoConverter;
    }

    /**
     * @param array<mixed> $subject
     * @return ResultInterface|null
     * @throws PaymentException
     * @throws AlreadyExistsException
     */
    public function execute(
        array $subject
    ): ?ResultInterface {
        // Shortcut for improved readability.
        $history = &$this->paymentHistory;

        // Resolve data from command subject.
        $data = SubjectReader::readPayment($subject);
        $paymentId = $data->getOrder()->getOrderIncrementId();

        /** @noinspection BadExceptionsProcessingInspection */
        try {
            /** @phpstan-ignore-next-line */
            $memo = $data->getPayment()->getCreditmemo();

            // Establish API connection.
            $connection = $this->apiPayment->getConnectionCommandSubject($data);

            // Log command being called.
            $history->entryFromCmd($data, History::EVENT_REFUND_CALLED);

            /** @noinspection NotOptimalIfConditionsInspection */
            /**
             * NOTE: canCredit will execute API calls which are more expensive
             * than the database transactions to obtain the creditmemo. So the
             * if statement is actually properly optimized.
             */
            if ($connection !== null &&
                $memo instanceof Creditmemo &&
                $connection->canCredit($paymentId)
            ) {
                // Log API method being called.
                $history->entryFromCmd($data, History::EVENT_REFUND_API_CALLED);

                // Refund payment.
                $connection->creditPayment(
                    $paymentId,
                    $this->creditmemoConverter->convertItemsToArrays(
                        $this->creditmemoConverter->convert($memo)
                    )
                );
            }
        } catch (Exception $e) {
            // Log error.
            $this->log->exception($e);
            $history->entryFromCmd($data, History::EVENT_REFUND_FAILED);

            // Pass safe error upstream.
            throw new PaymentException(__('Failed to refund payment.'));
        }

        return null;
    }
}
