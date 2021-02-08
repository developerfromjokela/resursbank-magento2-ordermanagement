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
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Resursbank\Core\Helper\PaymentMethods;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Api\PaymentHistoryRepositoryInterface;
use Resursbank\Ordermanagement\Helper\Log;
use RuntimeException;

class PaymentHistory implements ArgumentInterface
{
    /**
     * @var PaymentHistoryRepositoryInterface
     */
    private $repository;

    /**
     * @var PaymentMethods
     */
    private $paymentMethods;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchBuilder;

    /**
     * @var Log
     */
    private $log;

    /**
     * @param PaymentMethods $paymentMethods
     * @param PaymentHistoryRepositoryInterface $repository
     * @param SearchCriteriaBuilder $searchBuilder
     * @param Log $log
     */
    public function __construct(
        PaymentMethods $paymentMethods,
        PaymentHistoryRepositoryInterface $repository,
        SearchCriteriaBuilder $searchBuilder,
        Log $log
    ) {
        $this->paymentMethods = $paymentMethods;
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
    public function getEvents(Order $order): array
    {
        $items = [];

        try {
            if (!($order->getPayment() instanceof OrderPaymentInterface)) {
                throw new RuntimeException(
                    __('Missing payment data on order %1', $order->getId())
                );
            }

            $criteria = $this->searchBuilder->addFilter(
                PaymentHistoryInterface::ENTITY_PAYMENT_ID,
                $order->getPayment()->getEntityId()
            )->create();

            $items = $this->repository
                ->getList($criteria)
                ->getItems();
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
     * @return array
     */
    public function eventToTableData(PaymentHistoryInterface $event): array
    {
        $eventAction = $event->getEvent();
        $user = $event->getUser();

        return [
            'event' => $eventAction === null ? '' : $event->eventLabel($eventAction),
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
     * @return array
     */
    public function getTableDataFromOrder(Order $order): array
    {
        $arr = [];

        foreach ($this->getEvents($order) as $event) {
            $arr[] = $this->eventToTableData($event);
        }

        return $arr;
    }

    /**
     * Get the Payment History modal heading.
     *
     * @param Order $order
     * @return string
     */
    public function getHeading(Order $order): string
    {
        return '#' . $order->getIncrementId() . ' Payment History [' .
            $this->getOrderEnvironment($order) . ']';
    }

    /**
     * Get the environment that was selected in the Resurs Bank configuration
     * at the time when the order was placed.
     *
     * @param Order $order
     * @return string
     */
    public function getOrderEnvironment(Order $order): string
    {
        return (string) $order->getData('resursbank_environment');
    }

    /**
     * Check to see if payment history should be visible.
     *
     * @param Order $order
     * @return boolean
     */
    public function visible(Order $order) : bool
    {
        if (!($order->getPayment() instanceof OrderPaymentInterface)) {
            return false;
        }

        $code = $order->getPayment()->getMethod();

        return $this->paymentMethods->isResursBankMethod($code);
    }
}
