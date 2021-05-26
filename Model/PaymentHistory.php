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
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     * @noinspection MagicMethodsValidityInspection
     * @noinspection PhpMissingParentCallCommonInspection
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel::class);
    }

    /**
     * @inheritDoc
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function getId(int $default = null): ?int
    {
        $data = $this->_getData(self::ENTITY_ID);

        return $data === null ? $default : (int)$data;
    }

    /**
     * @inheritDoc
     */
    public function getPaymentId(int $default = null): ?int
    {
        $data = $this->_getData(self::ENTITY_PAYMENT_ID);

        return $data === null ? $default : (int)$data;
    }

    /**
     * @inheritDoc
     */
    public function setPaymentId(int $identifier): PaymentHistoryInterface
    {
        return $this->setData(self::ENTITY_PAYMENT_ID, $identifier);
    }

    /**
     * @inheritDoc
     */
    public function getEvent(string $default = null): ?string
    {
        return $this->_getData(self::ENTITY_EVENT) ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function setEvent(string $event): PaymentHistoryInterface
    {
        return $this->setData(self::ENTITY_EVENT, $event);
    }

    /**
     * @inheritDoc
     */
    public function getUser(string $default = null): ?string
    {
        return $this->_getData(self::ENTITY_USER) ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function setUser(string $user): PaymentHistoryInterface
    {
        return $this->setData(self::ENTITY_USER, $user);
    }

    /**
     * @inheritDoc
     */
    public function getExtra(string $default = null): ?string
    {
        return $this->_getData(self::ENTITY_EXTRA) ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function setExtra(?string $extra): PaymentHistoryInterface
    {
        return $this->setData(self::ENTITY_EXTRA, $extra);
    }

    /**
     * @inheritDoc
     */
    public function getStateFrom(string $default = null): ?string
    {
        return $this->_getData(self::ENTITY_STATE_FROM) ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function setStateFrom(?string $state): PaymentHistoryInterface
    {
        return $this->setData(self::ENTITY_STATE_FROM, $state);
    }

    /**
     * @inheritDoc
     */
    public function getStateTo(string $default = null): ?string
    {
        return $this->_getData(self::ENTITY_STATE_TO) ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function setStateTo(?string $state): PaymentHistoryInterface
    {
        return $this->setData(self::ENTITY_STATE_TO, $state);
    }

    /**
     * @inheritDoc
     */
    public function getStatusFrom(string $default = null): ?string
    {
        return $this->_getData(self::ENTITY_STATUS_FROM) ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function setStatusFrom(?string $status): PaymentHistoryInterface
    {
        return $this->setData(self::ENTITY_STATUS_FROM, $status);
    }

    /**
     * @inheritDoc
     */
    public function getStatusTo(string $default = null): ?string
    {
        return $this->_getData(self::ENTITY_STATUS_TO) ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function setStatusTo(?string $status): PaymentHistoryInterface
    {
        return $this->setData(self::ENTITY_STATUS_TO, $status);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(string $default = null): ?string
    {
        return $this->_getData(self::ENTITY_CREATED_AT) ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt(string $createdAt): PaymentHistoryInterface
    {
        return $this->setData(self::ENTITY_CREATED_AT, $createdAt);
    }

    /**
     * @inheritDoc
     */
    public function eventLabel(string $event): string
    {
        return self::EVENT_LABELS[$event] ?? '';
    }

    /**
     * @inheritdoc
     */
    public function userLabel(string $user): string
    {
        return self::USER_LABELS[$user] ?? '';
    }
}
