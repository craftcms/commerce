<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace  craft\commerce\elements\conditions\products;

use Craft;
use craft\base\conditions\BaseTextConditionRule;
use craft\base\ElementInterface;
use craft\commerce\elements\db\ProductQuery;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;

/**
 * Product Variant SKU Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.3.0
 */
class ProductVariantSkuConditionRule extends BaseTextConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Variant SKU');
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    /**
     * @param ElementQueryInterface $query
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        $variantQuery = Variant::find();
        $variantQuery->select(['commerce_variants.productId as id']);
        $variantQuery->sku($this->paramValue());

        /** @var ProductQuery $query */
        $query->andWhere(['elements.id' => $variantQuery]);
    }

    /**
     * @param Product $element
     */
    public function matchElement(ElementInterface $element): bool
    {
        foreach ($element->getVariants() as $variant) {
            if ($this->matchValue($variant->sku)) {
                // Skip out early if we have a match
                return true;
            }
        }

        return false;
    }
}
