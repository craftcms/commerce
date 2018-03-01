<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\Category;
use yii\db\ActiveQueryInterface;

/**
 * Sale product type record.
 *
 * @property ActiveQueryInterface $category
 * @property int $categoryId
 * @property int $id
 * @property ActiveQueryInterface $sale
 * @property int $saleId
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class SaleCategory extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%commerce_sale_categories}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['saleId', 'categoryId'], 'unique', 'targetAttribute' => ['saleId', 'categoryId']]
        ];
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getSale(): ActiveQueryInterface
    {
        return $this->hasOne(Sale::class, ['saleId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getCategory(): ActiveQueryInterface
    {
        return $this->hasOne(Category::class, ['saleId' => 'id']);
    }
}
