<?php
/**
 * Copyright 2016 Resurs Bank AB
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Helper;

use InvalidArgumentException;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;

class Command extends AbstractHelper
{
    /**
     * Resolve payment data from commandSubject,
     *
     * @param array $commandSubject
     * @return PaymentDataObjectInterface
     * @throws InvalidArgumentException
     */
    public function getPaymentDataObject(
        array $commandSubject
    ): PaymentDataObjectInterface {
        if (!isset($commandSubject['payment']) ||
            !($commandSubject['payment'] instanceof PaymentDataObjectInterface)
        ) {
            throw new InvalidArgumentException(
                'Payment data object should be provided'
            );
        }

        return $commandSubject['payment'];
    }
}
