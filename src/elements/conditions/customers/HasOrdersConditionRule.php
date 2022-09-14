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
use craft\helpers\Json;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;

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
     * @var array|OrderCondition|null
     */
    private OrderCondition|array|null $_orderCondition = null;

    /**
     * @var array
     */
    private static array $_orderConditionResults = [];

    public function getConfig(): array
    {
        return array_merge(parent::getConfig(), [
            'orderCondition' => $this->getOrderCondition()->getConfig(),
        ]);
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['orderCondition'], 'safe'];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Has Orders');
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['hasOrders'];
    }

    /**
     * @param ElementQueryInterface $query
     * @return void
     * @throws NotSupportedException
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        throw new NotSupportedException('Has orders condition rule does not support queries');
    }

    /**
     * @return string
     * @throws InvalidConfigException
     */
    public function getHtml(): string
    {
        $html = Html::label(Craft::t('commerce', 'Total Orders'), options: [
            'style' => [
                'padding-top' => '0.25rem',
                'padding-bottom' => '0.5rem',
                'font-weight' => 'bold',
                'color' => '#596673',
                'display' => 'block',
            ],
        ]);
        $html .= parent::getHtml();
        $html .= Html::tag('div', Craft::t('commerce', 'Match Orders'), [
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
        $key = md5(implode('||', [
            $element->id,
            Json::encode($this),
            Json::encode($orderQuery),
        ]));

        if (!isset(self::$_orderConditionResults[$key])) {
            self::$_orderConditionResults[$key] = $this->matchValue($orderQuery->count());
        }

        return self::$_orderConditionResults[$key];
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
        // Exclude unwanted condition rules
        $this->_orderCondition->queryParams = ['customerId'];
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
