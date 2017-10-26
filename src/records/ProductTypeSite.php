<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\Site;
use Symfony\Component\Debug\Tests\Fixtures\ClassAlias;
use yii\db\ActiveQuery;
use yii\db\ActiveQueryInterface;

/**
 * Product type locale record.
 *
 * @property int         $productTypeId
 * @property int         $localeId
 * @property string      $uriFormat
 *
 * @property Site        $site
 * @property ProductType $productType
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class ProductTypeSite extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%commerce_producttypes_sites}}';
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getProductType(): ActiveQueryInterface
    {
        return $this->hasOne(ProductType::class, ['id', 'productTypeId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getSite(): ActiveQueryInterface
    {
        return $this->hasOne(Site::class, ['id', 'siteId']);
    }
}
