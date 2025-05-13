<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use Craft;
use craft\commerce\base\Model as BaseModel;
use craft\commerce\elements\conditions\orders\ShippingMethodOrderCondition;
use craft\commerce\elements\Order;
use craft\commerce\errors\NotImplementedException;
use craft\helpers\Json;
use DateTime;
use Illuminate\Support\Collection;
use JsonSchema\Exception\InvalidConfigException;

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
abstract class ShippingMethod extends BaseModel implements ShippingMethodInterface, HasStoreInterface
{
    use StoreTrait;

    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string|null Name
     */
    public ?string $name = null;

    /**
     * @var string|null Handle
     */
    public ?string $handle = null;

    /**
     * @var bool Enabled
     */
    public bool $enabled = true;

    /**
     * @var ShippingMethodOrderCondition|null
     * @since 5.0.0
     */
    private ?ShippingMethodOrderCondition $_orderCondition = null;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateCreated = null;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateUpdated = null;

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
    public function getShippingRules(): Collection
    {
        return collect();
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
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [[
            'id',
            'name',
            'handle',
            'storeId',
            'orderCondition',
            'enabled',
            'dateCreated',
            'dateUpdated',
        ], 'safe'];

        return $rules;
    }

    /**
     * @param ShippingMethodOrderCondition|string|array|null $condition
     * @return void
     * @throws InvalidConfigException
     * @since 5.0.0
     */
    public function setOrderCondition(ShippingMethodOrderCondition|string|array|null $condition): void
    {
        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        if (!$condition instanceof ShippingMethodOrderCondition) {
            $condition['class'] = ShippingMethodOrderCondition::class;
            $condition = Craft::$app->getConditions()->createCondition($condition);
            /** @var ShippingMethodOrderCondition $condition */
        }
        $condition->forProjectConfig = false;

        $this->_orderCondition = $condition;
    }

    /**
     * @return ShippingMethodOrderCondition
     * @since 5.0.0
     */
    public function getOrderCondition(): ShippingMethodOrderCondition
    {
        $condition = $this->_orderCondition ?? new ShippingMethodOrderCondition();
        $condition->mainTag = 'div';
        $condition->name = 'orderCondition';
        $condition->storeId = $this->storeId;

        return $condition;
    }

    /**
     * @inheritdoc
     */
    public function matchOrder(Order $order): bool
    {
        // Match the method's order condition first to see if we need to even check the rules.
        if (!$this->getOrderCondition()->matchElement($order)) {
            return false;
        }

        /** @var ShippingRuleInterface $rule */
        foreach ($this->getShippingRules()->all() as $rule) {
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

    public function getPriceForOrder(Order $order): float
    {
        $shippingRule = $this->getMatchingShippingRule($order);
        $lineItems = $order->getLineItems();

        if (!$shippingRule) {
            return 0;
        }

        $nonShippableItems = [];

        foreach ($lineItems as $item) {
            if ($item->getIsShippable()) {
                continue;
            }

            $nonShippableItems[$item->id] = $item->id;
        }

        // Are all line items non shippable items? No shipping cost.
        if (count($lineItems) == count($nonShippableItems)) {
            return 0;
        }

        $amount = $shippingRule->getBaseRate();

        foreach ($order->getLineItems() as $item) {
            if ($item->getHasFreeShipping()) {
                continue;
            }

            if (!$item->getIsShippable()) {
                continue;
            }

            $percentageRate = $shippingRule->getPercentageRate($item->shippingCategoryId);
            $perItemRate = $shippingRule->getPerItemRate($item->shippingCategoryId);
            $weightRate = $shippingRule->getWeightRate($item->shippingCategoryId);

            $percentageAmount = $item->getSubtotal() * $percentageRate;
            $perItemAmount = $item->qty * $perItemRate;
            $weightAmount = ($item->weight * $item->qty) * $weightRate;

            $amount += ($percentageAmount + $perItemAmount + $weightAmount);
        }

        $amount = max($amount, $shippingRule->getMinRate());

        if ($shippingRule->getMaxRate()) {
            $amount = min($amount, $shippingRule->getMaxRate());
        }

        return $amount;
    }
}
