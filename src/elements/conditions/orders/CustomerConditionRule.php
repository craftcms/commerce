<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace  craft\commerce\elements\conditions\orders;

use Craft;
use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\commerce\elements\db\OrderQuery;
use craft\commerce\elements\Order;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use yii\base\InvalidConfigException;

/**
 * Customer Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2.0
 */
class CustomerConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Customer');
    }

    /**
     * @return array
     */
    protected function options(): array
    {
        return User::find()
            ->status(null)
            ->limit(null)
            ->indexBy('id')
            ->collect()
            ->map(fn(User $customer) => $customer->fullName ? sprintf('%s (%s)', $customer->fullName, $customer->email) : $customer->email)
            ->all();
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['customerId'];
    }

    /**
     * @throws InvalidConfigException
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var OrderQuery $query */
        $paramValue = $this->paramValue();;
        if ($this->operator === self::OPERATOR_NOT_IN) {
            $paramValue = ['or', $paramValue, null];
        }

        $query->customerId($paramValue);
    }

    /**
     * @throws InvalidConfigException
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Order $element */
        return $this->matchValue((string)$element->getCustomerId());
    }
}
