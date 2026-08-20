<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Conditions;

use CraftCms\Cms\Condition\BaseNumberConditionRule;
use CraftCms\Cms\Condition\Contracts\ConditionInterface;
use CraftCms\Cms\Cp\FormFields;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Money as MoneyHelper;
use CraftCms\Commerce\Payment\Currencies;
use CraftCms\Commerce\Store\Contracts\HasStoreInterface;
use Money\Currency;
use Override;

use function CraftCms\Cms\t;

/**
 * @property-read float|int $orderAttributeValue
 */
abstract class OrderCurrencyValuesAttributeConditionRule extends BaseNumberConditionRule implements ElementConditionRuleInterface
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

    public function modifyQuery(ElementQueryInterface $query): void
    {
        $query->{$this->orderAttribute}($this->paramValue());
    }

    #[Override]
    protected function inputHtml(): string
    {
        // don't show the value input if the condition checks for empty/notempty
        if ($this->operator === self::OPERATOR_EMPTY || $this->operator === self::OPERATOR_NOT_EMPTY) {
            return '';
        }

        if ($this->operator === self::OPERATOR_BETWEEN) {
            $maxValue = is_numeric($this->maxValue) ? MoneyHelper::toNumber(MoneyHelper::toMoney(['value' => $this->maxValue, 'currency' => $this->currencyCode()])) : $this->maxValue;

            return Html::tag('div',
                Html::hiddenLabel(t('Min Value'), 'min') .
                FormFields::moneyInputHtml($this->inputOptions()) .
                Html::tag('span', t('and')) .
                Html::hiddenLabel(t('Max Value'), 'max') .
                FormFields::moneyInputHtml(array_merge(
                    $this->inputOptions(),
                    ['id' => 'maxValue', 'name' => 'maxValue', 'value' => $maxValue]
                )) .
                Html::tag('craft-info-icon', t('The values are matched inclusively.')),
                ['class' => 'flex flex-center']
            );
        }

        return FormFields::moneyInputHtml($this->inputOptions());
    }

    /** @return array<string, mixed> */
    #[Override]
    protected function inputOptions(): array
    {
        $value = is_numeric($this->value) ? MoneyHelper::toNumber(MoneyHelper::toMoney(['value' => $this->value, 'currency' => $this->currencyCode()])) : $this->value;

        return [
            'type' => 'text',
            'id' => 'value',
            'name' => 'value',
            'value' => $value,
            'autocomplete' => false,
            'currency' => $this->currencyCode(),
            'currencyLabel' => $this->currencyLabel(),
            'showCurrency' => true,
            'decimals' => $this->subUnit ?? 2,
            'showClear' => false,
        ];
    }

    private function currencyCode(): string
    {
        return $this->currency?->getCode() ?? 'USD';
    }

    private function currencyLabel(): string
    {
        return t('({currencyCode}) {currencySymbol}', [
            'currencyCode' => $this->currencyCode(),
            'currencySymbol' => I18N::getFormattingLocale()->getCurrencySymbol($this->currencyCode()),
        ]);
    }
}
