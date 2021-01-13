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
use Magento\Framework\App\Cache\TypeListInterface;
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
     * @var TypeListInterface
     */
    private $cacheTypeList;

    /**
     * @param Context $context
     * @param CallbackHelper $callbackHelper
     * @param Config $config
     * @param Log $log
     * @param StoreHelper $storeHelper
     * @param TypeListInterface $cacheTypeList
     */
    public function __construct(
        Context $context,
        CallbackHelper $callbackHelper,
        Config $config,
        Log $log,
        StoreHelper $storeHelper,
        TypeListInterface $cacheTypeList
    ) {
        $this->callbackHelper = $callbackHelper;
        $this->config = $config;
        $this->log = $log;
        $this->storeHelper = $storeHelper;
        $this->cacheTypeList = $cacheTypeList;

        parent::__construct($context);
    }

    /**
     * Register callback URLs.
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

            $this->config->setTestTriggered(time());

            $this->cacheTypeList->cleanType('config');

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

        $this->_redirect($this->_redirect->getRefererUrl());
    }
}
