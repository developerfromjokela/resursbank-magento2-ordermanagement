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

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\User\Model\User;

/**
 * Generic methods to assist with admin related actions.
 */
class Admin extends AbstractHelper
{
    /**
     * @var Session
     */
    private $session;

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
