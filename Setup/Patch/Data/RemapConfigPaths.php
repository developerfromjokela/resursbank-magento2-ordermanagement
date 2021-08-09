<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Setup\Patch\Data;

use Resursbank\Core\Setup\Patch\Data\RemapConfigPaths as Core;

/**
 * @inheridoc
 */
class RemapConfigPaths extends Core
{
    /**
     * @inheridoc
     * @noinspection PhpMissingParentCallCommonInspection
     */
    protected function getKeys(): array
    {
        return [
            'ecom/enabled' => 'aftershop/enabled'
        ];
    }
}
