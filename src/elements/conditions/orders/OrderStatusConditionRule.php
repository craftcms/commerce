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
use craft\commerce\Plugin;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ArrayHelper;
use yii\base\InvalidConfigException;

/**
 * Order Status Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2.0
 *
 * @method array|string|null paramValue(?callable $normalizeValue = null)
 */
class OrderStatusConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Order Status');
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['orderStatus'];
    }

    /**
     * @param ElementQueryInterface $query
     * @return void
     * @throws InvalidConfigException
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        $orderStatuses = Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses();

        /** @var OrderQuery $query */
        $query->orderStatus($this->paramValue(fn(string $value) => ArrayHelper::firstWhere($orderStatuses, 'uid', $value)?->handle));
    }

    /**
     * @param ElementInterface $element
     * @return bool
     * @throws InvalidConfigException
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Order $element */
        $orderStatusUid = $element->getOrderStatus()?->uid;
        return $this->matchValue($orderStatusUid);
    }

    protected function options(): array
    {
        return ArrayHelper::map(Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses(), 'uid', 'name');
    }
}
