<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace  craft\commerce\elements\conditions\products;

use Craft;
use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\commerce\elements\db\ProductQuery;
use craft\commerce\elements\Product;
use craft\commerce\models\ProductType;
use craft\commerce\Plugin;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ArrayHelper;
use yii\base\InvalidConfigException;

/**
 * Customer Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.3.0
 */
class ProductTypeConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Product Type');
    }

    /**
     * @return array
     */
    protected function options(): array
    {
        return collect(Plugin::getInstance()->getProductTypes()->getAllProductTypes())
            ->map(fn(ProductType $productType) => ['value' => $productType->uid, 'label' => $productType->name])
            ->all();
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['type'];
    }

    /**
     * @throws InvalidConfigException
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes();

        /** @var string[] $value */
        $value = $this->paramValue(fn(string $value) => ArrayHelper::firstWhere($productTypes, 'uid', $value)?->handle);

        /** @var ProductQuery $query */
        $query->type($value);
    }

    /**
     * @throws InvalidConfigException
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Product $element */
        return $this->matchValue($element->getType()->uid);
    }
}
