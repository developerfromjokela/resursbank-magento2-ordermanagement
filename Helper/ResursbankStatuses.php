<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Helper;

class ResursbankStatuses
{
    /**
     * @var string
     */
    public const PAYMENT_REVIEW = 'resursbank_frozen';

    /**
     * @var string
     */
    public const PAYMENT_REVIEW_LABEL = 'Resurs Bank - Payment Review';

    /**
     * @var string
     */
    public const CONFIRMED = 'resursbank_confirmed';

    /**
     * @var string
     */
    public const CONFIRMED_LABEL = 'Resurs Bank - Confirmed';

    /**
     * @var string
     */
    public const FINALIZED = 'resursbank_finalized';

    /**
     * @var string
     */
    public const FINALIZED_LABEL = 'Resurs Bank - Payment Finalized';

    /**
     * @var string
     */
    public const CANCELLED = 'resursbank_purchase_canceled';

    /**
     * @var string
     */
    public const CANCELLED_LABEL = 'Resurs Bank - Purchase Cancelled';

    /**
     * Get the statuses with their label.
     *
     * @return array
     */
    public function statuses(): array
    {
        return [
            [
                'status' => self::PAYMENT_REVIEW,
                'label' => self::PAYMENT_REVIEW_LABEL
            ],
            [
                'status' => self::CONFIRMED,
                'label' => self::CONFIRMED_LABEL
            ],
            [
                'status' => self::FINALIZED,
                'label' => self::FINALIZED_LABEL
            ],
            [
                'status' => self::CANCELLED,
                'label' => self::CANCELLED_LABEL
            ]
        ];
    }
}
