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
    /** @var string */
    protected string $loggerName = 'Resursbank Callbacks';

    /** @var string */
    protected string $file = 'resursbank_ordermanagement_callbacks';
}
