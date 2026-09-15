<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Customer\Conditions;

use CraftCms\Cms\Condition\BaseNumberConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Support\Facades\Conditions;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Json;
use CraftCms\Commerce\Order\Conditions\CompletedConditionRule;
use CraftCms\Commerce\Order\Conditions\OrderCondition;
use CraftCms\Commerce\Order\Elements\Order;
use Override;
use RuntimeException;

use function CraftCms\Cms\t;

class HasOrdersConditionRule extends BaseNumberConditionRule implements ElementConditionRuleInterface
{
    /** @see getOrderCondition() */
    private OrderCondition|array|null $_orderCondition = null;

    /** @var array<string, bool> */
    private static array $_orderConditionResults = [];

    #[Override]
    public function getConfig(): array
    {
        return array_merge(parent::getConfig(), [
            'orderCondition' => $this->getOrderCondition()->getConfig(),
        ]);
    }

    #[Override]
    public function getRules(): array
    {
        return array_merge(parent::getRules(), [
            'orderCondition' => ['nullable'],
        ]);
    }

    public function getLabel(): string
    {
        return t('Has Orders', category: 'commerce');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['hasOrders'];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        throw new RuntimeException('Has orders condition rule does not support queries');
    }

    #[Override]
    public function getHtml(): string
    {
        $html = Html::tag('label', t('Total Orders', category: 'commerce'), [
            'style' => [
                'padding-top' => '0.25rem',
                'padding-bottom' => '0.5rem',
                'font-weight' => 'bold',
                'color' => '#596673',
                'display' => 'block',
            ],
        ]);
        $html .= parent::getHtml();
        $html .= Html::tag('div', t('Match Orders', category: 'commerce'), [
            'style' => [
                'margin-top' => '1rem',
                'font-weight' => 'bold',
                'color' => '#596673',
            ],
        ]);
        $html .= Html::tag('div', $this->getOrderCondition()->getBuilderHtml(), ['style' => ['margin-top' => '0.5rem']]);

        return $html;
    }

    public function matchElement(ElementInterface $element): bool
    {
        $orderQuery = Order::find()->customerId($element->id);
        $this->getOrderCondition()->modifyQuery($orderQuery);
        $key = md5(implode('||', [
            $element->id,
            Json::encode($this->getConfig()),
        ]));

        if (!isset(self::$_orderConditionResults[$key])) {
            self::$_orderConditionResults[$key] = $this->matchValue($orderQuery->count());
        }

        return self::$_orderConditionResults[$key];
    }

    public function getOrderCondition(): OrderCondition
    {
        if ($this->_orderCondition === null) {
            /** @var OrderCondition $orderCondition */
            $orderCondition = Conditions::createCondition(['class' => OrderCondition::class]);
            $this->_orderCondition = $orderCondition;

            // Set default rules
            /** @var CompletedConditionRule $completedConditionRule */
            $completedConditionRule = Conditions::createConditionRule([
                'class' => CompletedConditionRule::class,
            ]);
            $completedConditionRule->value = true;

            $this->_orderCondition->addConditionRule($completedConditionRule);
        } elseif (is_array($this->_orderCondition)) {
            /** @var OrderCondition $orderCondition */
            $orderCondition = Conditions::createCondition($this->_orderCondition);
            $this->_orderCondition = $orderCondition;
        }

        $this->_orderCondition->id = 'hasOrdersOrderCondition';
        $this->_orderCondition->mainTag = 'div';
        $this->_orderCondition->name = 'orderCondition';
        // Exclude unwanted condition rules
        $this->_orderCondition->queryParams = ['customerId'];

        return $this->_orderCondition;
    }

    public function setOrderCondition(OrderCondition|array|null $condition): void
    {
        $this->_orderCondition = $condition;
    }
}
