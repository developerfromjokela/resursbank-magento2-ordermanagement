<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model\Callback;

use Resursbank\Core\Helper\Config;
use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Module\Callback\Http\AuthorizationController;
use Resursbank\Ecom\Module\Callback\Repository;
use Resursbank\Ecom\Lib\Model\Callback\CallbackInterface;
use Resursbank\Ordermanagement\Api\MapiInterface;
use Throwable;
use Resursbank\Core\Helper\Order as OrderHelper;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\Helper\PaymentHistory;
use Resursbank\Ordermanagement\Model\CallbackQueue;
use Magento\Framework\Webapi\Exception as WebapiException;

/**
 * MAPI callback integration.
 */
class Mapi implements MapiInterface
{
    /**
     * @param Log $log
     * @param OrderHelper $orderHelper
     * @param PaymentHistory $paymentHistory
     */
    public function __construct(
        private readonly Log $log,
        private readonly OrderHelper $orderHelper,
        private readonly PaymentHistory $paymentHistory,
        private readonly CallbackQueue $callbackQueue,
        private readonly Config $config
    ) {
    }

    /**
     * Process incoming MAPI authorization callback.
     *
     * @throws WebapiException
     */
    public function authorization(): void
    {
        try {
            $controller = new AuthorizationController();

            $code = Repository::process(
                callback: $controller->getRequestData(),
                process: function (
                    CallbackInterface $callback
                ): void {
                    $order = $this->orderHelper->getOrderFromPaymentId(
                        paymentId: $callback->getPaymentId()
                    );

                    if ($order === null) {
                        throw new HttpException(message: 'Order not found.', code: 503);
                    }

                    if ($this->orderHelper->getResursbankResult(order: $order) !== true) {
                        throw new HttpException(
                            message: 'Order not ready for callbacks yet.',
                            code: 503
                        );
                    }

                    if (!$this->config->isMapiActive(scopeCode: (string) $order->getStoreId())) {
                        throw new HttpException(message: 'MAPI not activated.', code: 503);
                    }

                    $this->paymentHistory->syncOrderStatus(
                        order: $order,
                        event: PaymentHistoryInterface::EVENT_CALLBACK_AUTHORIZATION
                    );
                }
            );
        } catch (Throwable $error) {
            $code = 503;
            $this->log->exception(error: $error);
        }

        if ($code > 299) {
            throw new WebapiException(
                phrase: __('Failed to process authorization callback.'),
                httpCode: $code
            );
        }
    }

    /**
     * Process incoming MAPI test callback.
     *
     * @throws WebapiException
     * @return void
     */
    public function test(): void
    {
        try {
            $this->callbackQueue->test(
                param1: '',
                param2: '',
                param3: '',
                param4: '',
                param5: ''
            );
        } catch (Throwable $error) {
            $this->log->exception(error: $error);

            throw new WebapiException(
                phrase: __('Failed to process test callback.'),
                httpCode: 503
            );
        }
    }
}
