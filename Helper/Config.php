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
use function is_numeric;

class Config extends AbstractConfig
{
    /**
     * @var string
     */
    public const GROUP = 'ordermanagement';

    /**
     * @param string|null $scopeCode
     * @param string $scopeType
     * @return bool
     */
    public function isAftershopEnabled(
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
     * @return int
     */
    public function getTestReceivedAt(
        ?string $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): int {
        $result = $this->get(
            self::GROUP,
            'callback_test_received_at',
            $scopeCode,
            $scopeType
        );

        return is_numeric($result) ? (int) $result : 0;
    }

    /**
     * @param int $value
     * @param int $scopeId
     * @return mixed
     */
    public function setTestTriggeredAt(int $value, int $scopeId = 0): void
    {
        $this->set(
            self::GROUP,
            'callback_test_received_at',
            json_encode(['triggeredAt' => $value, 'receivedAt' => null]),
            $scopeId,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );
    }

    /**
     * @param int $value
     * @param int $scopeId
     * @return mixed
     */
    public function setTestReceivedAt(
        int $value,
        int $scopeId = 0
    ): void {
        $this->set(
            self::GROUP,
            'callback_test_received_at',
            (string) $value,
            $scopeId,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );
    }
}
