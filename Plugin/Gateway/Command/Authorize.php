<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Plugin\Gateway\Command;

use Exception;
use Magento\Framework\Exception\PaymentException;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Store\Model\StoreManagerInterface;
use Resursbank\Core\Gateway\Command\Authorize as Subject;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Api\PaymentHistoryRepositoryInterface;
use Resursbank\Ordermanagement\Model\PaymentHistoryFactory;
use Resursbank\Simplified\Helper\Config;
use Resursbank\Simplified\Helper\Log;

/**
 * Create payment session at Resurs Bank and prepare redirecting client to the
 * gateway for payment processing.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Authorize
{
    /**
     * @var Log
     */
    private Log $log;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

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
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param OrderRepositoryInterface $orderRepo
     * @param PaymentHistoryRepositoryInterface $phRepository
     * @param PaymentHistoryFactory $phFactory
     */
    public function __construct(
        Log $log,
        Config $config,
        StoreManagerInterface $storeManager,
        OrderRepositoryInterface $orderRepo,
        PaymentHistoryRepositoryInterface $phRepository,
        PaymentHistoryFactory $phFactory
    ) {
        $this->log = $log;
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->orderRepo = $orderRepo;
        $this->phRepository = $phRepository;
        $this->phFactory = $phFactory;
    }

    /**
     * @param Subject $subject
     * @param null|ResultInterface $result
     * @param array<mixed> $data
     * @return ResultInterface|null
     * @throws PaymentException
     * @noinspection PhpUnusedParameterInspection
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(
        Subject $subject,
        ?ResultInterface $result,
        array $data
    ): ?ResultInterface {
        try {
            $storeCode = $this->storeManager->getStore()->getCode();

            if ($this->config->isActive($storeCode)) {
                $payment = SubjectReader::readPayment($data)->getPayment();

                if ($payment instanceof Payment) {
                    $order = $payment->getOrder();
                    $payment2 = $order->getPayment();
                    $payment2EnId = $payment2->getEntityId();

                    /* @noinspection PhpUndefinedMethodInspection */
                    $entry = $this->phFactory->create();
                    $paymentId = $payment->getId();
                    $paymentEnId = $payment->getEntityId();
                    $paymentQuId = $payment->getQuotePaymentId();
                    $asd = 123;

                    $entry
                        ->setPaymentId((int) $payment2EnId)
                        ->setEvent('update')
                        ->setUser(PaymentHistoryInterface::USER_RESURS_BANK)
                        ->setStatusFrom($order->getStatus())
                        ->setStatusTo(Order::STATE_PENDING_PAYMENT)
                        ->setExtra(
                            'Payment authorization started. Client will be ' .
                            'sent to gateway.'
                        );

                    $order->setStatus(Order::STATE_PENDING_PAYMENT);

                    $this->orderRepo->save($order);
                    $this->phRepository->save($entry);
                }
            }
        } catch (Exception $e) {
            $this->log->exception($e);

            throw new PaymentException(__(
                'Something went wrong when trying to place the order. ' .
                'Please try again, or select another payment method. You ' .
                'could also try refreshing the page.'
            ));
        }

        return null;
    }
}
