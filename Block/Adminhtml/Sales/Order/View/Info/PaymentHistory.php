<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Block\Adminhtml\Sales\Order\View\Info;

use Exception;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Phrase;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Resursbank\Core\Helper\Ecom;
use Resursbank\Core\Helper\Order as OrderHelper;
use Resursbank\Core\Helper\PaymentMethods;
use Resursbank\Ecom\Module\PaymentHistory\Repository;
use Resursbank\Ecom\Module\PaymentHistory\Widget\Log as Widget;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Api\PaymentHistoryRepositoryInterface;
use Resursbank\Ordermanagement\Helper\Log;
use RuntimeException;
use Throwable;

/**
 * Implementation of payment history widget for order view.
 *
 * This block implements the widget for deprecated and modern API:s alike.
 * Methods utilised exclusively by deprecated API:s are marked in their
 * docblocks.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PaymentHistory extends EcomWidget
{
    /**
     * @var array|null $paymentHistoryItems
     */
    private ?array $paymentHistoryItems = null;

    /**
     * @param Context $context
     * @param InvoiceRepositoryInterface $invoiceRepo
     * @param CreditmemoRepositoryInterface $creditmemoRepo
     * @param Log $log
     * @param OrderRepositoryInterface $orderRepository
     * @param PaymentMethods $paymentMethods
     * @param OrderHelper $orderHelper
     * @param Ecom $ecom
     * @param PaymentHistoryRepositoryInterface $repository
     * @param SearchCriteriaBuilder $searchBuilder
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        InvoiceRepositoryInterface $invoiceRepo,
        CreditmemoRepositoryInterface $creditmemoRepo,
        Log $log,
        OrderRepositoryInterface $orderRepository,
        PaymentMethods $paymentMethods,
        OrderHelper $orderHelper,
        Ecom $ecom,
        private readonly PaymentHistoryRepositoryInterface $repository,
        private readonly SearchCriteriaBuilder $searchBuilder,
        array $data = []
    ) {
        parent::__construct(
            context: $context,
            templateDir: 'payment-history',
            invoiceRepo: $invoiceRepo,
            creditmemoRepo: $creditmemoRepo,
            log: $log,
            orderRepository: $orderRepository,
            paymentMethods: $paymentMethods,
            orderHelper: $orderHelper,
            ecom: $ecom,
            data: $data
        );
    }

    /**
     * Resolve modern Ecom widget.
     *
     * @return Widget|null
     */
    public function getWidget(): ?Widget
    {
        try {
            $entries = Repository::getList(
                paymentId: $this->orderHelper->getPaymentId(order: $this->order)
            );

            if ($entries !== null) {
                return new Widget(entries: $entries);
            }
        } catch (Throwable $error) {
            $this->log->exception(error: $error);
        }

        return null;
    }

    /**
     * Get table data from order.
     *
     * Fetch payment history events relating to provided order and convert the
     * data to a presentational form.
     *
     * NOTE: Utilised exclusively by deprecated API:s.
     *
     * @return string
     */
    public function getTableDataFromOrder(): string
    {
        $data = [];

        foreach ($this->getEvents() as $event) {
            $data[] = $this->eventToTableData($event);
        }

        try {
            $result = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            $this->log->exception($e);
        }

        return $result;
    }

    /**
     * Get the Payment History modal heading.
     *
     * NOTE: Utilised exclusively by deprecated API:s.
     *
     * @return Phrase
     */
    public function getHeading(): Phrase
    {
        return __(
            'rb-payment-history',
            $this->order->getIncrementId(),
            $this->getOrderEnvironment()
        );
    }

    /**
     * Get order environment.
     *
     * Get the environment that was selected in the Resurs Bank configuration
     * at the time when the order was placed.
     *
     * NOTE: Utilised exclusively by deprecated API:s.
     *
     * @return string
     */
    private function getOrderEnvironment(): string
    {
        return (bool) $this->order->getData('resursbank_is_test')
            ? 'Test'
            : 'Production';
    }

    /**
     * Fetch payment history events relating to provided order.
     *
     * NOTE: Utilised exclusively by deprecated API:s.
     *
     * @return PaymentHistoryInterface[]
     */
    private function getEvents(): array
    {
        $items = [];

        try {
            if ($this->paymentHistoryItems === null) {
                if (!( $this->order->getPayment() instanceof OrderPaymentInterface)) {
                    throw new RuntimeException(
                        'Missing payment data on order ' .  $this->order->getId()
                    );
                }

                $criteria = $this->searchBuilder
                    ->addFilter(
                        PaymentHistoryInterface::ENTITY_PAYMENT_ID,
                        $this->order->getPayment()->getEntityId()
                    )->create();

                /** @var PaymentHistoryInterface[] $items */
                $items = $this->repository
                    ->getList($criteria)
                    ->getItems();

                $this->paymentHistoryItems = $items;
            } else {
                $items = $this->paymentHistoryItems;
            }
        } catch (Exception $e) {
            $this->log->error(
                'Could not retrieve list of payment history events for ' .
                'order #' .  $this->order->getIncrementId() . ' : ' . $e->getMessage()
            );
        }

        return $items;
    }

    /**
     * Converts data from a payment history event to presentational data.
     *
     * NOTE: Utilised exclusively by deprecated API:s.
     *
     * @param PaymentHistoryInterface $event
     * @return array<string, string|null>
     */
    private function eventToTableData(
        PaymentHistoryInterface $event
    ): array {
        $eventAction = $event->getEvent();
        $eventLabel = $event->eventLabel((string) $eventAction);
        $user = $event->getUser();

        return [
            'event' => $eventAction === null ? '' : $eventLabel,
            'user' => $user === null ? '' : $event->userLabel($user),
            'timestamp' => $event->getCreatedAt() ?? '',
            'extra' => $event->getExtra() ?? '',
            'state_from' => $event->getStateFrom() ?? '',
            'state_to' => $event->getStateTo() ?? '',
            'status_from' => $event->getStatusFrom() ?? '',
            'status_to' => $event->getStatusTo() ?? ''
        ];
    }
}
