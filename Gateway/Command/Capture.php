<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Gateway\Command;

use Exception;
use JsonException;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\PaymentException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Sales\Api\Data\OrderInterface;
use Resursbank\Core\Helper\Order;
use Magento\Sales\Model\OrderRepository;
use ReflectionException;
use Resursbank\Core\Exception\InvalidDataException;
use Resursbank\Core\Exception\PaymentDataException;
use Resursbank\Core\Helper\Api;
use Resursbank\Core\Helper\Config;
use Resursbank\Core\Helper\Mapi;
use Resursbank\Core\Helper\Scope;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Module\Payment\Enum\Status;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface as History;
use Resursbank\Ordermanagement\Helper\ApiPayment;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Helper\PaymentHistory;
use Resursbank\Ordermanagement\Model\Api\Payment\Converter\InvoiceConverter;
use Resursbank\Ordermanagement\Model\Invoice;
use Resursbank\RBEcomPHP\ResursBank;
use ResursException;
use Throwable;
use TorneLIB\Exception\ExceptionHandler;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Capture implements CommandInterface
{
    use CommandTraits;

    /**
     * @param Log $log
     * @param ApiPayment $apiPayment
     * @param PaymentHistory $paymentHistory
     * @param Invoice $invoice
     * @param InvoiceConverter $invoiceConverter
     * @param OrderRepository $orderRepo
     * @param Config $config
     * @param Order $orderHelper
     * @param Api $api
     * @param Scope $scope
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        private readonly Log $log,
        private readonly ApiPayment $apiPayment,
        private readonly PaymentHistory $paymentHistory,
        private readonly Invoice $invoice,
        private readonly InvoiceConverter $invoiceConverter,
        private readonly OrderRepository $orderRepo,
        private readonly Config $config,
        private readonly Order $orderHelper,
        private readonly Api $api,
        private readonly Scope $scope
    ) {
    }

    /**
     * Execute the capture.
     *
     * @param array $commandSubject
     * @return ResultInterface|null
     * @throws AlreadyExistsException
     * @throws PaymentException
     * @throws LocalizedException
     */
    public function execute(
        array $commandSubject
    ): ?ResultInterface {
        try {
            if ($this->isMapiActive(commandSubject: $commandSubject)) {
                $this->mapi(order: $this->getOrder(commandSubject: $commandSubject));
            } else {
                $this->old(order: $this->getOrder(commandSubject: $commandSubject), commandSubject: $commandSubject);
            }
        } catch (Exception $e) {
            // Log error.
            $this->log->exception(error: $e);
            $this->paymentHistory->entryFromCmd(
                data: SubjectReader::readPayment(subject: $commandSubject),
                event: History::EVENT_CAPTURE_FAILED
            );

            // Pass safe error upstream.
            throw new PaymentException(phrase: __('Failed to capture payment.'));
        }

        return null;
    }

    /**
     * Capturing orders with the deprecated afterShop flow.
     *
     * @param OrderInterface $order
     * @param array $commandSubject
     * @return void
     * @throws AlreadyExistsException
     * @throws ExceptionHandler
     * @throws InvalidDataException
     * @throws LocalizedException
     * @throws PaymentException
     * @throws ResursException
     * @throws ValidatorException
     */
    private function old(
        OrderInterface $order,
        array $commandSubject
    ): void {
        if (!$this->api->paymentExists(order: $order)) {
            return;
        }

        $data = SubjectReader::readPayment(subject: $commandSubject);

        try {
            // Establish API connection.
            $connection = $this->apiPayment->getConnectionCommandSubject(paymentData: $data);

            // Skip capture online if payment is already debited.
            if ($connection->canDebit(paymentArrayOrPaymentId: $data->getOrder()->getOrderIncrementId())) {
                // Log command being called.
                $this->paymentHistory->entryFromCmd(data: $data, event: History::EVENT_CAPTURE_CALLED);

                $this->capture(
                    commandSubject: $commandSubject,
                    data: $data,
                    connection: $connection,
                    paymentId: $data->getOrder()->getOrderIncrementId()
                );
            }
        } catch (Throwable $e) {
            // Log error.
            $this->log->exception(error: $e);
            $this->paymentHistory->entryFromCmd(data: $data, event: History::EVENT_CAPTURE_FAILED);

            // Pass safe error upstream.
            throw new PaymentException(__('Failed to capture payment.'));
        }
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
        $payment = $this->getPayment(data: $data);
        $amount = $this->getAmount(data: $commandSubject);

        // Log API method being called.
        $history->entryFromCmd(data: $data, event: History::EVENT_CAPTURE_API_CALLED);

        // Add items to API payload.
        $this->addOrderLines(
            connection: $connection,
            data: $this->invoiceConverter->convert(
                entity: $this->invoice->getInvoice()
            )
        );

        // Refund payment.
        $connection->finalizePayment(
            paymentId: $paymentId,
            customPayloadItemList: [],
            runOnce: false,
            skipSpecValidation: true,
            specificSpecLines: true
        );

        // Set transaction id.
        $payment->setTransactionId(
            transactionId: $data->getOrder()->getOrderIncrementId()
        );

        // Close transaction when order is paid in full.
        if ((float)$payment->getAmountAuthorized() ===
            ((float)$payment->getAmountPaid() + $amount)
        ) {
            $payment->setIsTransactionClosed(isClosed: true);
        }
    }

    /**
     * Get amount and return as float.
     *
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

        return (float)$data['amount'];
    }

    /**
     * Capturing orders with MAPI.
     *
     * @param OrderInterface $order
     * @return void
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ValidationException
     * @throws InputException
     * @throws Exception
     */
    private function mapi(OrderInterface $order): void
    {
        $payment = Mapi::getMapiPayment(
            order: $order,
            orderHelper: $this->orderHelper,
            config: $this->config,
            scope: $this->scope
        );

        if (!$payment->canCapture() ||
            $payment->status === Status::TASK_REDIRECTION_REQUIRED
        ) {
            return;
        }

        Repository::capture(
            paymentId: Mapi::getPaymentId(
                order: $order,
                orderHelper: $this->orderHelper,
                config: $this->config,
                scope: $this->scope
            ),
            orderLines: $this->getOrderLines(
                items: $this->invoiceConverter->convert(entity: $this->invoice->getInvoice())
            )
        );
    }
}
