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
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Payment;
use Resursbank\Core\Exception\PaymentDataException;
use Resursbank\Core\Model\Api\Payment\Item;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface as History;
use Resursbank\Ordermanagement\Helper\ApiPayment;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Helper\PaymentHistory;
use Resursbank\Ordermanagement\Model\Api\Payment\Converter\CreditmemoConverter;
use Resursbank\RBEcomPHP\ResursBank;
use function get_class;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Refund implements CommandInterface
{
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

        /** @noinspection BadExceptionsProcessingInspection */
        try {
            $payment = $data->getPayment();

            if (!($payment instanceof Payment)) {
                throw new PaymentDataException(
                    __('Unexpected Payment class %1', get_class($payment))
                );
            }

            $memo = $payment->getCreditmemo();

            // Establish API connection.
            $connection = $this->apiPayment->getConnectionCommandSubject($data);

            // Log command being called.
            $history->entryFromCmd($data, History::EVENT_REFUND_CALLED);

            /**
             * NOTE: canCredit will execute API calls which are more expensive
             * than the database transactions to obtain the creditmemo. So the
             * if statement is actually properly optimized.
             */
            if ($connection === null ||
                !($memo instanceof Creditmemo) ||
                !$connection->canCredit($paymentId)
            ) {
                throw new PaymentDataException(
                    __('Payment not creditable.')
                );
            }

            // Log API method being called.
            $history->entryFromCmd(
                $data,
                History::EVENT_REFUND_API_CALLED
            );

            // Add items to API payload.
            $this->addOrderLines(
                $connection,
                $this->creditmemoConverter->convert($memo)
            );

            // Refund payment.
            $connection->creditPayment($paymentId, [], false, true);
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
     * Use the addOrderLine method in ECom to add payload data while avoiding
     * methods that override supplied data.
     *
     * @param ResursBank $connection
     * @param array<Item> $data
     * @throws Exception
     */
    private function addOrderLines(
        ResursBank $connection,
        array $data
    ): void {
        foreach ($data as $item) {
            // Ecom wrongly specifies some arguments as int when they should
            // be floats.
            /** @phpstan-ignore-next-line */
            $connection->addOrderLine(
                $item->getArtNo(),
                $item->getDescription(),
                $item->getUnitAmountWithoutVat(),
                $item->getVatPct(),
                $item->getUnitMeasure(),
                $item->getType(),
                $item->getQuantity()
            );
        }
    }
}
