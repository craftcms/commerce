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
    public Plugin $commerce;

    public function init(): void
    {
        parent::init();

        // Point `craft.commerce` to the craft\commerce\Plugin instance
        $this->commerce = Plugin::getInstance();
    }

    /**
     * Returns a new OrderQuery instance.
     *
     * @param array $criteria
     * @return OrderQuery
     */
    public function orders(array $criteria = []): OrderQuery
    {
        $query = Order::find();
        Craft::configure($query, $criteria);
        return $query;
    }

    /**
     * Returns a new SubscriptionQuery instance.
     *
     * @param array $criteria
     * @return SubscriptionQuery
     */
    public function subscriptions(array $criteria = []): SubscriptionQuery
    {
        $query = Subscription::find();
        Craft::configure($query, $criteria);
        return $query;
    }

    /**
     * Returns a new ProductQuery instance.
     *
     * @param array $criteria
     * @return ProductQuery
     */
    public function products(array $criteria = []): ProductQuery
    {
        $query = Product::find();
        Craft::configure($query, $criteria);
        return $query;
    }

    /**
     * Returns a new VariantQuery instance.
     *
     * @param array $criteria
     * @return VariantQuery
     */
    public function variants(array $criteria = []): VariantQuery
    {
        $query = Variant::find();
        Craft::configure($query, $criteria);
        return $query;
    }
}
