<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Block\Adminhtml\Sales\Order\View\Info;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Resursbank\Ordermanagement\ViewModel\Adminhtml\Sales\Order\View\Info\PaymentInformation as ViewModel;

/**
 * Injects custom HTML containing payment information on order/invoice view.
 *
 * The normal way would be to inject a block through an XML file, but in this
 * case it's proven difficult. It seems we would need to overwrite a core
 * PHTML template to make it work, so we settled using a block + plugin approach
 * so that we wouldn't cause issues with third party extensions.
 *
 * See: Plugin\Block\Adminhtml\Sales\Order\View\AppendPaymentInfo
 */
class PaymentInformation extends Template
{
    /**
     * @param Context $context
     * @param ViewModel $viewModel
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        ViewModel $viewModel,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->setTemplate(
            'Resursbank_Ordermanagement' .
            '::sales/order/view/info/payment_information.phtml'
        );

        $this->setData('view_model', $viewModel);
        $this->assign('view_model', $this->getData('view_model'));
    }
}
