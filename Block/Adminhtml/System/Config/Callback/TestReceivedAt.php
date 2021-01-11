<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Block\Adminhtml\System\Config\Callback;

use Exception;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Resursbank\Ordermanagement\Helper\Config as ConfigHelper;
use Resursbank\Ordermanagement\Helper\Log;

class TestReceivedAt extends Field
{
    /**
     * @var Log
     */
    private $log;

    /**
     * @var ConfigHelper
     */
    private $config;

    /**
     * @param Context $context
     * @param Log $log
     * @param ConfigHelper $config
     */
    public function __construct(
        Context $context,
        Log $log,
        ConfigHelper $config
    ) {
        $this->log = $log;
        $this->config = $config;

        parent::__construct($context);
    }

    /**
     * Returns formatted time when test callback was last received.
     *
     * @return string
     */
    public function getTime(): string
    {
        try {
            $testData = $this->config->getTestReceivedAt();

            if (!is_null($testData->{ConfigHelper::TRIGGER_KEY})) {
                $text = __(
                    'Test callback was triggered at %1 but has not yet been received. ' .
                    'Try reloading the page.',
                    date('Y-m-d H:i:s', $testData->{ConfigHelper::TRIGGER_KEY})
                );
            } else {
                $text = date('Y-m-d H:i:s', $testData->{ConfigHelper::RECEIVED_KEY});
            }

            /*$text = $time > 0 ?
                date('Y-m-d H:i:s', $time) :
                __(
                    'Test callback has not been received yet. Try triggering ' .
                    'it using the button above.'
                );*/
        } catch (Exception $e) {
            $this->log->exception($e);

            $text = __('Could not retrieve timestamp from database.');
        }

        return (string) $text;
    }

    /**
     * Get the button and scripts contents
     *
     * @param AbstractElement $element
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        return $this->getTime();
    }
}
