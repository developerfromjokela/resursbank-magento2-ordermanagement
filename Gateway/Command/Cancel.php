<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Gateway\Command;

use Exception;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\PaymentException;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Sales\Model\OrderRepository;
use Resursbank\Core\Helper\Api;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface as History;
use Resursbank\Ordermanagement\Helper\ApiPayment;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Helper\PaymentHistory;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Cancel implements CommandInterface
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
     * @var Api
     */
    private $api;

    /**
     * @var OrderRepository
     */
    private $orderRepo;

    /**
     * @param Log $log
     * @param ApiPayment $apiPayment
     * @param PaymentHistory $paymentHistory
     * @param Api $api
     * @param OrderRepository $orderRepo
     */
    public function __construct(
        Log $log,
        ApiPayment $apiPayment,
        PaymentHistory $paymentHistory,
        Api $api,
        OrderRepository $orderRepo
    ) {
        $this->log = $log;
        $this->api = $api;
        $this->apiPayment = $apiPayment;
        $this->paymentHistory = $paymentHistory;
        $this->orderRepo = $orderRepo;
    }

    /**
     * @param array<mixed> $commandSubject
     * @return ResultInterface|null
     * @throws AlreadyExistsException
     * @throws PaymentException
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function execute(
        array $commandSubject
    ): ?ResultInterface {
        // Shortcut for improved readability.
        $history = &$this->paymentHistory;

        // Resolve data from command subject.
        $data = SubjectReader::readPayment($commandSubject);
        $order = $this->orderRepo->get($data->getOrder()->getId());
        $paymentId = $data->getOrder()->getOrderIncrementId();

        /** @noinspection BadExceptionsProcessingInspection */
        try {
            if ($this->api->paymentExists($order)) {
                // Establish API connection.
                $connection = $this->apiPayment->getConnectionCommandSubject($data);

                // Log command being called.
                $history->entryFromCmd($data, History::EVENT_CANCEL_CALLED);

                if ($connection !== null && $connection->canAnnul($paymentId)) {
                    // Log API method being called.
                    $history->entryFromCmd($data, History::EVENT_CANCEL_API_CALLED);

                    // Cancel payment.
                    $connection->annulPayment($paymentId);
                }
            }
        } catch (Exception $e) {
            // Log error.
            $this->log->exception($e);
            $history->entryFromCmd($data, History::EVENT_CANCEL_FAILED);

            // Pass safe error upstream.
            throw new PaymentException(__('Failed to cancel payment.'));
        }

        return null;
    }
}
