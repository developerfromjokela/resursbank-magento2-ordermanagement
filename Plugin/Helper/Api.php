<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Plugin\Helper;

use Resursbank\Core\Helper\Api as Subject;
use Resursbank\Core\Helper\Version;

/**
 * Appends version assigned in module composer.json to API call user agent.
 */
class Api
{
    /**
     * @var Version
     */
    private Version $version;

    /**
     * @param Version $version
     */
    public function __construct(
        Version $version
    ) {
        $this->version = $version;
    }

    /**
     * Intercept call to getUserAgent.
     *
     * @param Subject $subject
     * @param string $result
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @noinspection PhpUnusedParameterInspection
     */
    public function afterGetUserAgent(
        Subject $subject,
        string $result
    ): string {
        return $result . sprintf(
            ' | Resursbank_Ordermanagement %s',
            $this->version->getComposerVersion('Resursbank_Ordermanagement')
        );
    }
}
