<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Ui\Component;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\OrderRepository;
use Magento\Ui\Component\Listing\Columns\Column;
use Resursbank\Ordermanagement\Helper\ApiPayment;
use Resursbank\Core\Helper\PaymentMethods;

class PaymentStatus extends Column
{

    public function prepareDataSource(array $dataSource)
    {
        $test = 0;
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $item['resursbank_payment_status'] = 'resurs' . $test++;
            }
        }
        return $dataSource;
    }
}
