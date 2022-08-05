<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Model;

use Magento\Framework\Model\AbstractModel;
use Resursbank\Ordermanagement\Api\Data\PaymentInterface;
use Resursbank\Ordermanagement\Model\ResourceModel\Payment as ResourceModel;

class Payment extends AbstractModel implements PaymentInterface
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
    public function getReference(): ?string
    {
        $data = $this->_getData(self::ENTITY_REFERENCE);

        return $data === null ? null : (string)$data;
    }

    /**
     * @inheritDoc
     */
    public function setReference(string $reference): PaymentInterface
    {
        return $this->setData(self::ENTITY_REFERENCE, $reference);
    }

    /**
     * @inheritDoc
     */
    public function getStatus(): ?string
    {
        $data = $this->_getData(self::ENTITY_STATUS);

        return $data === null ? null : (string)$data;
    }

    /**
     * @inheritDoc
     */
    public function setStatus(string $status): PaymentInterface
    {
        return $this->setData(self::ENTITY_STATUS, $status);
    }
}
