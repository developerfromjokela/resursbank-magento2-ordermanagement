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
    public const GROUP_CALLBACKS = 'callbacks';

    /**
     * @var string
     */
    public const GROUP_AFTERSHOP = 'aftershop';

    /**
     * @var string
     */
    public const TRIGGER_KEY = 'triggeredAt';

    /**
     * @var string
     */
    public const RECEIVED_KEY = 'receivedAt';

    /**
     * @param string $scopeCode
     * @param string $scopeType
     * @return bool
     */
    public function isAfterShopEnabled(
        string $scopeCode,
        string $scopeType = ScopeInterface::SCOPE_STORES
    ): bool {
        return $this->isEnabled(
            self::GROUP_AFTERSHOP,
            'enabled',
            $scopeCode,
            $scopeType
        );
    }

    /**
     * Update time when last test callback was triggered.
     *
     * @param int $scopeId
     * @param string $scopeType
     */
    public function setCallbackTestTriggeredAt(
        int $scopeId,
        string $scopeType
    ): void {
        $this->set(
            self::GROUP_CALLBACKS,
            'test_triggered_at',
            date('Y-m-d H:i:s'),
            $scopeId,
            $scopeType
        );
    }

    /**
     * NOTE: scope code may be null (when entering the config there is no scope
     * provided, thus no code / type to collect from the request data).
     *
     * @param null|string $scopeCode
     * @param string $scopeType
     * @return string
     */
    public function getCallbackTestTriggeredAt(
        ?string $scopeCode,
        string $scopeType = ScopeInterface::SCOPE_STORES
    ): string {
        return (string) $this->get(
            self::GROUP_CALLBACKS,
            'test_triggered_at',
            $scopeCode,
            $scopeType
        );
    }

    /**
     * Update time when last test callback was received.
     *
     * @param int $scopeId
     * @param string $scopeType
     * @return void
     */
    public function setCallbackTestReceivedAt(
        int $scopeId,
        string $scopeType
    ): void {
        $this->set(
            self::GROUP_CALLBACKS,
            'test_received_at',
            date('Y-m-d H:i:s'),
            $scopeId,
            $scopeType
        );
    }

    /**
     * NOTE: scope code may be null (when entering the config there is no scope
     * provided, thus no code / type to collect from the request data).
     *
     * @param null|string $scopeCode
     * @param string $scopeType
     * @return string
     */
    public function getCallbackTestReceivedAt(
        ?string $scopeCode,
        string $scopeType = ScopeInterface::SCOPE_STORES
    ): string {
        return (string) $this->get(
            self::GROUP_CALLBACKS,
            'test_received_at',
            $scopeCode,
            $scopeType
        );
    }
}
