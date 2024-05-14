<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Plugin\Core\Block\Adminhtml\System\Config;

use Resursbank\Core\Block\Adminhtml\System\Config\SupportInfo as Subject;
use Resursbank\Ordermanagement\Helper\Log;
use Magento\Framework\Module\PackageInfo;
use Throwable;

/**
 * Interceptor for Core SupportInfo widget block.
 */
class SupportInfo
{
    /**
     * @param PackageInfo $packageInfo
     * @param Log $log
     */
    public function __construct(
        private readonly PackageInfo $packageInfo,
        private readonly Log $log
    ) {
    }

    /**
     * Append this module's name and version to getVersion output.
     *
     * @param Subject $subject
     * @param string $result
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetVersion(
        Subject $subject,
        string $result
    ): string {
        try {
            return $result . '<br />Resursbank_Ordermanagement: ' .
                $this->packageInfo->getVersion(
                    moduleName: 'Resursbank_Ordermanagement'
                );
        } catch (Throwable $error) {
            $this->log->exception(error: $error);
        }

        return $result;
    }
}
