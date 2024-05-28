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
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Payment;
use Resursbank\Core\Exception\PaymentDataException;
use Resursbank\Core\Gateway\Command;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface as History;
use Resursbank\Ordermanagement\Helper\ApiPayment;
use Resursbank\Ordermanagement\Helper\Config;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Helper\PaymentHistory;
use Resursbank\Ordermanagement\Model\Api\Payment\Converter\CreditmemoConverter;
use Resursbank\RBEcomPHP\ResursBank;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Refund extends Command implements CommandInterface
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
     * @param Config $configHelper
     * @param StoreManagerInterface $storeManager
     * @param OrderRepository $orderRepository
     */
    public function __construct(
        Log $log,
        ApiPayment $apiPayment,
        PaymentHistory $paymentHistory,
        CreditmemoConverter $creditmemoConverter,
        private readonly Config $configHelper,
        private readonly StoreManagerInterface $storeManager,
        private readonly OrderRepository $orderRepository
    ) {
        $this->log = $log;
        $this->apiPayment = $apiPayment;
        $this->paymentHistory = $paymentHistory;
        $this->creditmemoConverter = $creditmemoConverter;
    }

    /**
     * Execution entrypoint.
     *
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
    public function getCreditmemo(
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

        // Log API method being called.
        $history->entryFromCmd(
            $data,
            History::EVENT_REFUND_API_CALLED
        );

        // Add items to API payload.
        $this->addOrderLines(
            $connection,
            $this->creditmemoConverter->convert(
                $this->getCreditmemo($this->getPayment($data))
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
            ['artNo', 'description', 'unitMeasure']
        );

        // Refund payment.
        $connection->creditPayment($paymentId, [], false, true, true);
    }
}
