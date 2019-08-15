<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use Craft;
use craft\base\Model;
use craft\commerce\elements\Order;
use craft\commerce\errors\NotImplementedException;

/**
 * Base ShippingMethod
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
abstract class ShippingMethod extends Model implements ShippingMethodInterface
{
    // Properties
    // =========================================================================

    /**
     * @var int ID
     */
    public $id;

    /**
     * @var string Name
     */
    public $name;

    /**
     * @var string Handle
     */
    public $handle;

    /**
     * @var bool Enabled
     */
    public $enabled;

    /**
     * @var bool Is this the shipping method for the lite edition.
     */
    public $isLite = false;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getType(): string
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritdoc
     */
    public function getHandle(): string
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl(): string
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritdoc
     */
    public function getShippingRules(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getIsEnabled(): bool
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritdoc
     */
    public function matchOrder(Order $order): bool
    {
        /** @var ShippingRuleInterface $rule */
        foreach ($this->getShippingRules() as $rule) {
            if ($rule->matchOrder($order)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function getMatchingShippingRule(Order $order)
    {
        foreach ($this->getShippingRules() as $rule) {
            /** @var ShippingRuleInterface $rule */
            if ($rule->matchOrder($order)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * @param Order $order
     * @return float
     */
    public function getPriceForOrder(Order $order)
    {
        $shippingRule = $this->getMatchingShippingRule($order);
        $lineItems = $order->getLineItems();

        if (!$shippingRule) {
            return 0;
        }

        $nonShippableItems = [];

        foreach ($lineItems as $item) {
            $purchasable = $item->getPurchasable();
            if($purchasable && !$purchasable->getIsShippable())
            {
                $nonShippableItems[$item->id] = $item->id;
            }
        }

        // Are all line items non shippable items? No shipping cost.
        if(count($lineItems) == count($nonShippableItems))
        {
            return 0;
        }

        $amount = $shippingRule->getBaseRate();

        foreach ($order->lineItems as $item) {
            if ($item->purchasable && !$item->purchasable->hasFreeShipping() && $item->purchasable->getIsShippable()) {
                $percentageRate = $shippingRule->getPercentageRate($item->shippingCategoryId);
                $perItemRate = $shippingRule->getPerItemRate($item->shippingCategoryId);
                $weightRate = $shippingRule->getWeightRate($item->shippingCategoryId);

                $percentageAmount = $item->getSubtotal() * $percentageRate;
                $perItemAmount = $item->qty * $perItemRate;
                $weightAmount = ($item->weight * $item->qty) * $weightRate;

                $amount += ($percentageAmount + $perItemAmount + $weightAmount);
            }
        }

        $amount = max($amount, $shippingRule->getMinRate());

        if ($shippingRule->getMaxRate()) {
            $amount = min($amount, $shippingRule->getMaxRate());
        }

        return $amount;
    }

    /**
     * @deprecated in 2.0
     */
    public function getAmount()
    {
        Craft::$app->getDeprecator()->log('ShippingMethod::amount', 'ShippingMethod::amount has been deprecated. Use ShippingMethod::getPriceForOrder($order) instead.');

        return 0;
    }
}
