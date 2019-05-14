<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\elements\Product;
use yii\base\Event;

/**
 * Class CustomizeProductSnapshotFieldsEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class CustomizeProductSnapshotFieldsEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var Product The product
     */
    public $product;

    /**
     * @var array|null The fields to be captured
     */
    public $fields;
}
