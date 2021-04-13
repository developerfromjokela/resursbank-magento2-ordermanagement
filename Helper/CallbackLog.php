<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Helper;

use Resursbank\Core\Helper\AbstractLog;

class CallbackLog extends AbstractLog
{
    /**
     * @inheritDoc
     */
    protected $loggerName = 'Resursbank Callbacks';

    /**
     * @inheritDoc
     */
    protected $file = 'resursbank_ordermanagement_callbacks';
}
