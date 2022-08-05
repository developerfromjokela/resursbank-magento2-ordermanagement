<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Api\Data;

interface PaymentInterface
{
    /** @var int  */
    public const ENTITY_ID = 'id';

    /** @var string  */
    public const ENTITY_REFERENCE = 'reference';

    /** @var string  */
    public const ENTITY_STATUS = 'status';

    /**
     * Get ID.
     *
     * @return int|null
     */
    public function getId(): ?int;

    /**
     * Get Resurs Bank payment reference.
     *
     * @return string|null
     */
    public function getReference(): ?string;

    /**
     * Set Resurs Bank payment reference.
     *
     * @param string $reference
     * @return PaymentInterface
     */
    public function setReference(string $reference): PaymentInterface;

    /**
     * Get Resurs Bank payment status.
     *
     * @return string|null
     */
    public function getStatus(): ?string;

    /**
     * Set Resurs Bank payment status.
     *
     * @param string $status
     * @return PaymentInterface
     */
    public function setStatus(string $status): PaymentInterface;
}
