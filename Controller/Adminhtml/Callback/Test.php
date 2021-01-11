<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Controller\Adminhtml\Callback;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Resursbank\Core\Helper\Store as StoreHelper;
use Resursbank\Core\Helper\Url;
use Resursbank\Ordermanagement\Helper\Callback as CallbackHelper;
use Resursbank\Ordermanagement\Helper\Config;
use Resursbank\Ordermanagement\Helper\Log;

class Test extends Action
{
    /**
     * @var CallbackHelper
     */
    private $callbackHelper;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Log
     */
    private $log;

    /**
     * @var StoreHelper
     */
    private $storeHelper;

    /**
     * @var Url
     */
    private $urlHelper;

    /**
     * Registration constructor.
     *
     * @param Context $context
     * @param CallbackHelper $callbackHelper
     * @param Config $config
     * @param Log $log
     * @param StoreHelper $storeHelper
     * @param Url $urlHelper
     */
    public function __construct(
        Context $context,
        CallbackHelper $callbackHelper,
        Config $config,
        Log $log,
        StoreHelper $storeHelper,
        Url $urlHelper
    ) {
        $this->callbackHelper = $callbackHelper;
        $this->config = $config;
        $this->log = $log;
        $this->storeHelper = $storeHelper;
        $this->urlHelper = $urlHelper;

        parent::__construct($context);
    }

    /**
     * Register callback URLs
     *
     * @return void
     */
    public function execute(): void
    {
        try {
            // Trigger the test-callback.
            $this->callbackHelper->test(
                $this->storeHelper->fromRequest()
            );

            $this->config->setTestTriggeredAt(time());

            // Add success message.
            $this->getMessageManager()->addSuccessMessage(
                __('Test callback was sent')
            );
        } catch (Exception $e) {
            // Log error.
            $this->log->exception($e);

            // Add error message.
            $this->getMessageManager()->addErrorMessage(
                __('Test callback could not be triggered')
            );
        }

        // Redirect back to the config section.
        $this->_redirect($this->urlHelper->getAdminUrl(
            'admin/system_config/edit/section/payment'
        ));
    }
}
