<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Plugin\Order;

use Exception;
use Magento\Checkout\Controller\Onepage\Success;
use Magento\Checkout\Model\Session\SuccessValidator;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Resursbank\Core\Helper\Log;
use Resursbank\Core\Helper\Order;
use Resursbank\Core\Helper\Request;
use Resursbank\Core\ViewModel\Session\Checkout as Session;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Api\PaymentHistoryRepositoryInterface;
use Resursbank\Ordermanagement\Model\Callback;
use Resursbank\Ordermanagement\Model\PaymentHistoryFactory;


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
     * @var SuccessValidator
     */
    private SuccessValidator $successValidator;

    /**
     * @var Session
     */
    private Session $session;

    /**
     * @var Order
     */
    private Order $order;

    /**
     * @var Callback
     */
    private Callback $callback;

    /**
     * @var PaymentHistoryFactory
     */
    private PaymentHistoryFactory $phFactory;

    /**
     * @var PaymentHistoryRepositoryInterface
     */
    private PaymentHistoryRepositoryInterface $phRepository;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepo;

    /**
     * @param Log $log
     * @param Request $request
     * @param Order $order
     * @param Session $session
     * @param SuccessValidator $successValidator
     * @param Callback $callback
     */
    public function __construct(
        Log $log,
        Request $request,
        Order $order,
        Session $session,
        SuccessValidator $successValidator,
        Callback $callback,
        PaymentHistoryFactory $phFactory,
        PaymentHistoryRepositoryInterface $phRepository,
        OrderRepositoryInterface $orderRepo
    ) {
        $this->log = $log;
        $this->request = $request;
        $this->order = $order;
        $this->session = $session;
        $this->successValidator = $successValidator;
        $this->callback = $callback;
        $this->phFactory = $phFactory;
        $this->phRepository = $phRepository;
        $this->orderRepo = $orderRepo;
    }

    /**
     * @param Success $subject
     * @param ResultInterface $result
     * @return ResultInterface
     */
    public function afterExecute(
        Success $subject,
        ResultInterface $result
    ): ResultInterface {
        try {
            $quoteId = $this->request->getQuoteId();
            $order = $this->order->getOrderByQuoteId($quoteId);

            /* @noinspection PhpUndefinedMethodInspection */
            $entry = $this->phFactory->create();
            $payment = $order->getPayment();

            if (!($payment instanceof OrderPaymentInterface)) {
                throw new LocalizedException(__(
                    'Payment does not exist for order ' .
                    $order->getIncrementId()
                ));
            }

            $newOrderStatus = $this->callback->getOrderStatusFromResurs($order);

            $entry
                ->setPaymentId((int) $payment->getEntityId())
                ->setEvent('update')
                ->setUser(PaymentHistoryInterface::USER_RESURS_BANK)
                ->setStateFrom($order->getState())
                ->setStateTo($this->callback->getOrderStateFromResurs($order))
                ->setStatusFrom($order->getStatus())
                ->setStatusTo($this->callback->getOrderStatusFromResurs($order));

            $order->setStatus($newOrderStatus);

            $this->orderRepo->save($order);
            $this->phRepository->save($entry);
        } catch (Exception $e) {
            $this->log->exception($e);
        }

        return $result;
    }
}
