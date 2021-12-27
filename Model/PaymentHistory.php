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
    public function getId(): ?int
    {
        $data = $this->_getData(self::ENTITY_ID);

        return $data === null ? null : (int)$data;
    }

    /**
     * @inheritDoc
     */
    public function getPaymentId(): ?int
    {
        $data = $this->_getData(self::ENTITY_PAYMENT_ID);

        return $data === null ? null : (int)$data;
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
    public function getEvent(): ?string
    {
        $data = $this->_getData(self::ENTITY_EVENT);

        return $data === null ? null : (string)$data;
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
    public function getUser(): ?string
    {
        $data = $this->_getData(self::ENTITY_USER);

        return $data === null ? null : (string)$data;
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
    public function getExtra(): ?string
    {
        $data = $this->_getData(self::ENTITY_EXTRA);

        return $data === null ? null : (string)$data;
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
    public function getStateFrom(): ?string
    {
        $data = $this->_getData(self::ENTITY_STATE_FROM);

        return $data === null ? null : (string)$data;
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
    public function getStateTo(): ?string
    {
        $data = $this->_getData(self::ENTITY_STATE_TO);

        return $data === null ? null : (string)$data;
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
    public function getStatusFrom(): ?string
    {
        $data = $this->_getData(self::ENTITY_STATUS_FROM);

        return $data === null ? null : (string)$data;
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
    public function getStatusTo(): ?string
    {
        $data = $this->_getData(self::ENTITY_STATUS_TO);

        return $data === null ? null : (string)$data;
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
    public function getCreatedAt(): ?string
    {
        $data = $this->_getData(self::ENTITY_CREATED_AT);

        return $data === null ? null : (string)$data;
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
