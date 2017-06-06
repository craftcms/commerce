<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\commerce\events;

use craft\commerce\elements\Variant;
use yii\base\Event;

class PurchaseVariantEvent extends Event
{
    /**
     * @var Variant The variant model
     */
    public $variant;
}
