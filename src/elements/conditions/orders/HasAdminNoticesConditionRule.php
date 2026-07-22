<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\conditions\orders;

use Craft;
use craft\base\conditions\BaseLightswitchConditionRule;
use craft\base\ElementInterface;
use craft\commerce\elements\db\OrderQuery;
use craft\commerce\elements\Order;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;

/**
 * Has Admin Notices Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.x
 */
class HasAdminNoticesConditionRule extends BaseLightswitchConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Has Admin Notices');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['hasAdminNotices'];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var OrderQuery $query */
        $query->hasAdminNotices($this->value);
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Order $element */
        return $element->hasAdminNotices() === $this->value;
    }
}
