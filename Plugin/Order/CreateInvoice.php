<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Plugin\Order;

use Exception;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Sales\Api\Data\OrderInterface;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Helper\Config;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Magento\Sales\Model\Order\Payment\Operations\SaleOperation;
use Resursbank\Ordermanagement\Helper\ResursbankStatuses;
use Resursbank\Core\Helper\PaymentMethods;
use Resursbank\Core\Api\PaymentMethodRepositoryInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Resursbank\Ordermanagement\Model\PaymentHistoryFactory;
use Resursbank\Ordermanagement\Api\PaymentHistoryRepositoryInterface;

/**
 * Perform sale operation when order status changes to 'resursbank_finalized'.
 */
class CreateInvoice
{
    /**
     * @var Log
     */
    private Log $log;

    /**
     * @var SaleOperation
     */
    private SaleOperation $saleOperation;

    /**
     * @var PaymentMethods
     */
    private PaymentMethods $paymentMethods;

    /**
     * @var PaymentMethodRepositoryInterface
     */
    private PaymentMethodRepositoryInterface $paymentMethodRepository;

    /**
     * @var PaymentHistoryFactory
     */
    private PaymentHistoryFactory $phFactory;

    /**
     * @var PaymentHistoryRepositoryInterface
     */
    private PaymentHistoryRepositoryInterface $phRepository;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @param Log $log
     * @param SaleOperation $saleOperation
     * @param PaymentMethods $paymentMethods
     * @param PaymentMethodRepositoryInterface $paymentMethodRepository
     * @param PaymentHistoryFactory $phFactory
     * @param PaymentHistoryRepositoryInterface $phRepository
     * @param Config $config
     */
    public function __construct(
        Log $log,
        SaleOperation $saleOperation,
        PaymentMethods $paymentMethods,
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        PaymentHistoryFactory $phFactory,
        PaymentHistoryRepositoryInterface $phRepository,
        Config $config
    ) {
        $this->log = $log;
        $this->saleOperation = $saleOperation;
        $this->paymentMethods = $paymentMethods;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->phFactory = $phFactory;
        $this->phRepository = $phRepository;
        $this->config = $config;
    }

    /**
     * @param OrderInterface $order
     * @param OrderInterface $result
     * @return OrderInterface
     */
    public function afterSetStatus(
        OrderInterface $order,
        OrderInterface $result
    ): OrderInterface {
        try {
            if ($this->isEnabled($order) &&
                $order->getPayment() instanceof OrderPaymentInterface
            ) {
                $this->log->info('Invoicing order ' . $order->getIncrementId());
                $this->saleOperation->execute($order->getPayment());
                $this->trackPaymentHistoryEvent($order->getPayment());
            }
        } catch (Exception $e) {
            $this->log->exception($e);
        }

        return $result;
    }

    /**
     * @param OrderPaymentInterface $payment
     * @return void
     * @throws AlreadyExistsException
     */
    private function trackPaymentHistoryEvent(
        OrderPaymentInterface $payment
    ): void {
        $entry = $this->phFactory->create();
        $entry
            ->setPaymentId((int) $payment->getEntityId())
            ->setEvent(PaymentHistoryInterface::EVENT_INVOICE_CREATED)
            ->setUser(PaymentHistoryInterface::USER_RESURS_BANK);

        $this->phRepository->save($entry);
    }

    /**
     * @param OrderInterface $order
     * @return bool
     */
    private function isEnabled(
        OrderInterface $order
    ): bool {
        return (
            $this->config->isAutoInvoiceEnabled((string) $order->getStoreId()) &&
            $order->getStatus() === ResursbankStatuses::FINALIZED &&
            $order->getPayment() instanceof OrderPaymentInterface &&
            $this->paymentMethods->isResursBankMethod(
                $order->getPayment()->getMethod()
            ) &&
            (float) $order->getTotalInvoiced() === 0.0
        );
    }
}
