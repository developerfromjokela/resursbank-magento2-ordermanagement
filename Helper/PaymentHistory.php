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
     * PaymentHistory constructor.
     * @param Context $context
     * @param PaymentHistoryFactory $phFactory
     * @param PaymentHistoryRepositoryInterface $phRepository
     * @noinspection PhpUndefinedClassInspection
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
}
