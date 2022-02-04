<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Gateway\Command;

use Exception;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\PaymentException;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Payment;
use Resursbank\Core\Exception\PaymentDataException;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface as History;
use Resursbank\Ordermanagement\Helper\ApiPayment;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Helper\PaymentHistory;
use Resursbank\Ordermanagement\Model\Api\Payment\Converter\CreditmemoConverter;
use Resursbank\RBEcomPHP\ResursBank;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Refund implements CommandInterface
{
    use CommandTraits;

    /**
     * @var Log
     */
    private Log $log;

    /**
     * @var ApiPayment
     */
    private ApiPayment $apiPayment;

    /**
     * @var PaymentHistory
     */
    private PaymentHistory $paymentHistory;

    /**
     * @var CreditmemoConverter
     */
    private CreditmemoConverter $creditmemoConverter;

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
     * @param array<mixed> $commandSubject
     * @return ResultInterface|null
     * @throws PaymentException
     * @throws AlreadyExistsException|LocalizedException
     */
    public function execute(
        array $commandSubject
    ): ?ResultInterface {
        // Shortcut for improved readability.
        $history = &$this->paymentHistory;

        // Resolve data from command subject.
        $data = SubjectReader::readPayment($commandSubject);
        $paymentId = $data->getOrder()->getOrderIncrementId();

        try {
            // Establish API connection.
            $connection = $this->apiPayment->getConnectionCommandSubject($data);

            // Log command being called.
            $history->entryFromCmd($data, History::EVENT_REFUND_CALLED);

            // Skip refunding online if payment is already refunded.
            if ($connection->canCredit($paymentId)) {
                $this->refund($data, $connection, $paymentId);
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

    /**
     * Resolve credit memo from payment.
     *
     * @param Payment $payment
     * @return Creditmemo
     * @throws PaymentDataException
     */
    private function getMemo(
        Payment $payment
    ): Creditmemo {
        $memo = $payment->getCreditmemo();

        if (!($memo instanceof Creditmemo)) {
            throw new PaymentDataException(__('Invalid credit memo.'));
        }

        return $memo;
    }

    /**
     * Refund online.
     *
     * @param PaymentDataObjectInterface $data
     * @param ResursBank $connection
     * @param string $paymentId
     * @return void
     * @throws AlreadyExistsException
     * @throws PaymentDataException
     * @throws Exception
     */
    private function refund(
        PaymentDataObjectInterface $data,
        ResursBank $connection,
        string $paymentId
    ): void {
        // Shortcut for improved readability.
        $history = &$this->paymentHistory;

        if (!$connection->canCredit($paymentId)) {
            throw new PaymentDataException(__('Payment not ready for credit.'));
        }

        // Log API method being called.
        $history->entryFromCmd(
            $data,
            History::EVENT_REFUND_API_CALLED
        );

        // Add items to API payload.
        $this->addOrderLines(
            $connection,
            $this->creditmemoConverter->convert(
                $this->getMemo($this->getPayment($data))
            )
        );

        // Refund payment.
        $connection->setGetPaymentMatchKeys(['artNo', 'description', 'unitMeasure']);
        $connection->creditPayment($paymentId, [], false, true);
    }
}
