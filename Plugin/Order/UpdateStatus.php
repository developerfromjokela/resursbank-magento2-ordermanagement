<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Plugin\Order;

use function constant;
use function sprintf;
use Exception;
use Magento\Checkout\Controller\Onepage\Success;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Resursbank\Core\Helper\Log;
use Resursbank\Core\Helper\Order;
use Resursbank\Core\Helper\Request;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Helper\PaymentHistory;

class UpdateStatus implements ArgumentInterface
{
    /**
     * @var Request
     */
    private Request $request;

    /**
     * @var Log
     */
    private Log $log;

    /**
     * @var Order
     */
    private Order $order;

    /**
     * @var PaymentHistory
     */
    private PaymentHistory $phHelper;

    /**
     * @param Log $log
     * @param Request $request
     * @param Order $order
     * @param PaymentHistory $phHelper
     */
    public function __construct(
        Log $log,
        Request $request,
        Order $order,
        PaymentHistory $phHelper
    ) {
        $this->log = $log;
        $this->request = $request;
        $this->order = $order;
        $this->phHelper = $phHelper;
    }

    /**
     * @param Success $subject
     * @param ResultInterface $result
     * @return ResultInterface
     * @noinspection PhpUnusedParameterInspection
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(
        Success $subject,
        ResultInterface $result
    ): ResultInterface {
        try {
            // We resolve order by quote id to support intermediate browser
            // change during signing.
            $quoteId = $this->request->getQuoteId();

            $this->phHelper->syncOrderStatus(
                $this->order->getOrderByQuoteId($quoteId),
                constant(sprintf(
                    '%s::%s',
                    PaymentHistoryInterface::class,
                    'EVENT_REACHED_ORDER_SUCCESS'
                ))
            );
        } catch (Exception $e) {
            $this->log->exception($e);
        }

        return $result;
    }
}
