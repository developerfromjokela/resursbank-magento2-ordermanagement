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
     * @noinspection PhpUndefinedClassInspection
     * @var PaymentHistoryFactory
     */
    private $phFactory;

    /**
     * @var PaymentHistoryRepositoryInterface
     */
    private $phRepository;

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
     * @param int $paymentId
     * @param string $event
     * @param string $user
     * @param string|null $stateFrom
     * @param string|null $stateTo
     * @param string|null $statusFrom
     * @param string|null $statusTo
     * @param string|null $extra
     * @return void
     * @throws AlreadyExistsException
     * @noinspection PhpTooManyParametersInspection
     */
    public function createEntry(
        int $paymentId,
        string $event,
        string $user = PaymentHistoryInterface::USER_RESURS_BANK,
        ?string $stateFrom = null,
        ?string $stateTo = null,
        ?string $statusFrom = null,
        ?string $statusTo = null,
        ?string $extra = null
    ): void {
        /* @noinspection PhpUndefinedMethodInspection */
        $entry = $this->phFactory->create();

        $entry
            ->setPaymentId($paymentId)
            ->setEvent($event)
            ->setUser($user)
            ->setExtra($extra)
            ->setStateFrom($stateFrom)
            ->setStateTo($stateTo)
            ->setStatusFrom($statusFrom)
            ->setStatusTo($statusTo);
        $this->phRepository->save($entry);
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
        /** @noinspection PhpUndefinedMethodInspection */
        $this->createEntry(
            (int) $data->getPayment()->getId(), /** @phpstan-ignore-line */
            $event,
            PaymentHistoryInterface::USER_CLIENT
        );
    }
}
