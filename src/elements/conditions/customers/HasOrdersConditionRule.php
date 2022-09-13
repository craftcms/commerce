<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace  craft\commerce\elements\conditions\customers;

use Craft;
use craft\base\conditions\BaseNumberConditionRule;
use craft\base\ElementInterface;
use craft\commerce\elements\conditions\orders\CompletedConditionRule;
use craft\commerce\elements\conditions\orders\OrderCondition;
use craft\commerce\elements\Order;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Html;
use yii\base\InvalidConfigException;

/**
 * Is Paid Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2.0
 *
 * @property null|array|OrderCondition $orderCondition
 */
class HasOrdersConditionRule extends BaseNumberConditionRule implements ElementConditionRuleInterface
{
    /**
     * @var int|null
     */
    public ?int $customerId = null;

    /**
     * @var array|OrderCondition|null
     */
    private OrderCondition|array|null $_orderCondition = null;

    public function getConfig(): array
    {
        return array_merge(parent::getConfig(), [
            'orderCondition' => $this->getOrderCondition()->getConfig(),
        ]);
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['customerId', 'orderCondition'], 'safe'];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Has Orders');
    }

    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    /**
     * @param ElementQueryInterface $query
     * @return void
     * @throws InvalidConfigException
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        if ($query->id === null) {
            throw new InvalidConfigException('Has orders condition rule requires a customer ID.');
        }

        $orderQuery = Order::find()->customerId($query->id);
        $this->getOrderCondition()->modifyQuery($orderQuery);

        $query->subQuery->andWhere([$orderQuery->count() => $this->paramValue()]);
    }

    /**
     * @return string
     * @throws InvalidConfigException
     */
    public function getHtml(): string
    {
        $html = parent::getHtml();
        $html .= Html::tag('div', Craft::t('commerce', 'Order Rules'), [
            'style' => [
                'margin-top' => '1rem',
                'font-weight' => 'bold',
                'color' => '#596673',
            ],
        ]);
        $html .= Html::tag('div', $this->getOrderCondition()->getBuilderHtml(), ['style' => ['margin-top' => '0.5rem']]);

        return $html;
    }

    /**
     * @param ElementInterface $element
     * @return bool
     * @throws InvalidConfigException
     */
    public function matchElement(ElementInterface $element): bool
    {
        $orderQuery = Order::find()->customerId($element->id);
        $this->getOrderCondition()->modifyQuery($orderQuery);

        return $this->matchValue($orderQuery->count());
    }

    /**
     * @return OrderCondition
     * @throws InvalidConfigException
     */
    public function getOrderCondition(): OrderCondition
    {
        if ($this->_orderCondition === null) {
            $this->_orderCondition = Craft::$app->getConditions()->createCondition(['class' => OrderCondition::class]);

            // Set default rules
            /** @var CompletedConditionRule $completedConditionRule */
            $completedConditionRule = Craft::$app->getConditions()->createConditionRule([
                'class' => CompletedConditionRule::class,
            ]);
            $completedConditionRule->value = true;

            $this->_orderCondition->addConditionRule($completedConditionRule);
        } elseif (is_array($this->_orderCondition)) {
            /** @var OrderCondition $orderCondition */
            $orderCondition = Craft::$app->getConditions()->createCondition($this->_orderCondition);
            $this->_orderCondition = $orderCondition;
        }

        $this->_orderCondition->id = 'hasOrdersOrderCondition';
        $this->_orderCondition->mainTag = 'div';
        $this->_orderCondition->name = 'orderCondition';
        return $this->_orderCondition;
    }

    /**
     * @param OrderCondition|array|null $condition
     */
    public function setOrderCondition(OrderCondition|array|null $condition): void
    {
        $this->_orderCondition = $condition;
    }
}
