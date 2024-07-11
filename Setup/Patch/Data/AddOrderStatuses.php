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

class AddOrderStatuses implements DataPatchInterface
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
     * Constructor.
     *
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
     * Get list of dependencies.
     *
     * @inheriDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * Get list of aliases.
     *
     * @inheriDoc
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * Apply patch.
     *
     * NOTE: We are utilising insertOnDuplicate specifically to avoid collisions
     * with order statuses supplied through our deprecated module.
     *
     * @inheriDoc
     */
    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        foreach ($this->resursbankStatuses->statuses() as $status) {
            $this->moduleDataSetup->getConnection()->insertOnDuplicate(
                $this->moduleDataSetup->getTable('sales_order_status'),
                [
                    'status' => $status['status'],
                    'label' => $status['label']
                ]
            );
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }
}
