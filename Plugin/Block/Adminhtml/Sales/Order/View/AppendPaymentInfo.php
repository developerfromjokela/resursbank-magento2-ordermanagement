<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Plugin\Block\Adminhtml\Sales\Order\View;

use Exception;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Sales\Block\Adminhtml\Order\View\Info;
use Resursbank\Ordermanagement\Block\Adminhtml\Sales\Order\View\Info\EcomWidget;
use Resursbank\Ordermanagement\Helper\ApiPayment;
use Resursbank\Ordermanagement\Helper\Log;
use Resursbank\Ordermanagement\ViewModel\Adminhtml\Sales\Order\View\Info\PaymentInformation;

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
     */
    public function __construct(
        private readonly Log $log,
        private readonly ApiPayment $apiPayment,
        private readonly ObjectManagerInterface $objectManager,
        private readonly LayoutInterface $layout
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
                //$block = $this->layout->getBlock('resursbank.payment.information');
                $block = $this->objectManager->create(\Resursbank\Ordermanagement\Block\Adminhtml\Sales\Order\View\Info\PaymentInformation::class);
/*
                $block = $this->objectManager->create(
                    EcomWidget::class, [
                        'viewModel' => $this->objectManager->create(
                            type: PaymentInformation::class
                        ),
                        'templateDir' => 'payment-information'
                    ]
                );*/

                $result = $block->toHtml() . $result;
            }
        } catch (Exception $e) {
            $this->log->error($e->getMessage());
        }

        return $result;
    }
}
