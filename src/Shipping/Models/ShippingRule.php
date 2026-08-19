<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Shipping\Models;

use craft\commerce\elements\conditions\customers\ShippingRuleCustomerCondition;
use craft\commerce\elements\conditions\orders\ShippingRuleOrderCondition;
use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Support\Facades\Conditions;
use CraftCms\Cms\Support\Json;
use CraftCms\Commerce\Formula\Formulas;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Shipping\Contracts\ShippingRuleInterface;
use CraftCms\Commerce\Shipping\Records\ShippingRuleCategory as ShippingRuleCategoryRecord;
use CraftCms\Commerce\Store\Concerns\StoreTrait;
use CraftCms\Commerce\Store\Contracts\HasStoreInterface;
use DateTime;
use function CraftCms\Cms\t;

class ShippingRule extends Component implements ShippingRuleInterface, HasStoreInterface
{
    use StoreTrait;

    public ?int $id = null;

    public ?string $name = null;

    public ?string $description = null;

    public ?int $methodId = null;

    public int $priority = 0;

    public bool $enabled = true;

    public ?string $orderConditionFormula = '';

    public float $baseRate = 0;

    public float $perItemRate = 0;

    public float $percentageRate = 0;

    public float $weightRate = 0;

    public float $minRate = 0;

    public float $maxRate = 0;

    public ?DateTime $dateCreated = null;

    public ?DateTime $dateUpdated = null;

    private ?array $_shippingRuleCategories = null;

    private ShippingRuleOrderCondition|string|array|null $_orderCondition = null;

    private ShippingRuleCustomerCondition|string|array|null $_customerCondition = null;

    #[\Override]
    public function getRules(): array
    {
        return [
            'name' => ['required', 'string'],
            'methodId' => ['required', 'integer'],
            'priority' => ['required', 'integer'],
            'enabled' => ['required', 'boolean'],
            'baseRate' => ['required', 'numeric'],
            'perItemRate' => ['required', 'numeric'],
            'weightRate' => ['required', 'numeric'],
            'percentageRate' => ['required', 'numeric'],
            'minRate' => ['required', 'numeric'],
            'maxRate' => ['required', 'numeric'],
            'orderConditionFormula' => [
                'nullable',
                'string',
                'min:1',
                'max:65000',
                function(string $attribute, mixed $value, \Closure $fail) {
                    if ($value) {
                        $order = Order::find()->one() ?? new Order();
                        $orderAsArray = app(\CraftCms\Commerce\Shipping\ShippingMethods::class)->getSerializedOrderForMatchingRules($order);
                        if (!app(Formulas::class)->validateConditionSyntax($value, ['order' => $orderAsArray])) {
                            $fail(t('Invalid order condition syntax.', category: 'commerce'));
                        }
                    }
                },
            ],
            'shippingRuleCategories' => [
                'sometimes',
                function(string $attribute, mixed $value, \Closure $fail) {
                    if (!empty($value)) {
                        foreach ($value as $key => $ruleCategory) {
                            if (!$ruleCategory->validate()) {
                                $this->addModelErrors($ruleCategory, $attribute . '.' . $key);
                            }
                        }
                    }
                },
            ],
        ];
    }

    public function getIsEnabled(): bool
    {
        return $this->enabled;
    }

    public function setOrderCondition(ShippingRuleOrderCondition|string|array|null $condition): void
    {
        if (empty($condition)) {
            $this->_orderCondition = null;
            return;
        }

        $this->_orderCondition = $condition;
    }

    public function getOrderCondition(): ShippingRuleOrderCondition
    {
        if ($this->_orderCondition instanceof ShippingRuleOrderCondition) {
            return $this->_orderCondition;
        }

        $condition = $this->_orderCondition ?? [];
        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        $condition['class'] = ShippingRuleOrderCondition::class;
        // Inject storeId so condition rules can call getCondition()->getStore() during init.
        if ($this->storeId !== null && !isset($condition['storeId'])) {
            $condition['storeId'] = $this->storeId;
        }
        $condition = Conditions::createCondition($condition);
        /** @var ShippingRuleOrderCondition $condition */
        $condition->forProjectConfig = false;
        $condition->mainTag = 'div';
        $condition->name = 'orderCondition';
        $condition->storeId = $this->storeId;

        $this->_orderCondition = $condition;

        return $this->_orderCondition;
    }

    public function setCustomerCondition(ShippingRuleCustomerCondition|string|array|null $condition): void
    {
        if (empty($condition)) {
            $this->_customerCondition = null;
            return;
        }

        $this->_customerCondition = $condition;
    }

    public function getCustomerCondition(): ShippingRuleCustomerCondition
    {
        if ($this->_customerCondition instanceof ShippingRuleCustomerCondition) {
            return $this->_customerCondition;
        }

        $condition = $this->_customerCondition ?? [];
        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        $condition['class'] = ShippingRuleCustomerCondition::class;
        $condition = Conditions::createCondition($condition);
        /** @var ShippingRuleCustomerCondition $condition */
        $condition->forProjectConfig = false;
        $condition->mainTag = 'div';
        $condition->name = 'customerCondition';

        $this->_customerCondition = $condition;

        return $this->_customerCondition;
    }

    public function matchOrder(Order $order): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $lineItems = $order->getLineItems();

        $nonShippableItems = [];
        foreach ($lineItems as $item) {
            if ($item->getIsShippable()) {
                continue;
            }

            $nonShippableItems[$item->id] = $item->id;
        }

        if (count($nonShippableItems) > 0 && count($lineItems) == count($nonShippableItems)) {
            return false;
        }

        $shippingRuleCategories = $this->getShippingRuleCategories();
        $orderShippingCategories = $this->_getUniqueCategoryIdsInOrder($order);
        [$disallowedCategories, $requiredCategories] = $this->_getRequiredAndDisallowedCategoriesFromRule($shippingRuleCategories);

        if (!empty(array_intersect($orderShippingCategories, $disallowedCategories))) {
            return false;
        }

        if (!empty(array_diff($requiredCategories, $orderShippingCategories))) {
            return false;
        }

        if (!$this->getOrderCondition()->matchElement($order)) {
            return false;
        }

        $customer = $order->getCustomer();
        if (!$customer && !empty($this->getCustomerCondition()->getConditionRules())) {
            return false;
        }

        if ($customer && !$this->getCustomerCondition()->matchElement($customer)) {
            return false;
        }

        if ($this->orderConditionFormula) {
            $orderAsArray = app(\CraftCms\Commerce\Shipping\ShippingMethods::class)->getSerializedOrderForMatchingRules($order);
            if (!app(Formulas::class)->evaluateCondition($this->orderConditionFormula, ['order' => $orderAsArray], 'Evaluate Shipping Rule Order Condition Formula')) {
                return false;
            }
        }

        return true;
    }

    public function getShippingRuleCategories(): array
    {
        if ($this->_shippingRuleCategories === null && $this->id) {
            $this->_shippingRuleCategories = app(\CraftCms\Commerce\Shipping\ShippingRuleCategories::class)->getShippingRuleCategoriesByRuleId($this->id);
        }

        return $this->_shippingRuleCategories ?? [];
    }

    public function setShippingRuleCategories(array $models): void
    {
        $this->_shippingRuleCategories = $models;
    }

    public function getOptions(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'methodId' => $this->methodId,
            'priority' => $this->priority,
            'enabled' => $this->enabled,
            'orderConditionFormula' => $this->orderConditionFormula,
            'baseRate' => $this->baseRate,
            'perItemRate' => $this->perItemRate,
            'percentageRate' => $this->percentageRate,
            'weightRate' => $this->weightRate,
            'minRate' => $this->minRate,
            'maxRate' => $this->maxRate,
            'storeId' => $this->storeId,
        ];
    }

    public function getPercentageRate(?int $shippingCategoryId = null): float
    {
        return $this->_getRate('percentageRate', $shippingCategoryId);
    }

    public function getPerItemRate(?int $shippingCategoryId = null): float
    {
        return $this->_getRate('perItemRate', $shippingCategoryId);
    }

    public function getWeightRate(?int $shippingCategoryId = null): float
    {
        return $this->_getRate('weightRate', $shippingCategoryId);
    }

    public function getBaseRate(): float
    {
        return (float)$this->baseRate;
    }

    public function getMaxRate(): float
    {
        return (float)$this->maxRate;
    }

    public function getMinRate(): float
    {
        return (float)$this->minRate;
    }

    public function getDescription(): string
    {
        return $this->description ?? '';
    }

    private function _getUniqueCategoryIdsInOrder(Order $order): array
    {
        $orderShippingCategories = [];
        foreach ($order->getLineItems() as $lineItem) {
            if (!$lineItem->getIsShippable()) {
                continue;
            }

            $orderShippingCategories[] = $lineItem->shippingCategoryId;
        }

        return array_unique($orderShippingCategories);
    }

    private function _getRequiredAndDisallowedCategoriesFromRule(array $shippingRuleCategories): array
    {
        $disallowedCategories = [];
        $requiredCategories = [];
        foreach ($shippingRuleCategories as $ruleCategory) {
            if ($ruleCategory->condition === ShippingRuleCategoryRecord::CONDITION_DISALLOW) {
                $disallowedCategories[] = $ruleCategory->shippingCategoryId;
            }

            if ($ruleCategory->condition === ShippingRuleCategoryRecord::CONDITION_REQUIRE) {
                $requiredCategories[] = $ruleCategory->shippingCategoryId;
            }
        }

        return [$disallowedCategories, $requiredCategories];
    }

    private function _getRate(string $attribute, ?int $shippingCategoryId = null): float
    {
        if (!$shippingCategoryId) {
            return (float)$this->$attribute;
        }

        foreach ($this->getShippingRuleCategories() as $ruleCategory) {
            if ($shippingCategoryId === $ruleCategory->shippingCategoryId && $ruleCategory->$attribute !== null) {
                return (float)$ruleCategory->$attribute;
            }
        }

        return (float)$this->$attribute;
    }
}
