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
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\PaymentException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Sales\Api\Data\OrderInterface;
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
use ResursException;
use TorneLIB\Exception\ExceptionHandler;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Cancel implements CommandInterface
{
    use CommandTraits;

    /**
     * @param Log $log
     * @param ApiPayment $apiPayment
     * @param PaymentHistory $paymentHistory
     * @param Api $api
     * @param OrderRepository $orderRepo
     * @param Config $config
     * @param Order $orderHelper
     */
    public function __construct(
        private readonly Log $log,
        private readonly ApiPayment $apiPayment,
        private readonly PaymentHistory $paymentHistory,
        private readonly Api $api,
        private readonly OrderRepository $orderRepo,
        private readonly Config $config,
        private readonly Order $orderHelper
    ) {
    }

    /**
     * @param array $commandSubject
     * @return ResultInterface|null
     * @throws AlreadyExistsException
     * @throws PaymentException
     * @throws InputException
     * @throws NoSuchEntityException|LocalizedException
     */
    public function execute(
        array $commandSubject
    ): ?ResultInterface {
        // Resolve data from command subject.
        $order = $this->getOrder(commandSubject: $commandSubject);

        try {
            if ($this->config->isMapiActive(scopeCode: $order->getStoreId())) {
                $this->mapi(order: $order);
            } else {
                $this->old(order: $order, commandSubject: $commandSubject);
            }
        } catch (Exception $e) {
            // Log error.
            $this->log->exception(error: $e);
            $this->paymentHistory->entryFromCmd(data: $data, event: History::EVENT_CANCEL_FAILED);

            // Pass safe error upstream.
            throw new PaymentException(phrase: __('Failed to cancel payment.'));
        }

        return null;
    }

    /**
     * @param OrderInterface $order
     * @param array $commandSubject
     * @return void
     * @throws AlreadyExistsException
     * @throws ExceptionHandler
     * @throws InputException
     * @throws InvalidDataException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws PaymentDataException
     * @throws ResursException
     * @throws ValidatorException
     * @throws Exception
     */
    private function old(
        OrderInterface $order,
        array $commandSubject
    ): void {
        if (!$this->api->paymentExists(order: $order)) {
            return;
        }

        $data = SubjectReader::readPayment(subject: $commandSubject);

        // Establish API connection.
        $connection = $this->apiPayment->getConnectionCommandSubject(paymentData: $data);

        // Log command being called.
        $this->paymentHistory->entryFromCmd(data: $data, event: History::EVENT_CANCEL_CALLED);
        if ($connection->canAnnul(paymentArrayOrPaymentId: $data->getOrder()->getOrderIncrementId())) {
            // Log API method being called.
            $this->paymentHistory->entryFromCmd(data: $data, event: History::EVENT_CANCEL_API_CALLED);

            // Cancel payment.
            $connection->annulPayment(paymentId: $data->getOrder()->getOrderIncrementId());
        }
    }

    /**
     * @param OrderInterface $order
     * @return void
     * @throws JsonException
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
    private function mapi(
        OrderInterface $order
    ): void {
        $id = $this->orderHelper->getPaymentId($order);

        $payment = Repository::get(paymentId: $id);

        if (
            !$payment->canCancel() ||
            $payment->status === Status::TASK_REDIRECTION_REQUIRED
        ) {
            return;
        }

        Repository::cancel(paymentId: $id);
    }
}
