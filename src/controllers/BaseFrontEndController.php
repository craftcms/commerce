<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use craft\commerce\elements\Order;
use craft\commerce\Plugin;

/**
 * Class BaseFrontEndController
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class BaseFrontEndController extends BaseController
{
    // Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected $allowAnonymous = true;

    // Protected Methods
    // =========================================================================

    /**
     * @param Order $cart
     * @return array
     */
    protected function cartArray(Order $cart): array
    {
        return Plugin::getInstance()->getOrders()->cartArray($cart);
    }
}
