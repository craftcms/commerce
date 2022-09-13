<?php

namespace craft\commerce\elements\conditions\customers;

use Craft;
use craft\base\conditions\ConditionRuleInterface;
use craft\base\ElementInterface;
use craft\commerce\elements\conditions\orders\CompletedConditionRule;
use craft\commerce\elements\conditions\orders\CustomerConditionRule;
use craft\commerce\elements\conditions\orders\OrderCondition;
use craft\commerce\elements\db\OrderQuery;
use craft\elements\conditions\IdConditionRule;
use craft\elements\db\ElementQueryInterface;
use yii\base\InvalidConfigException;

/**
 * Customer Orders condition.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2.0
 */
class CustomerOrdersCondition extends OrderCondition
{
    /**
     * @var int|null
     */
    public ?int $customerId = null;

    /**
     * The current order ID, used to prevent it being returned in results.
     *
     * @var int|null
     */
    public ?int $orderId = null;

    private array $_forcedRuleTypes = [
        CustomerConditionRule::class,
        CompletedConditionRule::class,
        IdConditionRule::class,
    ];

    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['customerId', 'orderId'], 'safe'];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    protected function conditionRuleTypes(): array
    {
        // Remove rules that could cause conflicts
        return array_filter(parent::conditionRuleTypes(), fn($ruleType) => !in_array($ruleType, $this->_forcedRuleTypes, true));
    }

    /**
     * @param ElementQueryInterface $query
     * @return void
     * @throws InvalidConfigException
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        if ($this->customerId === null) {
            throw new InvalidConfigException('Customer orders condition requires a customer ID.');
        }

        $this->_addForcedRules();

        /** @var OrderQuery $query */
        parent::modifyQuery($query);

        $this->_removeForcedRules();
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        if ($this->customerId === null) {
            throw new InvalidConfigException('Customer orders condition requires a customer ID.');
        }

        $this->_addForcedRules();

        $return = parent::matchElement($element);

        $this->_removeForcedRules();

        return $return;
    }

    /**
     * @inheritdoc
     */
    protected function validateConditionRule(ConditionRuleInterface $rule): bool
    {
        if (parent::validateConditionRule($rule)) {
            return true;
        }

        return in_array(get_class($rule), $this->_forcedRuleTypes, true);
    }

    /**
     * Add the hardcoded condition rules applicable to
     * @return void
     */
    private function _addForcedRules(): void
    {
        // Add ID condition rule
        $hasIdConditionRule = !empty(array_filter($this->getConditionRules(), static fn($rule) => get_class($rule) === IdConditionRule::class));
        if (!$hasIdConditionRule && $this->orderId !== null) {
            /** @var IdConditionRule $idConditionRule */
            $idConditionRule = Craft::$app->getConditions()->createConditionRule([
                'class' => IdConditionRule::class,
            ]);
            $idConditionRule->value = (string)$this->orderId;
        }

        // Add customer condition rule
        /** @var CustomerConditionRule $customerConditionRule */
        $customerConditionRule = Craft::$app->getConditions()->createConditionRule([
            'class' => CustomerConditionRule::class,
        ]);
        $customerConditionRule->setValues([(string)$this->customerId]);

        // Add completed condition rule
        /** @var CompletedConditionRule $isCompletedConditionRule */
        $isCompletedConditionRule = Craft::$app->getConditions()->createConditionRule([
            'class' => CompletedConditionRule::class,
        ]);
        $isCompletedConditionRule->value = true;

        $this->addConditionRule($customerConditionRule);
        $this->addConditionRule($isCompletedConditionRule);
    }

    /**
     * Remove forced condition rules
     *
     * @return void
     */
    private function _removeForcedRules(): void
    {
        $conditionRules = array_filter($this->getConditionRules(), fn($rule) => !in_array(get_class($rule), $this->_forcedRuleTypes, true));

        $this->setConditionRules($conditionRules);
    }
}
