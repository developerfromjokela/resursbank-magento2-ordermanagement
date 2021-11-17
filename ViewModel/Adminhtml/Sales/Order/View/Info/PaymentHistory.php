<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\ViewModel\Adminhtml\Sales\Order\View\Info;

use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\Phrase;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Api\PaymentHistoryRepositoryInterface;
use Resursbank\Ordermanagement\Helper\Log;
use RuntimeException;

class PaymentHistory implements ArgumentInterface
{
    /**
     * @var PaymentHistoryRepositoryInterface
     */
    private PaymentHistoryRepositoryInterface $repository;

    /**
     * @var SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $searchBuilder;

    /**
     * @var Log
     */
    private Log $log;

    /**
     * @var PaymentHistoryInterface[]|null
     */
    private ?array $paymentHistoryItems = null;

    /**
     * @param PaymentHistoryRepositoryInterface $repository
     * @param SearchCriteriaBuilder $searchBuilder
     * @param Log $log
     */
    public function __construct(
        PaymentHistoryRepositoryInterface $repository,
        SearchCriteriaBuilder $searchBuilder,
        Log $log
    ) {
        $this->repository = $repository;
        $this->searchBuilder = $searchBuilder;
        $this->log = $log;
    }

    /**
     * Fetch payment history events relating to provided order.
     *
     * @param Order $order
     * @return PaymentHistoryInterface[]
     */
    public function getEvents(
        Order $order
    ): array {
        $items = [];

        try {
            if ($this->paymentHistoryItems === null) {
                if (!($order->getPayment() instanceof OrderPaymentInterface)) {
                    throw new RuntimeException(
                        'Missing payment data on order ' . $order->getId()
                    );
                }

                $criteria = $this->searchBuilder->addFilter(
                    PaymentHistoryInterface::ENTITY_PAYMENT_ID,
                    $order->getPayment()->getEntityId()
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
                'order #' . $order->getIncrementId() . ' : ' . $e->getMessage()
            );
        }

        return $items;
    }

    /**
     * Converts data from a payment history event to presentational data.
     *
     * @param PaymentHistoryInterface $event
     * @return array<string, string|null>
     */
    public function eventToTableData(
        PaymentHistoryInterface $event
    ): array {
        $eventAction = $event->getEvent();
        $eventLabel = $event->eventLabel((string) $eventAction);
        $user = $event->getUser();

        return [
            'event' => $eventAction === null ? '' : $eventLabel,
            'user' => $user === null ? '' : $event->userLabel($user),
            'timestamp' => $event->getCreatedAt(''),
            'extra' => $event->getExtra(''),
            'state_from' => $event->getStateFrom(''),
            'state_to' => $event->getStateTo(''),
            'status_from' => $event->getStatusFrom(''),
            'status_to' => $event->getStatusTo('')
        ];
    }

    /**
     * Fetch payment history events relating to provided order and convert the
     * data to a presentational form.
     *
     * @param Order $order
     * @return string
     */
    public function getTableDataFromOrder(
        Order $order
    ): string {
        $result = '{}';
        $data = [];

        foreach ($this->getEvents($order) as $event) {
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
     * @param Order $order
     * @return Phrase
     */
    public function getHeading(
        Order $order
    ): Phrase {
        return __(
            '#%1 Payment History [%2]',
            $order->getIncrementId(),
            $this->getOrderEnvironment($order)
        );
    }

    /**
     * Get the environment that was selected in the Resurs Bank configuration
     * at the time when the order was placed.
     *
     * @param Order $order
     * @return string
     */
    public function getOrderEnvironment(
        Order $order
    ): string {
        return (bool) $order->getData('resursbank_is_test')
            ? 'Test'
            : 'Production';
    }

    /**
     * Check to see if payment history should be visible.
     *
     * @param Order $order
     * @return bool
     */
    public function visible(
        Order $order
    ) : bool {
        if (!($order->getPayment() instanceof OrderPaymentInterface)) {
            return false;
        }

        return !empty($this->getEvents($order));
    }
}
