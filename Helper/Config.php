<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Helper;

use Magento\Store\Model\ScopeInterface;
use Resursbank\Core\Helper\AbstractConfig;

class Config extends AbstractConfig
{
    /**
     * @var string
     */
    public const GROUP = 'ordermanagement';

    /**
     * @var string
     */
    public const TRIGGER_KEY = 'triggeredAt';

    /**
     * @var string
     */
    public const RECEIVED_KEY = 'receivedAt';

    /**
     * @param string|null $scopeCode
     * @param string $scopeType
     * @return bool
     */
    public function isAfterShopEnabled(
        ?string $scopeCode,
        string $scopeType = ScopeInterface::SCOPE_STORES
    ): bool {
        return $this->isEnabled(
            self::GROUP,
            'aftershop',
            $scopeCode,
            $scopeType
        );
    }

    /**
     * Update time when last test callback was triggered.
     *
     * @param int|null $scopeId
     * @param string $scopeType
     */
    public function setCallbackTestTriggeredAt(
        int $scopeId,
        string $scopeType
    ): void {
        $this->set(
            self::GROUP,
            'callback_test_triggered_at',
            date('Y-m-d H:i:s'),
            $scopeId,
            $scopeType
        );
    }

    /**
     * @param string|null $scopeCode
     * @param string $scopeType
     * @return string
     */
    public function getCallbackTestTriggeredAt(
        ?string $scopeCode,
        string $scopeType = ScopeInterface::SCOPE_STORES
    ): string {
        return (string) $this->get(
            self::GROUP,
            'callback_test_triggered_at',
            $scopeCode,
            $scopeType
        );
    }

    /**
     * Update time when last test callback was received.
     *
     * @param int|null $scopeId
     * @param string $scopeType
     * @return void
     */
    public function setCallbackTestReceivedAt(
        int $scopeId,
        string $scopeType
    ): void {
        $this->set(
            self::GROUP,
            'callback_test_received_at',
            date('Y-m-d H:i:s'),
            $scopeId,
            $scopeType
        );
    }

    /**
     * @param string|null $scopeCode
     * @param string $scopeType
     * @return string
     */
    public function getCallbackTestReceivedAt(
        ?string $scopeCode,
        string $scopeType = ScopeInterface::SCOPE_STORES
    ): string {
        return (string) $this->get(
            self::GROUP,
            'callback_test_received_at',
            $scopeCode,
            $scopeType
        );
    }
}
