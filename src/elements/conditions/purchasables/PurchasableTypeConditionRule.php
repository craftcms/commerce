<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\conditions\purchasables;

use Craft;
use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\commerce\base\Purchasable;
use craft\commerce\Plugin;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Db;

class PurchasableTypeConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Purchasable Type');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['purchasableType'];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        $query->andWhere(Db::parseParam('type',$this->paramValue()));
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Purchasable $element */
        return $this->matchValue(get_class($element));
    }

    /**
     * @inheritdoc
     */
    protected function options(): array
    {
        $elementTypes = Plugin::getInstance()->getPurchasables()->getAllPurchasableElementTypes();

        $types = [];
        foreach ($elementTypes as $elementType) {
            $types[$elementType] = $elementType::displayName();
        }

        return $types;
    }
}
