<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Conditions;

use CraftCms\Cms\Condition\BaseNumberConditionRule;
use CraftCms\Cms\Condition\Contracts\ConditionInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementQueryConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Form\Contracts\Node;
use CraftCms\Cms\Form\Controls\Money;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Commerce\Order\Queries\OrderQuery;
use CraftCms\Commerce\Payment\Currencies;
use CraftCms\Commerce\Store\Contracts\HasStoreInterface;
use Illuminate\Database\Query\Builder;
use Money\Currency;
use Override;

use function CraftCms\Cms\t;

/**
 * @property-read float|int $orderAttributeValue
 */
abstract class OrderCurrencyValuesAttributeConditionRule extends BaseNumberConditionRule implements ElementConditionRuleInterface, ElementQueryConditionRuleInterface
{
    public string $orderAttribute = '';

    public ?Currency $currency = null;

    public ?int $subUnit = null;

    #[Override]
    public function setCondition(ConditionInterface $condition): void
    {
        parent::setCondition($condition);

        if ($condition instanceof HasStoreInterface) {
            $this->currency = $condition->getStore()->getCurrency();
        } else {
            /** @phpstan-ignore-next-line method.notFound (getStore() is added to Site via a Macroable macro registered in Plugin::registerBehaviorMacros(), not visible to static analysis) */
            $this->currency = Sites::getCurrentSite()->getStore()->getCurrency();
        }

        if ($this->currency) {
            $this->subUnit = app(Currencies::class)->getSubunitFor($this->currency);
        }
    }

    public function getExclusiveQueryParams(): array
    {
        return [$this->orderAttribute];
    }

    public function getLabel(): string
    {
        return 'Label not implemented';
    }

    public function matchElement(ElementInterface $element): bool
    {
        return $this->matchValue($element->{$this->orderAttribute});
    }

    public function modifyQuery(Builder $query, ElementQueryInterface $elementQuery): void
    {
        OrderQuery::{'apply' . ucfirst($this->orderAttribute)}($query, $this->paramValue());
    }

    /** @return list<Node> */
    #[Override]
    protected function inputNodes(): array
    {
        if (in_array($this->operator, [self::OPERATOR_EMPTY, self::OPERATOR_NOT_EMPTY], true)) {
            return [];
        }

        if (in_array($this->operator, [self::OPERATOR_IN, self::OPERATOR_NOT_IN], true)) {
            return parent::inputNodes();
        }

        if ($this->operator === self::OPERATOR_BETWEEN) {
            return [
                Field::make(t('Min Value'), Money::make('value')->currency($this->currencyCode())->value($this->value)),
                Field::make(t('Max Value'), Money::make('maxValue')->currency($this->currencyCode())->value($this->maxValue))
                    ->tip(t('The values are matched inclusively.')),
            ];
        }

        return [Field::make($this->getLabel(), Money::make('value')->currency($this->currencyCode())->value($this->value))];
    }

    private function currencyCode(): string
    {
        return $this->currency?->getCode() ?? 'USD';
    }
}
