<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
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
        ?string $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): bool {
        return $this->isEnabled(
            self::GROUP,
            'aftershop',
            $scopeCode,
            $scopeType
        );
    }

    /**
     * @param string|null $scopeCode
     * @param string $scopeType
     * @return bool
     */
    public function isDebugEnabled(
        ?string $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): bool {
        return $this->isEnabled(
            self::GROUP,
            'debug',
            $scopeCode,
            $scopeType
        );
    }

    /**
     * @param string|null $scopeCode
     * @param string $scopeType
     * @return null|object
     */
    public function getTestReceivedAt(
        ?string $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): ?object {
        $result = $this->get(
            self::GROUP,
            'callback_test_received_at',
            $scopeCode,
            $scopeType
        );

        return $result ? json_decode($result) : null;
    }

    /**
     * @param int $value
     * @param int $scopeId
     * @return void
     */
    public function setTestTriggered(int $value, int $scopeId = 0): void
    {
        $this->set(
            self::GROUP,
            'callback_test_received_at',
            json_encode(
                [self::TRIGGER_KEY => $value, self::RECEIVED_KEY => null]
            ),
            $scopeId,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );
    }

    /**
     * @param int $value
     * @param int $scopeId
     * @return void
     */
    public function setTestReceived(int $value, int $scopeId = 0): void
    {
        $this->set(
            self::GROUP,
            'callback_test_received_at',
            json_encode(
                [self::TRIGGER_KEY => null, self::RECEIVED_KEY => $value]
            ),
            $scopeId,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );
    }
}
