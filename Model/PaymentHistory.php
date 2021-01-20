<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model;

use Magento\Framework\Model\AbstractModel;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Model\ResourceModel\PaymentHistory as ResourceModel;

class PaymentHistory extends AbstractModel implements PaymentHistoryInterface
{
    /**
     * Constructor.
     */
    public function _construct(): void
    {
        $this->_init(ResourceModel::class);
    }

    /**
     * @inheritDoc
     */
    public function getId(int $default = null): int
    {
        $data = $this->_getData('id');

        return $data === null ? $default : (int)$data;
    }

    /**
     * @inheritDoc
     */
    public function getPaymentId(int $default = null): int
    {
        $data = $this->_getData('payment_id');

        return $data === null ? $default : (int)$data;
    }

    /**
     * @inheritDoc
     */
    public function setPaymentId(int $identifier): PaymentHistoryInterface
    {
        return $this->setData('payment_id', $identifier);
    }

    /**
     * @inheritDoc
     */
    public function getEvent(string $default = null): string
    {
        $data = $this->_getData('event');

        return $data === null ? $default : (string)$data;
    }

    /**
     * @inheritDoc
     */
    public function setEvent(string $event): PaymentHistoryInterface
    {
        return $this->setData('event', $event);
    }

    /**
     * @inheritDoc
     */
    public function getUser(int $default = null): int
    {
        $data = $this->_getData('user');

        return $data === null ? $default : (int)$data;
    }

    /**
     * @inheritDoc
     */
    public function setUser(int $user): PaymentHistoryInterface
    {
        return $this->setData('user', $user);
    }

    /**
     * @inheritDoc
     */
    public function getExtra(string $default = null): string
    {
        return $this->_getData('extra') ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function setExtra(string $extra): PaymentHistoryInterface
    {
        return $this->setData('extra', $extra);
    }

    /**
     * @inheritDoc
     */
    public function getStateFrom(string $default = null): string
    {
        return $this->_getData('state_from') ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function setStateFrom(string $state): PaymentHistoryInterface
    {
        return $this->setData('state_from', $state);
    }

    /**
     * @inheritDoc
     */
    public function getStateTo(string $default = null): string
    {
        return $this->_getData('state_to') ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function setStateTo(string $state): PaymentHistoryInterface
    {
        return $this->setData('state_to', $state);
    }

    /**
     * @inheritDoc
     */
    public function getStatusFrom(string $default = null): string
    {
        return $this->_getData('status_from') ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function setStatusFrom(string $status): PaymentHistoryInterface
    {
        return $this->setData('status_from', $status);
    }

    /**
     * @inheritDoc
     */
    public function getStatusTo(string $default = null): string
    {
        return $this->_getData('status_to') ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function setStatusTo(string $status): PaymentHistoryInterface
    {
        return $this->setData('status_to', $status);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(string $default = null): string
    {
        return $this->_getData('created_at') ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt(string $createdAt): PaymentHistoryInterface
    {
        return $this->setData('created_at', $createdAt);
    }

    /**
     * @inheritDoc
     */
    public function eventLabel(string $event): string
    {
        $labels = [
            self::EVENT_CALLBACK_BOOKED => 'Callback "Booked" received.',
            self::EVENT_CALLBACK_UNFREEZE => 'Callback "Unfreeze" received.',
            self::EVENT_CALLBACK_UPDATE => 'Callback "Update" received.'
        ];

        return $labels[$event] ?? '';
    }
}
