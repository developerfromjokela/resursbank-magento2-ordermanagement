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
}
