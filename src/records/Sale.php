<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\UserGroup;
use yii\db\ActiveQueryInterface;

/**
 * Sale record.
 *
 * @property int           $id
 * @property string        $name
 * @property string        $description
 * @property \DateTime     $dateFrom
 * @property \DateTime     $dateTo
 * @property string        $discountType
 * @property float         $discountAmount
 * @property bool          $allGroups
 * @property bool          $allProducts
 * @property bool          $allProductTypes
 * @property bool          $enabled
 *
 * @property Product[]     $products
 * @property ProductType[] $productTypes
 * @property UserGroup[]   $groups
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Sale extends ActiveRecord
{
    const TYPE_PERCENT = 'percent';
    const TYPE_FLAT = 'flat';

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%commerce_sales}}';
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['discountType'], 'required']
        ];
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getGroups(): ActiveQueryInterface
    {
        return $this->hasMany(UserGroup::class, ['id' => 'userGroupId'])->viaTable('{{%commerce_sale_usergroup}}', ['saleId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getProducts(): ActiveQueryInterface
    {
        return $this->hasMany(Product::class, ['id' => 'productId'])->viaTable('{{%commerce_sale_products}}', ['saleId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getProductTypes(): ActiveQueryInterface
    {
        return $this->hasMany(ProductType::class, ['id' => 'productTypeId'])->viaTable('{{%commerce_sale_productypes}}', ['saleId' => 'id']);
    }
}