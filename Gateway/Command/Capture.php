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
use Magento\Framework\Exception\ValidatorException;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Resursbank\Core\Exception\PaymentDataException;
use Resursbank\Core\Helper\Api;
use Resursbank\Core\Helper\Api\Credentials;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Helper\ApiPayment;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Helper\PaymentHistory;
use ResursException;

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
     * @var Api
     */
    private $api;

    /**
     * @var Credentials
     */
    private $credentials;

    /**
     * @var PaymentHistory
     */
    private $paymentHistory;

    /**
     * @param Log $log
     * @param ApiPayment $apiPayment
     * @param Api $api
     * @param Credentials $credentials
     * @param PaymentHistory $paymentHistory
     */
    public function __construct(
        Log $log,
        ApiPayment $apiPayment,
        Api $api,
        Credentials $credentials,
        PaymentHistory $paymentHistory
    ) {
        $this->log = $log;
        $this->apiPayment = $apiPayment;
        $this->api = $api;
        $this->credentials = $credentials;
        $this->paymentHistory = $paymentHistory;
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
        $paymentData = SubjectReader::readPayment($subject);

        try {
            /** @noinspection PhpUndefinedMethodInspection */
            $this->paymentHistory->createEntry(
                (int) $paymentData->getPayment()->getEntityId(), /** @phpstan-ignore-line */
                PaymentHistoryInterface::EVENT_CAPTURE_CALLED,
                PaymentHistoryInterface::USER_CLIENT
            );

            if ($this->apiPayment->canCapture($paymentData)) {
                $this->capture($paymentData);
            }
        } catch (Exception $e) {
            $this->log->exception($e);

            /** @noinspection PhpUndefinedMethodInspection */
            $this->paymentHistory->createEntry(
                (int) $paymentData->getPayment()->getEntityId(), /** @phpstan-ignore-line */
                PaymentHistoryInterface::EVENT_CAPTURE_FAILED,
                PaymentHistoryInterface::USER_CLIENT
            );

            throw new PaymentException(__('Failed to finalize payment.'));
        }

        return null;
    }

    /**
     * @param PaymentDataObjectInterface $paymentData
     * @throws ValidatorException
     * @throws ResursException
     * @throws PaymentDataException
     * @throws Exception
     */
    private function capture(
        PaymentDataObjectInterface $paymentData
    ): void {
        $connection = $this->api->getConnection(
            $this->credentials->resolveFromConfig()
        );
        $paymentId = $paymentData->getOrder()->getOrderIncrementId();
        $apiPayment = $connection->getPayment($paymentId);
        $orderPayment = $paymentData->getPayment();

        $this->apiPayment->finalizePayment(
            $orderPayment,
            $apiPayment,
            $connection,
            $paymentId
        );

        $this->log->info(
            'Successfully captured payment of order ' . $paymentId
        );
    }
}
