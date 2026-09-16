<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Customer\Conditions;

use CraftCms\Cms\Condition\BaseNumberConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementQueryConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Form\Contracts\Node;
use CraftCms\Cms\Form\Controls\ConditionBuilder;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Support\Facades\Conditions;
use CraftCms\Cms\Support\Json;
use CraftCms\Commerce\Order\Conditions\CompletedConditionRule;
use CraftCms\Commerce\Order\Conditions\OrderCondition;
use CraftCms\Commerce\Order\Elements\Order;
use Illuminate\Database\Query\Builder;
use Override;
use RuntimeException;

use function CraftCms\Cms\t;

class HasOrdersConditionRule extends BaseNumberConditionRule implements ElementConditionRuleInterface, ElementQueryConditionRuleInterface
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

    public function modifyQuery(Builder $query, ElementQueryInterface $elementQuery): void
    {
        throw new RuntimeException('Has orders condition rule does not support queries');
    }

    /** @return list<Node> */
    #[Override]
    protected function inputNodes(): array
    {
        return [
            ...parent::inputNodes(),
            Field::make(t('Match Orders', category: 'commerce'), ConditionBuilder::make('orderCondition')
                ->conditionClass(OrderCondition::class)
                ->queryParams(['customerId'])),
        ];
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
