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
    public const EVENT_CALLBACK_UNFREEZE_COMPLETED = 'callback_unfreeze_completed';

    /**
     * @var string
     */
    public const EVENT_CALLBACK_BOOKED = 'callback_booked';

    /**
     * @var string
     */
    public const EVENT_CALLBACK_BOOKED_COMPLETED = 'callback_booked_completed';

    /**
     * @var string
     */
    public const EVENT_CALLBACK_UPDATE = 'callback_update';

    /**
     * @var string
     */
    public const EVENT_CALLBACK_UPDATE_COMPLETED = 'callback_update_completed';

    /**
     * @var string
     */
    public const EVENT_CALLBACK_AUTHORIZATION = 'callback_authorization';

    /**
     * @var string
     */
    public const EVENT_CAPTURE_CALLED = 'capture_called';

    /**
     * @var string
     */
    public const EVENT_CAPTURE_FAILED = 'capture_failed';

    /**
     * @var string
     */
    public const EVENT_CAPTURE_API_CALLED = 'capture_api_called';

    /**
     * @var string
     */
    public const EVENT_CANCEL_CALLED = 'cancel_called';

    /**
     * @var string
     */
    public const EVENT_CANCEL_FAILED = 'cancel_failed';

    /**
     * @var string
     */
    public const EVENT_CANCEL_API_CALLED = 'cancel_api_called';

    /**
     * @var string
     */
    public const EVENT_REFUND_CALLED = 'refund_called';

    /**
     * @var string
     */
    public const EVENT_REFUND_FAILED = 'refund_failed';

    /**
     * @var string
     */
    public const EVENT_REFUND_API_CALLED = 'refund_api_called';

    /**
     * @var string
     */
    public const EVENT_REACHED_ORDER_SUCCESS = 'reached_order_success';

    /**
     * @var string
     */
    public const EVENT_ORDER_CANCELED = 'order_canceled';

    /**
     * @var string
     */
    public const EVENT_INVOICE_CREATED = 'order_invoice_created';

    /**
     * @var string
     */
    public const EVENT_GATEWAY_REDIRECTED_TO = 'gateway_redirected_to';

    /**
     * @var string
     */
    public const EVENT_REACHED_ORDER_FAILURE = 'reached_order_failure';

    /**
     * @var string
     */
    public const EVENT_PAYMENT_BOOK_SIGNED = 'payment_book_signed';

    /**
     * @var string
     */
    public const EVENT_PAYMENT_BOOK_SIGNED_COMPLETED = 'payment_book_signed_completed';

    /**
     * @array
     */
    public const EVENT_LABELS = [
        self::EVENT_CALLBACK_BOOKED => 'Callback "Booked" received.',
        self::EVENT_CALLBACK_BOOKED_COMPLETED => 'Callback "Booked" completed.',
        self::EVENT_CALLBACK_UNFREEZE => 'Callback "Unfreeze" received.',
        self::EVENT_CALLBACK_UNFREEZE_COMPLETED => 'Callback "Unfreeze" completed.',
        self::EVENT_CALLBACK_UPDATE => 'Callback "Update" received.',
        self::EVENT_CALLBACK_UPDATE_COMPLETED => 'Callback "Update" completed.',
        self::EVENT_CALLBACK_AUTHORIZATION => 'Callback "Authorization received."',
        self::EVENT_CAPTURE_CALLED => 'Capture payment was called.',
        self::EVENT_CAPTURE_FAILED => 'Capture payment failed. Check the logs.',
        self::EVENT_CAPTURE_API_CALLED => 'Payment was debited at Resurs.',
        self::EVENT_CANCEL_CALLED => 'Cancel payment was called.',
        self::EVENT_CANCEL_FAILED => 'Cancel payment failed. Check the logs.',
        self::EVENT_CANCEL_API_CALLED => 'Payment was annulled at Resurs',
        self::EVENT_REFUND_CALLED => 'Refund payment was called.',
        self::EVENT_REFUND_FAILED => 'Refund payment failed. Check the logs.',
        self::EVENT_REFUND_API_CALLED => 'Payment was credited at Resurs.',
        self::EVENT_REACHED_ORDER_SUCCESS => 'Client reached order success page.',
        self::EVENT_ORDER_CANCELED => 'Order canceled.',
        self::EVENT_GATEWAY_REDIRECTED_TO => 'Customer redirected to gateway.',
        self::EVENT_REACHED_ORDER_FAILURE => 'Client reached order failure page.',
        self::EVENT_PAYMENT_BOOK_SIGNED => 'Book signed payment API call initiated.',
        self::EVENT_PAYMENT_BOOK_SIGNED_COMPLETED => 'Book signed payment API call completed.'
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
     * @return int|null
     */
    public function getId(): ?int;

    /**
     * Get payment ID.
     *
     * @return int|null
     */
    public function getPaymentId(): ?int;

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
     * @return string|null
     */
    public function getEvent(): ?string;

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
     * @return string|null
     */
    public function getUser(): ?string;

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
     * @return string|null
     */
    public function getExtra(): ?string;

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
     * @return string|null
     */
    public function getStateFrom(): ?string;

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
     * @return string|null
     */
    public function getStateTo(): ?string;

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
     * @return string|null
     */
    public function getStatusFrom(): ?string;

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
     * @return string|null
     */
    public function getStatusTo(): ?string;

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
     * @return string|null
     */
    public function getCreatedAt(): ?string;

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
