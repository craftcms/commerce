<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Shipping\Models;

use craft\commerce\elements\conditions\customers\ShippingMethodCustomerCondition;
use craft\commerce\elements\conditions\orders\ShippingMethodOrderCondition;
use craft\commerce\elements\Order;
use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Support\Facades\Conditions;
use CraftCms\Cms\Support\Json;
use CraftCms\Commerce\Shipping\Contracts\ShippingMethodInterface;
use CraftCms\Commerce\Shipping\Contracts\ShippingRuleInterface;
use CraftCms\Commerce\Store\Concerns\StoreTrait;
use CraftCms\Commerce\Store\Contracts\HasStoreInterface;
use DateTime;
use Illuminate\Support\Collection;

abstract class BaseShippingMethod extends Component implements ShippingMethodInterface, HasStoreInterface
{
    use StoreTrait;

    public ?int $id = null;

    public ?string $name = null;

    public ?string $handle = null;

    public ?string $icon = null;

    public ?string $color = null;

    public bool $enabled = true;

    public ?DateTime $dateCreated = null;

    public ?DateTime $dateUpdated = null;

    private ?ShippingMethodOrderCondition $_orderCondition = null;

    private ?ShippingMethodCustomerCondition $_customerCondition = null;

    public function getType(): string
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function getId(): ?int
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function getName(): string
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function getHandle(): string
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function getCpEditUrl(): string
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function getShippingRules(): Collection
    {
        return collect();
    }

    public function getIsEnabled(): bool
    {
        throw new \BadMethodCallException('Not implemented.');
    }

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
            $condition = Conditions::createCondition($condition);
        }
        $condition->forProjectConfig = false;

        /** @var ShippingMethodOrderCondition $condition */
        $this->_orderCondition = $condition;
    }

    public function getOrderCondition(): ShippingMethodOrderCondition
    {
        $condition = $this->_orderCondition ?? new ShippingMethodOrderCondition();
        /** @phpstan-ignore-next-line */
        $condition->mainTag = 'div';
        /** @phpstan-ignore-next-line */
        $condition->name = 'orderCondition';
        $condition->storeId = $this->storeId;

        return $condition;
    }

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
            $condition = Conditions::createCondition($condition);
        }
        $condition->forProjectConfig = false;

        /** @var ShippingMethodCustomerCondition $condition */
        $this->_customerCondition = $condition;
    }

    public function getCustomerCondition(): ShippingMethodCustomerCondition
    {
        $condition = $this->_customerCondition ?? new ShippingMethodCustomerCondition();
        /** @phpstan-ignore-next-line */
        $condition->mainTag = 'div';
        /** @phpstan-ignore-next-line */
        $condition->name = 'customerCondition';

        return $condition;
    }

    public function matchOrder(Order $order): bool
    {
        /** @phpstan-ignore-next-line */
        if (!$this->getOrderCondition()->matchElement($order)) {
            return false;
        }

        $customer = $order->getCustomer();
        /** @phpstan-ignore-next-line */
        if (!$customer && !empty($this->getCustomerCondition()->getConditionRules())) {
            return false;
        }

        /** @phpstan-ignore-next-line */
        if ($customer && !$this->getCustomerCondition()->matchElement($customer)) {
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
