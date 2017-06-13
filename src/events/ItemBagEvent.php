<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\commerce\events;

use craft\commerce\elements\Order;
use Omnipay\Common\ItemBag;
use yii\base\Event;

class ItemBagEvent extends Event
{
    /**
     * @var Order The order
     */
    public $order;

    /**
     * @var ItemBag The item bag
     */
    public $items;
}
