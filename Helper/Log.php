<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Helper;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\Context;
use Resursbank\Core\Helper\AbstractLog;

/**
 * @package Resursbank\Ordermanagement\Helper
 */
class Log extends AbstractLog
{
    /**
     * @inheritDoc
     */
    protected $loggerName = 'Resursbank Ordermanagement Log';

    /**
     * @inheritDoc
     */
    protected $file = 'resursbank_ordermanagement';

    /**
     * @var Config
     */
    private $config;

    /**
     * @inheritDoc
     */
    public function __construct(
        DirectoryList $directories,
        Context $context,
        Config $config
    ) {
        $this->config = $config;

        parent::__construct($directories, $context);
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config->isDebugEnabled();
    }
}
