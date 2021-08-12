<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use craft\commerce\base\Model as BaseModel;
use craft\commerce\elements\Order;
use craft\commerce\errors\NotImplementedException;
use craft\commerce\Plugin;
use DateTime;

/**
 * Base ShippingMethod
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 *
 * @property-read string $cpEditUrl
 * @property-read array $shippingRules
 * @property-read bool $isEnabled
 * @property-read string $type
 */
abstract class ShippingMethod extends BaseModel implements ShippingMethodInterface
{
    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string Name
     */
    public string $name;

    /**
     * @var string Handle
     */
    public string $handle;

    /**
     * @var bool Enabled
     */
    public bool $enabled;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateCreated;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateUpdated;

    /**
     * @var bool Is this the shipping method for the lite edition.
     */
    public bool $isLite = false;


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
    public function getId(): ?int
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
    public function getMatchingShippingRule(Order $order): ?ShippingRuleInterface
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
    public function getPriceForOrder(Order $order): float
    {
        $shippingRule = $this->getMatchingShippingRule($order);
        $lineItems = $order->getLineItems();

        if (!$shippingRule) {
            return 0;
        }

        $nonShippableItems = [];

        foreach ($lineItems as $item) {
            $purchasable = $item->getPurchasable();
            if ($purchasable && !Plugin::getInstance()->getPurchasables()->isPurchasableShippable($purchasable)) {
                $nonShippableItems[$item->id] = $item->id;
            }
        }

        // Are all line items non shippable items? No shipping cost.
        if (count($lineItems) == count($nonShippableItems)) {
            return 0;
        }

        $amount = $shippingRule->getBaseRate();

        foreach ($order->lineItems as $item) {
            if ($item->getPurchasable() && !$item->purchasable->hasFreeShipping() && Plugin::getInstance()->getPurchasables()->isPurchasableShippable($item->getPurchasable())) {
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
}
