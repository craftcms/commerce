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
use yii\base\InvalidConfigException;

/**
 * Product Variant Search Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.7.0
 */
class ProductVariantSearchConditionRule extends BaseTextConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Variant Search');
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    protected function operators(): array
    {
        return [];
    }

    /**
     * Returns the raw search value.
     *
     * Note we can't use [[paramValue()]] here because it prepends the operator
     * (e.g. `=`) intended for [[\craft\helpers\Db::parseParam()]], which would
     * corrupt the value once it's passed to [[\craft\elements\db\ElementQuery::search()]].
     *
     * @return string
     */
    private function searchValue(): string
    {
        return trim((string) $this->value);
    }

    /**
     * @param ElementQueryInterface $query
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        $variantQuery = Variant::find();
        $variantQuery->select(['commerce_variants.primaryOwnerId as id']);
        $variantQuery->search($this->searchValue());

        /** @var ProductQuery $query */
        $query->andWhere(['elements.id' => $variantQuery]);
    }

    /**
     * @param Product $element
     * @return bool
     * @throws InvalidConfigException
     */
    public function matchElement(ElementInterface $element): bool
    {
        $variantIds = $element->getVariants()->pluck('id')->all();
        if (empty($variantIds)) {
            return false;
        }

        // Perform a variant query search to ensure it is the same process as `modifyQuery`
        $variantQuery = Variant::find();
        $variantQuery->search($this->searchValue());
        $variantQuery->id($variantIds);

        return $variantQuery->count() > 0;
    }
}
