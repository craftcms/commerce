<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\elements\Variant;
use yii\base\Event;

/**
 * Class PurchaseVariantEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class PurchaseVariantEvent extends Event
{
    /**
     * @var Variant The variant model
     */
    public $variant;
}
