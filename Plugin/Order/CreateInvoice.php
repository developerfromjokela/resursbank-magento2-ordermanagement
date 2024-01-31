<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Plugin\Order;

use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Helper\Config;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Magento\Sales\Model\Order\Payment\Operations\SaleOperation;
use Resursbank\Ordermanagement\Helper\ResursbankStatuses;
use Resursbank\Core\Helper\PaymentMethods;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Resursbank\Ordermanagement\Model\PaymentHistoryFactory;
use Resursbank\Ordermanagement\Api\PaymentHistoryRepositoryInterface;

/**
 * Perform sale operation when order status changes to 'resursbank_finalized'.
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CreateInvoice
{
    /**
     * @param Log $log
     * @param SaleOperation $saleOperation
     * @param PaymentMethods $paymentMethods
     * @param PaymentHistoryFactory $phFactory
     * @param PaymentHistoryRepositoryInterface $phRepository
     * @param SearchCriteriaBuilder $searchBuilder
     * @param Config $config
     */
    public function __construct(
        private readonly Log $log,
        private readonly SaleOperation $saleOperation,
        private readonly PaymentMethods $paymentMethods,
        private readonly PaymentHistoryFactory $phFactory,
        private readonly PaymentHistoryRepositoryInterface $phRepository,
        private readonly SearchCriteriaBuilder $searchBuilder,
        private readonly Config $config
    ) {
    }

    /**
     * Creates invoice if one should be created.
     *
     * @param OrderInterface $order
     * @param OrderInterface $result
     * @return OrderInterface
     */
    public function afterSetStatus(
        OrderInterface $order,
        OrderInterface $result
    ): OrderInterface {
        try {
            if ($this->isEnabled(order: $order) &&
                $order->getPayment() instanceof OrderPaymentInterface
            ) {
                /** @noinspection NestedPositiveIfStatementsInspection */
                /* Since there is a potential for race conditions in this plugin
                we need the following validation to finish as quickly as
                possible, for this reason it's not merged with its parent. */
                if (!$this->hasCreatedInvoice(payment: $order->getPayment())) {
                    $this->trackPaymentHistoryEvent(
                        payment: $order->getPayment()
                    );
                    $this->log->info(
                        text: 'Invoicing ' . $order->getIncrementId()
                    );
                    $this->saleOperation->execute(
                        payment: $order->getPayment()
                    );
                }
            }
        } catch (Exception $e) {
            $this->log->exception(error: $e);
        }

        return $result;
    }

    /**
     * Track payment history event.
     *
     * @param OrderPaymentInterface $payment
     * @return void
     * @throws AlreadyExistsException
     */
    private function trackPaymentHistoryEvent(
        OrderPaymentInterface $payment
    ): void {
        $entry = $this->phFactory->create();
        $entry
            ->setPaymentId(identifier: (int) $payment->getEntityId())
            ->setEvent(event: PaymentHistoryInterface::EVENT_INVOICE_CREATED)
            ->setUser(user: PaymentHistoryInterface::USER_RESURS_BANK);
        $this->phRepository->save(entry: $entry);
    }

    /**
     * Check to see if an order has already been subject to invoice creation.
     *
     * @param OrderPaymentInterface $payment
     * @return bool
     * @throws LocalizedException
     */
    public function hasCreatedInvoice(
        OrderPaymentInterface $payment
    ): bool {
        $criteria = $this->searchBuilder->addFilter(
            field: PaymentHistoryInterface::ENTITY_PAYMENT_ID,
            value: $payment->getEntityId()
        )->addFilter(
            field: PaymentHistoryInterface::ENTITY_EVENT,
            value: PaymentHistoryInterface::EVENT_INVOICE_CREATED
        )->create();

        /** @var PaymentHistoryInterface[] $items */
        $items = $this->phRepository
            ->getList(searchCriteria: $criteria)
            ->getItems();

        return count($items) > 0;
    }

    /**
     * Check if auto invoice is enabled for order.
     *
     * @param OrderInterface $order
     * @return bool
     */
    public function isEnabled(
        OrderInterface $order
    ): bool {
        return (
            $this->config->isAutoInvoiceEnabled(
                scopeCode: (string) $order->getStoreId()
            ) &&
            $order->getStatus() === ResursbankStatuses::FINALIZED &&
            $order->getPayment() instanceof OrderPaymentInterface &&
            $this->paymentMethods->isResursBankMethod(
                code: $order->getPayment()->getMethod()
            ) &&
            (float) $order->getTotalInvoiced() === 0.0
        );
    }
}
