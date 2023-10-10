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
use craft\helpers\Cp;
use craft\helpers\Db;
use yii\base\InvalidConfigException;
use yii\db\Expression;

/**
 * Customer Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2.0
 * @TODO change the class that the `CustomerConditionRule` extends
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
     * @deprecated in 4.3.1.
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
     * @inheritDoc
     */
    protected function inputHtml(): string
    {
        $users = User::find()->status(null)->limit(null)->id($this->values)->all();

        return Cp::elementSelectHtml([
            'name' => 'values',
            'elements' => $users,
            'elementType' => User::class,
            'sources' => null,
            'criteria' => null,
            'condition' => null,
            'single' => false,
        ]);
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
        $paramValue = $this->paramValue();
        if ($this->operator === self::OPERATOR_NOT_IN) {
            // Account for the fact the querying using a combination of `not` and `in` doesn't match `null` in the column
            $query->andWhere(Db::parseParam(new Expression('coalesce([[commerce_orders.customerId]], -1)'), $paramValue));
        } else {
            $query->customerId($paramValue);
        }
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
