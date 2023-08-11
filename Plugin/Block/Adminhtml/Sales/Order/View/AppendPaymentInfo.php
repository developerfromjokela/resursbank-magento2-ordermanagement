<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Plugin\Block\Adminhtml\Sales\Order\View;

use Exception;
use Magento\Sales\Block\Adminhtml\Order\View\Info;
use Resursbank\Ordermanagement\Block\Adminhtml\Sales\Order\View\Info\PaymentInformation as PaymentBlock;
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
     * @var PaymentBlock
     */
    private PaymentBlock $paymentBlock;

    /**
     * @var Log
     */
    private Log $log;

    /**
     * Payment API helper.
     *
     * @var ApiPayment
     */
    private ApiPayment $apiPayment;

    /**
     * @param PaymentBlock $paymentBlock
     * @param Log $log
     * @param ApiPayment $apiPayment
     */
    public function __construct(
        PaymentBlock $paymentBlock,
        Log $log,
        ApiPayment $apiPayment
    ) {
        $this->paymentBlock = $paymentBlock;
        $this->log = $log;
        $this->apiPayment = $apiPayment;
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
                $result = $this->paymentBlock
                        ->setNameInLayout('resursbank_payment_info')
                        ->toHtml() . $result;
            }
        } catch (Exception $e) {
            $this->log->error($e->getMessage());
        }

        return $result;
    }
}
