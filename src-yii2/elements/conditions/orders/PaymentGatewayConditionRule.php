<?php

namespace craft\commerce\elements\conditions\orders;

use Craft;
use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\commerce\elements\db\OrderQuery;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\elements\conditions\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;

/**
 * Payment Gateway condition rule.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.3.0
 */
class PaymentGatewayConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    /**
     * @var string|null Legacy single value property for backwards compatibility
     * @deprecated Use getValues() instead
     */
    public $value;

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Payment Gateway');
    }

    /**
     * @inheritdoc
     */
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

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        // For backwards compatibility: accept 'value' property and convert to 'values'
        $rules[] = [['value'], 'safe'];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function setAttributes($values, $safeOnly = true): void
    {
        // For backwards compatibility: convert single 'value' to 'values' array
        if (isset($values['value']) && !isset($values['values'])) {
            $values['values'] = is_array($values['value']) ? $values['value'] : [$values['value']];
            unset($values['value']);
        }

        parent::setAttributes($values, $safeOnly);
    }

    /**
     * Returns the single value for backwards compatibility
     * @return string|null
     */
    #[\Deprecated(message: 'Use getValues() instead')]
    public function getValue(): ?string
    {
        $values = $this->getValues();
        return !empty($values) ? reset($values) : null;
    }

    /**
     * Sets a single value for backwards compatibility
     * @param string|null $value
     */
    #[\Deprecated(message: 'Use setValues() instead')]
    public function setValue(?string $value): void
    {
        $this->setValues($value ? [$value] : []);
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['gatewayId'];
    }

    /**
     * @inheritdoc
     */
    protected function options(): array
    {
        return Plugin::getInstance()->getGateways()->getAllGateways()->mapWithKeys(fn($gateway) => [$gateway->uid => $gateway->name])->all();
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        $gateways = Plugin::getInstance()->getGateways()->getAllGateways();

        /** @var OrderQuery $query */
        $query->gatewayId($this->paramValue(fn($uid) => $gateways->firstWhere('uid', $uid)?->id));
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Order $element */
        $gatewayUid = $element->getGateway()?->uid ?? '';
        return $this->matchValue($gatewayUid);
    }
}
