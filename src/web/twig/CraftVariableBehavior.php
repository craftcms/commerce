<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\web\twig;

use Craft;
use craft\commerce\elements\db\OrderQuery;
use craft\commerce\elements\db\ProductQuery;
use craft\commerce\elements\db\SubscriptionQuery;
use craft\commerce\elements\db\VariantQuery;
use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Subscription;
use craft\commerce\elements\Variant;
use craft\commerce\Plugin;
use yii\base\Behavior;

/**
 * Class CraftVariableBehavior
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class CraftVariableBehavior extends Behavior
{
    /**
     * @var Plugin
     */
    public $commerce;

    public function init()
    {
        parent::init();

        // Point `craft.commerce` to the craft\commerce\Plugin instance
        $this->commerce = Plugin::getInstance();
    }

    /**
     * Returns a new OrderQuery instance.
     *
     * @param mixed $criteria
     * @return OrderQuery
     */
    public function orders($criteria = null): OrderQuery
    {
        $query = Order::find();
        if ($criteria) {
            Craft::configure($query, $criteria);
        }
        return $query;
    }

    /**
     * Returns a new SubscriptionQuery instance.
     *
     * @param mixed $criteria
     * @return SubscriptionQuery
     */
    public function subscriptions($criteria = null): SubscriptionQuery
    {
        $query = Subscription::find();
        if ($criteria) {
            Craft::configure($query, $criteria);
        }
        return $query;
    }

    /**
     * Returns a new ProductQuery instance.
     *
     * @param mixed $criteria
     * @return ProductQuery
     */
    public function products($criteria = null): ProductQuery
    {
        $query = Product::find();
        if ($criteria) {
            Craft::configure($query, $criteria);
        }
        return $query;
    }

    /**
     * Returns a new VariantQuery instance.
     *
     * @param mixed $criteria
     * @return VariantQuery
     */
    public function variants($criteria = null): VariantQuery
    {
        $query = Variant::find();
        if ($criteria) {
            Craft::configure($query, $criteria);
        }
        return $query;
    }
}
