<?php

namespace craft\commerce\base;

/**
 * Message Interface
 * This interface class defines the standard functions that any Commerce message needs to provide.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
interface MessageInterface
{
    // Public Methods
    // =========================================================================

    /**
     * Get the raw data array for this message.
     *
     * @return mixed
     */
    public function getData();
}
