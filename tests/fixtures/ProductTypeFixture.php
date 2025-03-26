<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\records\ProductType;
use craft\test\ActiveFixture;

/**
 * Product Type Fixture
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.4
 */
class ProductTypeFixture extends ActiveFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__ . '/data/product-types.php';

    /**
     * @inheritdoc
     */
    public $modelClass = ProductType::class;

    /**
     * @inheritdoc
     */
    public $depends = [
        ShippingCategoryFixture::class,
        ProductTypesShippingCategoriesFixture::class,
        TaxCategoryFixture::class,
        ProductTypesTaxCategoriesFixture::class,
        ProductTypeSitesFixture::class,
        FieldLayoutFixture::class,
    ];

    /**
     * @inheritdoc
     */
    protected function getData()
    {
        $data = parent::getData();

        foreach ($data as &$record) {
            if (isset($record['_fieldLayout'])) {
                $record['fieldLayoutId'] = \Craft::$app->getFields()->getLayoutByUid($record['_fieldLayout'])?->id ?? null;
                unset($record['_fieldLayout']);
            }

            if (isset($record['_variantFieldLayout'])) {
                $record['variantFieldLayoutId'] = \Craft::$app->getFields()->getLayoutByUid($record['_variantFieldLayout'])?->id ?? null;
                unset($record['_variantFieldLayout']);
            }
        }

        return $data;
    }
}
