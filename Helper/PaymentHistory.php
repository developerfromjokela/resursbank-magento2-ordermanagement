<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Api\PaymentHistoryRepositoryInterface;
use Resursbank\Ordermanagement\Model\PaymentHistoryFactory;

class PaymentHistory extends AbstractHelper
{
    /**
     * @var PaymentHistoryFactory
     */
    private PaymentHistoryFactory $phFactory;

    /**
     * @var PaymentHistoryRepositoryInterface
     */
    private PaymentHistoryRepositoryInterface $phRepository;

    /**
     * @param Context $context
     * @param PaymentHistoryFactory $phFactory
     * @param PaymentHistoryRepositoryInterface $phRepository
     */
    public function __construct(
        Context $context,
        PaymentHistoryFactory $phFactory,
        PaymentHistoryRepositoryInterface $phRepository
    ) {
        $this->phFactory = $phFactory;
        $this->phRepository = $phRepository;

        parent::__construct($context);
    }

    /**
     * Create payment history entry from command subject data.
     *
     * @param PaymentDataObjectInterface $data
     * @param string $event
     * @throws AlreadyExistsException
     */
    public function entryFromCmd(
        PaymentDataObjectInterface $data,
        string $event
    ): void {
        /* @noinspection PhpUndefinedMethodInspection */
        $entry = $this->phFactory->create();

        $entry
            ->setPaymentId((int) $data->getPayment()->getId()) /** @phpstan-ignore-line */
            ->setEvent($event)
            ->setUser(PaymentHistoryInterface::USER_CLIENT);

        $this->phRepository->save($entry);
    }
}
