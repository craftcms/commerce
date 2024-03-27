<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\conditions\purchasables;

use Craft;
use craft\base\conditions\BaseTextConditionRule;
use craft\base\ElementInterface;
use craft\commerce\base\Purchasable;
use craft\commerce\db\Table;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Db;

class SkuConditionRule extends BaseTextConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return Craft::t('commerce', 'SKU');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['sku'];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        // Awkward table alias just to avoid a conflict with other rules
        $query->leftJoin(Table::PURCHASABLES . ' skuconpurch', '[[skuconpurch.id]] = [[elements.id]]');
        $query->andWhere(Db::parseParam('[[skuconpurch.sku]]',$this->paramValue()));
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Purchasable $element */
        return $this->matchValue($element->getSku());
    }
}
