<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Resursbank\Ordermanagement\Helper\ResursbankStatuses;

/**
 * This patch adds state mapping for our custom order statuses and makes our
 * orders bearing our custom statuses visible on frontend.
 */
class AddOrderStatusesStates implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private ModuleDataSetupInterface $moduleDataSetup;

    /**
     * @var ResursbankStatuses
     */
    private ResursbankStatuses $resursbankStatuses;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param ResursbankStatuses $resursbankStatuses
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        ResursbankStatuses $resursbankStatuses
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->resursbankStatuses = $resursbankStatuses;
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $table = $this->moduleDataSetup->getTable(
            'sales_order_status_state'
        );

        foreach ($this->resursbankStatuses->statuses() as $status) {
            $this->moduleDataSetup->getConnection()->insertOnDuplicate(
                $table,
                [
                    'status' => $status['status'],
                    'state' => $status['state'],
                    'is_default' => 0,
                    'visible_on_front' => 1
                ]
            );
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }
}
