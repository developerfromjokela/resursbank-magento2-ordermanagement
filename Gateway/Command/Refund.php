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
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\OrderRepository;
use ReflectionException;
use Resursbank\Core\Exception\InvalidDataException;
use Resursbank\Core\Exception\PaymentDataException;
use Resursbank\Core\Helper\Api;
use Resursbank\Core\Helper\Config;
use Resursbank\Core\Helper\Order;
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
use Resursbank\Ordermanagement\Model\Api\Payment\Converter\CreditmemoConverter;
use Resursbank\RBEcomPHP\ResursBank;
use ResursException;
use TorneLIB\Exception\ExceptionHandler;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Refund implements CommandInterface
{
    use CommandTraits;

    /**
     * @param Log $log
     * @param ApiPayment $apiPayment
     * @param PaymentHistory $paymentHistory
     * @param CreditmemoConverter $creditmemoConverter
     * @param OrderRepository $orderRepo
     * @param Config $config
     * @param Order $orderHelper
     * @param Api $api
     */
    public function __construct(
        private readonly Log $log,
        private readonly ApiPayment $apiPayment,
        private readonly PaymentHistory $paymentHistory,
        private readonly CreditmemoConverter $creditmemoConverter,
        private readonly OrderRepository $orderRepo,
        private readonly Config $config,
        private readonly Order $orderHelper,
        private readonly Api $api
    ) {
    }

    /**
     * @param array $commandSubject
     * @return ResultInterface|null
     * @throws PaymentException
     * @throws AlreadyExistsException|LocalizedException
     */
    public function execute(
        array $commandSubject
    ): ?ResultInterface {
        $data = SubjectReader::readPayment(subject: $commandSubject);
        $order = $this->orderRepo->get(id: $data->getOrder()->getId());

        try {
            if ($this->config->isMapiActive(scopeCode: $order->getStoreId())) {
                $this->mapi(order: $order);
            } else {
                $this->old(order: $order, commandSubject: $commandSubject);
            }
        } catch (Exception $e) {
            // Log error.
            $this->log->exception(error: $e);
            $this->paymentHistory->entryFromCmd(data: $data, event: History::EVENT_REFUND_FAILED);

            // Pass safe error upstream.
            throw new PaymentException(phrase: __('Failed to refund payment.'));
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
            throw new PaymentDataException(phrase: __('Invalid credit memo.'));
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

        // Log API method being called.
        $history->entryFromCmd(
            data: $data,
            event: History::EVENT_REFUND_API_CALLED
        );

        // Add items to API payload.
        $this->addOrderLines(
            connection: $connection,
            data: $this->creditmemoConverter->convert(
                entity: $this->getMemo(payment: $this->getPayment(data: $data))
            )
        );

        /* Even though we disable payload validation in our call to
        creditPayment below, ECom will perform some validation, which will fail
        for certain order lines (if you part debit a discount a new order line
        is created in the payment at Resurs Bank, it's marked as DEBIT but never
        AUTHORIZE, because of this ECom won't see it in getPaymentDiffAsTable(),
        and thus we cannot credit the new discount order line after it has been
        added). The line below changes what data ECom utilizes to identify order
        lines and is essentially a work-around to this problem (doing this ECom
        will see the initial discount line, which is in AUTHORIZE, and pass the
        validation for our new discount order line).  */
        $connection->setGetPaymentMatchKeys(
            keepKeys: ['artNo', 'description', 'unitMeasure']
        );

        // Refund payment.
        $connection->creditPayment(
            paymentId: $paymentId,
            customPayloadItemList: [],
            runOnce: false,
            skipSpecValidation: true,
            specificSpecLines: true
        );
    }

    /**
     * @param OrderInterface $order
     * @param array $commandSubject
     * @return void
     * @throws AlreadyExistsException
     * @throws LocalizedException
     * @throws PaymentException
     * @throws ValidatorException
     * @throws ResursException
     * @throws InvalidDataException
     * @throws ExceptionHandler
     */
    private function old(
        OrderInterface $order,
        array $commandSubject
    ) {
        if (!$this->api->paymentExists(order: $order)) {
            return;
        }

        // Shortcut for improved readability.
        $history = &$this->paymentHistory;

        // Resolve data from command subject.
        $data = SubjectReader::readPayment(subject: $commandSubject);
        $paymentId = $data->getOrder()->getOrderIncrementId();

        try {
            // Establish API connection.
            $connection = $this->apiPayment->getConnectionCommandSubject(paymentData: $data);

            // Log command being called.
            $history->entryFromCmd(data: $data, event: History::EVENT_REFUND_CALLED);

            // Skip refunding online if payment is already refunded.
            if ($connection->canCredit(paymentArrayOrPaymentId: $paymentId)) {
                $this->refund(data: $data, connection: $connection, paymentId: $paymentId);
            }
        } catch (Exception $e) {
            // Log error.
            $this->log->exception($e);
            $history->entryFromCmd(data: $data, event: History::EVENT_REFUND_FAILED);

            // Pass safe error upstream.
            throw new PaymentException(phrase: __('Failed to refund payment.'));
        }
    }

    /**
     * @param OrderInterface $order
     * @return void
     * @throws JsonException
     * @throws InputException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     */
    private function mapi(OrderInterface $order): void
    {
        $id = $this->orderHelper->getPaymentId(order: $order);
        $payment = Repository::get(paymentId: $id);

        if (!$payment->canRefund() ||
            $payment->status === Status::TASK_REDIRECTION_REQUIRED
        ) {
            return;
        }

        Repository::refund(paymentId: $id);
    }
}
