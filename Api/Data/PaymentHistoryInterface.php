<?php

namespace Resursbank\Ordermanagement\Api\Data;

interface PaymentHistoryInterface
{
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
     * @array
     */
    public const EVENT_LABELS = [
        self::EVENT_CALLBACK_BOOKED => 'Callback "Booked" received.',
        self::EVENT_CALLBACK_UNFREEZE => 'Callback "Unfreeze" received.',
        self::EVENT_CALLBACK_UPDATE => 'Callback "Update" received.'
    ];

    /**
     * Get payment ID.
     *
     * @param int|null $default
     * @return int
     */
    public function getPaymentId(int $default = null): int;

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
    public function getEvent(string $default = null): string;

    /**
     * Set payment event.
     *
     * @param string $event
     * @return PaymentHistoryInterface
     */
    public function setEvent(string $event): PaymentHistoryInterface;

    /**
     * Get user that triggered the event. Users are represented by an integer,
     * which indicates what group that user belongs to.
     *
     * @param int|null $default
     * @return int
     */
    public function getUser(int $default = null): int;

    /**
     * Set user that triggered the event. Users are represented by an integer,
     * which indicates what group that user belongs to.
     *
     * @param int $user
     * @return PaymentHistoryInterface
     */
    public function setUser(int $user): PaymentHistoryInterface;

    /**
     * Get extra information about the event.
     *
     * @param string|null $default
     * @return string
     */
    public function getExtra(string $default = null): string;

    /**
     * Set extra information about the event. This may for example include the
     * name of an admin (client) that triggered an event or reasons why an event
     * was triggered.
     *
     * @param string $extra
     * @return PaymentHistoryInterface
     */
    public function setExtra(string $extra): PaymentHistoryInterface;

    /**
     * Get the state_from information about the event.
     *
     * @param string|null $default
     * @return string
     */
    public function getStateFrom(string $default = null): string;

    /**
     * Set the state that this entry went from.
     *
     * @param string $state
     * @return PaymentHistoryInterface
     */
    public function setStateFrom(string $state): PaymentHistoryInterface;

    /**
     * Get the state_to information about the event.
     *
     * @param string|null $default
     * @return string
     */
    public function getStateTo(string $default = null): string;

    /**
     * Set the state that this entry went to.
     *
     * @param string $state
     * @return PaymentHistoryInterface
     */
    public function setStateTo(string $state): PaymentHistoryInterface;

    /**
     * Get the status_from information about the event.
     *
     * @param string|null $default
     * @return string
     */
    public function getStatusFrom(string $default = null): string;

    /**
     * Set the status that this entry went from.
     *
     * @param string $status
     * @return PaymentHistoryInterface
     */
    public function setStatusFrom(string $status): PaymentHistoryInterface;

    /**
     * Get the status_to information about the event.
     *
     * @param string|null $default
     * @return string
     */
    public function getStatusTo(string $default = null): string;

    /**
     * Set the status that this entry went to.
     *
     * @param string $status
     * @return PaymentHistoryInterface
     */
    public function setStatusTo(string $status): PaymentHistoryInterface;

    /**
     * Get the time when the event was created.
     *
     * @param string|null $default
     * @return string
     */
    public function getCreatedAt(string $default = null): string;

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
}
