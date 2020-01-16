<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\models\ProductType;
use yii\base\Event;

/**
 * Product type event class.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ProductTypeEvent extends Event
{
    /**
     * @var ProductType|null The product type model associated with the event.
     */
    public $productType;

    /**
     * @var bool Whether the product type is brand new
     */
    public $isNew = false;
}
