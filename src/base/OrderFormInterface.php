<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

/**
 * Cart Form
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2
 */
interface OrderFormInterface
{
    /**
     * @return bool
     * @todo decide on appropriate naming
     */
    public function apply(): bool;
}
