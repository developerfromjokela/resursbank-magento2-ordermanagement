<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Plugin\Order;

use Magento\Checkout\Controller\Onepage\Success;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\InputException;
use Magento\Sales\Api\Data\OrderInterface;
use Resursbank\Core\ViewModel\Session\Checkout;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Lib\Model\PaymentHistory\Entry;
use Resursbank\Ecom\Lib\Model\PaymentHistory\Event;
use Resursbank\Ecom\Lib\Model\PaymentHistory\User;
use Resursbank\Ecom\Module\PaymentHistory\Repository;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Core\Helper\Order;
use Throwable;

/**
 * Log history entry when client reaches success page.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class TrackResult
{
    /**
     * @param Log $log
     * @param Checkout $checkout
     * @param Order $orderHelper
     */
    public function __construct(
        private readonly Log $log,
        private readonly Checkout $checkout,
        private readonly Order $orderHelper,
    ) {
    }

    /**
     * Log event.
     *
     * @param Success $subject
     * @param ResultInterface $result
     * @return ResultInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @noinspection PhpUnusedParameterInspection
     */
    public function afterExecute(
        Success $subject,
        ResultInterface $result
    ): ResultInterface {
        try {
            $order = $this->orderHelper->resolveOrderFromRequest(
                lastRealOrder: $this->checkout->getLastRealOrder()
            );

            if ($this->isEnabled(order: $order)) {
                Repository::write(entry: new Entry(
                    paymentId: $this->orderHelper->getPaymentId(order: $order),
                    event: Event::REACHED_ORDER_SUCCESS_PAGE,
                    user: User::CUSTOMER
                ));
            }
        } catch (Throwable $e) {
            $this->log->exception(error: $e);
        }

        return $result;
    }

    /**
     * Whether plugin should execute.
     *
     * @param OrderInterface $order
     * @return bool
     * @throws ConfigException
     * @throws InputException
     */
    public function isEnabled(
        OrderInterface $order
    ): bool {
        return (
            !$this->orderHelper->isLegacyFlow(order: $order) &&
            !Repository::hasExecuted(
                paymentId: $this->orderHelper->getPaymentId(order: $order),
                event: Event::REACHED_ORDER_SUCCESS_PAGE
            )
        );
    }
}
