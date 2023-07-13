<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace  craft\commerce\elements\conditions\products;

use Craft;
use craft\base\conditions\BaseNumberConditionRule;
use craft\base\conditions\BaseTextConditionRule;
use craft\base\ElementInterface;
use craft\commerce\elements\db\ProductQuery;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use yii\base\InvalidConfigException;

/**
 * Product Variant Stock Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.3.0
 */
class ProductVariantStockConditionRule extends BaseNumberConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Variant Stock');
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['variantStock'];
    }

    /**
     * @param ElementQueryInterface $query
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        $variantQuery = Variant::find();
        $variantQuery->select(['commerce_variants.productId as id']);
        $variantQuery->hasUnlimitedStock(false);
        $variantQuery->stock($this->paramValue());

        /** @var ProductQuery $query */
        $query->andWhere(['elements.id' => $variantQuery]);
    }

    /**
     * @throws InvalidConfigException
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Product $element */
        foreach ($element->getVariants() as $variant) {
            if ($variant->hasUnlimitedStock === false && $this->matchValue($variant->stock)) {
                // Skip out early if we have a match
                return true;
            }
        }

        return false;
    }
}
