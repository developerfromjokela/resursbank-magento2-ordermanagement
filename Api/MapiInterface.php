<?php

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Api;

/**
 * MAPI callback interface.
 */
interface MapiInterface
{
    /**
     * Process authorization callback.
     *
     * @return void
     */
    public function authorization(): void;

    /**
     * @return void
     */
    public function test(): void;
}
