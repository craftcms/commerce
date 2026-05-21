<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use Craft;
use craft\commerce\base\Model as BaseModel;
use craft\commerce\elements\conditions\customers\ShippingMethodCustomerCondition;
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
 * @property ShippingMethodOrderCondition $orderCondition
 * @property ShippingMethodCustomerCondition $customerCondition
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
     * @var string|null Icon
     */
    public ?string $icon = null;

    /**
     * @var string|null Color
     */
    public ?string $color = null;

    /**
     * @var bool Enabled
     */
    public bool $enabled = true;

    /**
     * @var ShippingMethodOrderCondition|null
     * @see getOrderCondition()
     * @see setOrderCondition()
     * @since 5.0.0
     */
    private ?ShippingMethodOrderCondition $_orderCondition = null;

    /**
     * @var ShippingMethodCustomerCondition|null
     * @see getCustomerCondition()
     * @see setCustomerCondition()
     * @since 5.4.0
     */
    private ?ShippingMethodCustomerCondition $_customerCondition = null;

    /**
     * @var array<string, ShippingRuleInterface|false> Per-request cache of matching rule per order number.
     */
    private array $_matchingRuleByOrderNumber = [];

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
            'icon',
            'color',
            'storeId',
            'orderCondition',
            'customerCondition',
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
        if (empty($condition)) {
            $this->_orderCondition = null;
            return;
        }

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
     * @param ShippingMethodCustomerCondition|string|array|null $condition
     * @return void
     * @throws InvalidConfigException
     * @since 5.4.0
     */
    public function setCustomerCondition(ShippingMethodCustomerCondition|string|array|null $condition): void
    {
        if (empty($condition)) {
            $this->_customerCondition = null;
            return;
        }

        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        if (!$condition instanceof ShippingMethodCustomerCondition) {
            $condition['class'] = ShippingMethodCustomerCondition::class;
            $condition = Craft::$app->getConditions()->createCondition($condition);
            /** @var ShippingMethodCustomerCondition $condition */
        }
        $condition->forProjectConfig = false;

        $this->_customerCondition = $condition;
    }

    /**
     * @return ShippingMethodCustomerCondition
     * @since 5.0.0
     */
    public function getCustomerCondition(): ShippingMethodCustomerCondition
    {
        $condition = $this->_customerCondition ?? new ShippingMethodCustomerCondition();
        $condition->mainTag = 'div';
        $condition->name = 'customerCondition';

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

        $customer = $order->getCustomer();
        // If there is no customer on the order and there are customer conditions, we can't match.
        if (!$customer && !empty($this->getCustomerCondition()->getConditionRules())) {
            return false;
        }

        // Match the method's customer condition.
        if ($customer && !$this->getCustomerCondition()->matchElement($customer)) {
            return false;
        }

        return $this->getMatchingShippingRule($order) !== null;
    }

    /**
     * @inheritdoc
     */
    public function getMatchingShippingRule(Order $order): ?ShippingRuleInterface
    {
        if (array_key_exists($order->number, $this->_matchingRuleByOrderNumber)) {
            return $this->_matchingRuleByOrderNumber[$order->number] ?: null;
        }

        foreach ($this->getShippingRules() as $rule) {
            /** @var ShippingRuleInterface $rule */
            if ($rule->matchOrder($order)) {
                $this->_matchingRuleByOrderNumber[$order->number] = $rule;
                return $rule;
            }
        }

        $this->_matchingRuleByOrderNumber[$order->number] = false;
        return null;
    }

    /**
     * Clears the per-request matching rule cache. Called by ShippingMethods service after each match pass.
     */
    public function clearMatchingRuleCache(): void
    {
        $this->_matchingRuleByOrderNumber = [];
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
