<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Conditions;

use CraftCms\Cms\Condition\BaseMultiSelectConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Queries\OrderQuery;
use CraftCms\Commerce\Payment\Gateway\Contracts\GatewayInterface;
use CraftCms\Commerce\Payment\Gateway\Gateways;
use Deprecated;
use Override;

use function CraftCms\Cms\t;

class PaymentGatewayConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    /**
     * @var string|null Legacy single value property for backwards compatibility
     *
     * @deprecated Use getValues() instead
     */
    public $value;

    public function getLabel(): string
    {
        return t('Payment Gateway', category: 'commerce');
    }

    #[Override]
    public function getConfig(): array
    {
        $config = parent::getConfig();

        // For backwards compatibility: if there's a legacy 'value' property, convert it to 'values'
        if (isset($config['value']) && !isset($config['values'])) {
            $config['values'] = [$config['value']];
            unset($config['value']);
        }

        return $config;
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();

        // For backwards compatibility: accept 'value' property and convert to 'values'
        $rules['value'] = ['nullable'];

        return $rules;
    }

    /** @param array<string, mixed> $values */
    #[Override]
    public function setAttributes($values): void
    {
        // For backwards compatibility: convert single 'value' to 'values' array
        if (isset($values['value']) && !isset($values['values'])) {
            $values['values'] = is_array($values['value']) ? $values['value'] : [$values['value']];
            unset($values['value']);
        }

        parent::setAttributes($values);
    }

    /**
     * Returns the single value for backwards compatibility
     */
    #[Deprecated(message: 'Use getValues() instead')]
    public function getValue(): ?string
    {
        $values = $this->getValues();

        return !empty($values) ? reset($values) : null;
    }

    /**
     * Sets a single value for backwards compatibility
     */
    #[Deprecated(message: 'Use setValues() instead')]
    public function setValue(?string $value): void
    {
        $this->setValues($value ? [$value] : []);
    }

    public function getExclusiveQueryParams(): array
    {
        return ['gatewayId'];
    }

    protected function options(): array
    {
        return app(Gateways::class)->getAllGateways()->mapWithKeys(fn(GatewayInterface $gateway) => [$gateway->uid => $gateway->name])->all();
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        $gateways = app(Gateways::class)->getAllGateways();

        /** @var OrderQuery $query */
        $query->gatewayId($this->paramValue(fn($uid) => $gateways->firstWhere('uid', $uid)?->id));
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Order $element */
        /** @phpstan-ignore-next-line property.notFound, nullsafe.neverNull ($uid is declared on legacy craft\commerce\base\Gateway via SavableComponent, which implements GatewayInterface via the class_alias chain, which PHPStan can't trace) */
        $gatewayUid = $element->getGateway()?->uid ?? '';

        return $this->matchValue($gatewayUid);
    }
}
