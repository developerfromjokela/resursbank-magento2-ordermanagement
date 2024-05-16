<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Plugin\Block\Adminhtml\Sales\Order\View;

use Exception;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Block\Adminhtml\Order\View\Info;
use Resursbank\Ordermanagement\Block\Adminhtml\Sales\Order\View\Info\PaymentInformation;
use Resursbank\Ordermanagement\Helper\ApiPayment;
use Resursbank\Ordermanagement\Helper\Log;

/**
 * Prepends payment information from Resurs Bank to the top of the order /
 * invoice view.
 *
 * See: Block\Adminhtml\Sales\Order\View\Info\PaymentInformation
 */
class AppendPaymentInfo
{
    /**
     * @param Log $log
     * @param ApiPayment $apiPayment
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        private readonly Log $log,
        private readonly ApiPayment $apiPayment,
        private readonly ObjectManagerInterface $objectManager
    ) {
    }

    /**
     * Prepend HTML to order / invoice view.
     *
     * @param Info $subject
     * @param string $result
     * @return string
     * @throws Exception
     */
    public function afterToHtml(
        Info $subject,
        string $result
    ): string {
        try {
            if ($this->apiPayment->validateOrder($subject->getOrder())) {
                $block = $this->objectManager->create(PaymentInformation::class);
                $result = $block->toHtml() . $result;
            }
        } catch (Exception $e) {
            $this->log->error($e->getMessage());
        }

        return $result;
    }
}
