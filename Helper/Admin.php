<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\User\Model\User;

/**
 * Generic methods to assist with admin related actions.
 *
 * This class implements ArgumentInterface (that's normally reserved for
 * ViewModels) because we found no other way of removing the suppressed warning
 * PHPMD.CookieAndSessionMisuse. The interface fools the analytic tools into
 * thinking this class is part of the presentation layer, and thus eligible to
 * handle the session.
 */
class Admin extends AbstractHelper implements ArgumentInterface
{
    /**
     * @var Session
     */
    private Session $session;

    /**
     * @param Context $context
     * @param Session $session
     */
    public function __construct(
        Context $context,
        Session $session
    ) {
        $this->session = $session;

        parent::__construct($context);
    }

    /**
     * Returns "Anonymous" if the username cannot be resolved.
     *
     * @return string
     */
    public function getUserName(): string
    {
        $result = 'Anonymous';

        if ($this->session->getUser() instanceof User) {
            $result = (string) $this->session->getUser()->getUserName();
        }

        return $result;
    }
}
