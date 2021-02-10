<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Api\Data;

interface PaymentHistoryInterface
{
    /**
     * @var string
     */
    public const ENTITY_ID = 'id';

    /**
     * Relationship to Magento\Sales\Model\Order\Payment entity.
     *
     * @var string
     */
    public const ENTITY_PAYMENT_ID = 'payment_id';

    /**
     * @var string
     */
    public const ENTITY_EVENT = 'event';

    /**
     * @var string
     */
    public const ENTITY_USER = 'user';

    /**
     * @var string
     */
    public const ENTITY_EXTRA = 'extra';

    /**
     * @var string
     */
    public const ENTITY_STATE_FROM = 'state_from';

    /**
     * @var string
     */
    public const ENTITY_STATE_TO = 'state_to';

    /**
     * @var string
     */
    public const ENTITY_STATUS_FROM = 'status_from';

    /**
     * @var string
     */
    public const ENTITY_STATUS_TO = 'status_to';

    /**
     * @var string
     */
    public const ENTITY_CREATED_AT = 'created_at';

    /**
     * @var string
     */
    public const EVENT_CALLBACK_UNFREEZE = 'callback_unfreeze';

    /**
     * @var string
     */
    public const EVENT_CALLBACK_BOOKED = 'callback_booked';

    /**
     * @var string
     */
    public const EVENT_CALLBACK_UPDATE = 'callback_update';

    /**
     * @var string
     */
    public const EVENT_CAPTURE_CALLED = 'capture_called';

    /**
     * @array
     */
    public const EVENT_LABELS = [
        self::EVENT_CALLBACK_BOOKED => 'Callback "Booked" received.',
        self::EVENT_CALLBACK_UNFREEZE => 'Callback "Unfreeze" received.',
        self::EVENT_CALLBACK_UPDATE => 'Callback "Update" received.',
        self::EVENT_CAPTURE_CALLED => 'Capture payment was called.'
    ];

    /**
     * @var string
     */
    public const USER_CUSTOMER = 'customer';

    /**
     * @var string
     */
    public const USER_RESURS_BANK = 'resurs_bank';

    /**
     * @var string
     */
    public const USER_CLIENT = 'client';

    /**
     * @var array
     */
    public const USER_LABELS = [
        self::USER_CUSTOMER => 'Customer',
        self::USER_RESURS_BANK => 'Resurs Bank',
        self::USER_CLIENT => 'Client'
    ];

    /**
     * Get ID.
     *
     * @param int|null $default
     * @return int|null
     */
    public function getId(int $default = null): ?int;

    /**
     * Get payment ID.
     *
     * @param int|null $default
     * @return int
     */
    public function getPaymentId(int $default = null): ?int;

    /**
     * Set payment ID.
     *
     * @param int $identifier
     * @return PaymentHistoryInterface
     */
    public function setPaymentId(int $identifier): PaymentHistoryInterface;

    /**
     * Get payment event.
     *
     * @param string|null $default
     * @return string
     */
    public function getEvent(string $default = null): ?string;

    /**
     * Set payment event.
     *
     * @param string $event
     * @return PaymentHistoryInterface
     */
    public function setEvent(string $event): PaymentHistoryInterface;

    /**
     * Get user that triggered the event.
     *
     * @param string|null $default
     * @return string
     */
    public function getUser(string $default = null): ?string;

    /**
     * Set user that triggered the event.
     *
     * @param string $user
     * @return PaymentHistoryInterface
     */
    public function setUser(string $user): PaymentHistoryInterface;

    /**
     * Get extra information about the event.
     *
     * @param string|null $default
     * @return string
     */
    public function getExtra(string $default = null): ?string;

    /**
     * Set extra information about the event. This may for example include the
     * name of an admin (client) that triggered an event or reasons why an event
     * was triggered.
     *
     * @param string|null $extra
     * @return PaymentHistoryInterface
     */
    public function setExtra(?string $extra): PaymentHistoryInterface;

    /**
     * Get the state_from information about the event.
     *
     * @param string|null $default
     * @return string
     */
    public function getStateFrom(string $default = null): ?string;

    /**
     * Set the state that this entry went from.
     *
     * @param string|null $state
     * @return PaymentHistoryInterface
     */
    public function setStateFrom(?string $state): PaymentHistoryInterface;

    /**
     * Get the state_to information about the event.
     *
     * @param string|null $default
     * @return string
     */
    public function getStateTo(string $default = null): ?string;

    /**
     * Set the state that this entry went to.
     *
     * @param string|null $state
     * @return PaymentHistoryInterface
     */
    public function setStateTo(?string $state): PaymentHistoryInterface;

    /**
     * Get the status_from information about the event.
     *
     * @param string|null $default
     * @return string
     */
    public function getStatusFrom(string $default = null): ?string;

    /**
     * Set the status that this entry went from.
     *
     * @param string|null $status
     * @return PaymentHistoryInterface
     */
    public function setStatusFrom(?string $status): PaymentHistoryInterface;

    /**
     * Get the status_to information about the event.
     *
     * @param string|null $default
     * @return string
     */
    public function getStatusTo(string $default = null): ?string;

    /**
     * Set the status that this entry went to.
     *
     * @param string|null $status
     * @return PaymentHistoryInterface
     */
    public function setStatusTo(?string $status): PaymentHistoryInterface;

    /**
     * Get the time when the event was created.
     *
     * @param string|null $default
     * @return string
     */
    public function getCreatedAt(string $default = null): ?string;

    /**
     * Set the time when the event entry was created.
     *
     * @param string $createdAt
     * @return PaymentHistoryInterface
     */
    public function setCreatedAt(string $createdAt): PaymentHistoryInterface;

    /**
     * Get the label for an event.
     *
     * @param string $event
     * @return string
     */
    public function eventLabel(string $event): string;

    /**
     * Get the label for the user.
     *
     * @param string $user
     * @return string
     */
    public function userLabel(string $user): string;
}
