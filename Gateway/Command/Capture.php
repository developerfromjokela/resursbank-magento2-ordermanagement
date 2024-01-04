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
use Magento\Sales\Model\OrderRepository;
use Magento\Store\Model\StoreManagerInterface;
use Resursbank\Core\Exception\PaymentDataException;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface as History;
use Resursbank\Ordermanagement\Helper\ApiPayment;
use Resursbank\Ordermanagement\Helper\Config;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Helper\PaymentHistory;
use Resursbank\Ordermanagement\Model\Invoice;
use Resursbank\Ordermanagement\Model\Api\Payment\Converter\InvoiceConverter;
use Resursbank\RBEcomPHP\ResursBank;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Capture implements CommandInterface
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
     * @var Invoice
     */
    private Invoice $invoice;

    /**
     * @var InvoiceConverter
     */
    private InvoiceConverter $invoiceConverter;

    /**
     * @param Log $log
     * @param ApiPayment $apiPayment
     * @param PaymentHistory $paymentHistory
     * @param Invoice $invoice
     * @param InvoiceConverter $invoiceConverter
     * @param Config $configHelper
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Log $log,
        ApiPayment $apiPayment,
        PaymentHistory $paymentHistory,
        Invoice $invoice,
        InvoiceConverter $invoiceConverter,
        private readonly Config $configHelper,
        private readonly StoreManagerInterface $storeManager,
        private readonly OrderRepository $orderRepository
    ) {
        $this->log = $log;
        $this->apiPayment = $apiPayment;
        $this->paymentHistory = $paymentHistory;
        $this->invoice = $invoice;
        $this->invoiceConverter = $invoiceConverter;
    }

    /**
     * Execution entrypoint.
     *
     * @param array<mixed> $commandSubject
     * @return ResultInterface|null
     * @throws AlreadyExistsException
     * @throws PaymentException
     * @throws LocalizedException
     */
    public function execute(
        array $commandSubject
    ): ?ResultInterface {
        // Shortcut for improved readability.
        $history = &$this->paymentHistory;

        // Resolve data from command subject.
        $data = SubjectReader::readPayment(subject: $commandSubject);
        $order = $this->orderRepository->get(id: $data->getOrder()->getId());
        $paymentId = $data->getOrder()->getOrderIncrementId();
        $store = $this->storeManager->getStore(storeId: $order->getStoreId());

        if (!$this->configHelper->isAfterShopEnabled(
            scopeCode: $store->getCode()
        )
        ) {
            return null;
        }


        try {
            // Establish API connection.
            $connection = $this->apiPayment->getConnectionCommandSubject($data);

            // Log command being called.
            $history->entryFromCmd($data, History::EVENT_CAPTURE_CALLED);

            // Skip capture online if payment is already debited.
            if ($connection->canDebit($paymentId)) {
                $this->capture($commandSubject, $data, $connection, $paymentId);
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
     * Fetch invoice property.
     *
     * @return Invoice
     */
    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }

    /**
     * Capture online.
     *
     * @param array $commandSubject
     * @param PaymentDataObjectInterface $data
     * @param ResursBank $connection
     * @param string $paymentId
     * @return void
     * @throws AlreadyExistsException
     * @throws PaymentDataException
     * @throws Exception
     */
    private function capture(
        array $commandSubject,
        PaymentDataObjectInterface $data,
        ResursBank $connection,
        string $paymentId
    ): void {
        // Shortcut for improved readability.
        $history = &$this->paymentHistory;
        $payment = $this->getPayment($data);
        $amount = $this->getAmount($commandSubject);

        // Log API method being called.
        $history->entryFromCmd($data, History::EVENT_CAPTURE_API_CALLED);

        // Add items to API payload.
        $this->addOrderLines(
            $connection,
            $this->invoiceConverter->convert(
                $this->invoice->getInvoice()
            )
        );

        // Refund payment.
        $connection->finalizePayment($paymentId, [], false, true, true);

        // Set transaction id.
        $payment->setTransactionId(
            $data->getOrder()->getOrderIncrementId()
        );

        // Close transaction when order is paid in full.
        if ((float)$payment->getAmountAuthorized() ===
            ((float)$payment->getAmountPaid() + $amount)
        ) {
            $payment->setIsTransactionClosed(true);
        }
    }

    /**
     * Get amount to capture.
     *
     * @param array<mixed> $data
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
}
