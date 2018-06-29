<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\db;

use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\Plugin;
use craft\db\QueryAbortedException;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use yii\db\Connection;

/**
 * VariantQuery represents a SELECT SQL statement for variants in a way that is independent of DBMS.
 * @method Variant[]|array all($db = null)
 * @method Variant|array|null one($db = null)
 * @method Variant|array|null nth(int $n, Connection $db = null)
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class VariantQuery extends ElementQuery
{
    // Properties
    // =========================================================================

    /**
     * @var string the SKU of the variant
     */
    public $sku;

    /**
     * @var bool Whether to only return variants that the user has permission to edit.
     */
    public $editable = false;

    /**
     * @var Product
     */
    public $product;

    /**
     * @var
     */
    public $productId;

    /**
     * @var
     */
    public $typeId;

    /**
     * @var
     */
    public $isDefault;

    /**
     * @var
     */
    public $stock;

    /**
     * @var
     */
    public $hasStock;

    /**
     * @var
     */
    public $price;

    /**
     * @var
     */
    public $hasSales;

    /**
     * @inheritdoc
     */
    protected $defaultOrderBy = ['commerce_variants.sortOrder' => SORT_ASC];


    // Public Methods
    // =========================================================================

    /**
     * @param $value
     * @return $this
     */
    public function sku($value)
    {
        $this->sku = $value;

        return $this;
    }

    /**
     * @param $value
     * @return $this
     */
    public function product($value)
    {
        $this->product = $value;

        return $this;
    }

    /**
     * @param $value
     * @return $this
     */
    public function productId($value)
    {
        $this->productId = $value;

        return $this;
    }

    /**
     * @param $value
     * @return $this
     */
    public function typeId($value)
    {
        $this->typeId = $value;

        return $this;
    }


    /**
     * @param $value
     * @return $this
     */
    public function isDefault($value)
    {
        $this->isDefault = $value;

        return $this;
    }

    /**
     * @param $value
     * @return $this
     */
    public function stock($value)
    {
        $this->stock = $value;

        return $this;
    }

    /**
     * @param $value
     * @return $this
     */
    public function price($value)
    {
        $this->price = $value;

        return $this;
    }

    /**
     * @param $value
     * @return $this
     */
    public function hasStock($value)
    {
        $this->hasStock = $value;

        return $this;
    }

    /**
     * @param $value
     * @return $this
     */
    public function hasSales($value)
    {
        $this->hasSales = $value;

        return $this;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('commerce_variants');

        $this->query->select([
            'commerce_variants.id',
            'commerce_variants.productId',
            'commerce_variants.isDefault',
            'commerce_variants.sku',
            'commerce_variants.price',
            'commerce_variants.sortOrder',
            'commerce_variants.width',
            'commerce_variants.height',
            'commerce_variants.length',
            'commerce_variants.weight',
            'commerce_variants.stock',
            'commerce_variants.hasUnlimitedStock',
            'commerce_variants.minQty',
            'commerce_variants.maxQty'
        ]);

        $this->subQuery->leftJoin('{{%commerce_products}} commerce_products', '[[commerce_variants.productId]] = [[commerce_products.id]]');

        if ($this->typeId) {
            $this->subQuery->andWhere(Db::parseParam('commerce_products.typeId', $this->typeId));
        }

        if ($this->sku) {
            $this->subQuery->andWhere(Db::parseParam('commerce_variants.sku', $this->sku));
        }

        if ($this->product) {
            if ($this->product instanceof Product) {
                $this->productId = $this->product->id;
            } else {
                $this->subQuery->andWhere(Db::parseParam('commerce_variants.productId', $this->product));
            }
        }

        if ($this->productId) {
            $this->subQuery->andWhere(Db::parseParam('commerce_variants.productId', $this->productId));
        }

        if ($this->price) {
            $this->subQuery->andWhere(Db::parseParam('commerce_variants.price', $this->price));
        }

        if ($this->isDefault) {
            $this->subQuery->andWhere(Db::parseParam('commerce_variants.isDefault', $this->isDefault));
        }

        if ($this->stock) {
            $this->subQuery->andWhere(Db::parseParam('commerce_variants.stock', $this->stock));
        }

        if (null !== $this->hasStock && $this->hasStock === true) {
            $hasStockCondition = ['or', '(commerce_variants.stock > 0 AND commerce_variants.hasUnlimitedStock != 1)', 'commerce_variants.hasUnlimitedStock = 1'];
            $this->subQuery->andWhere($hasStockCondition);
        }

        if (null !== $this->hasStock && $this->hasStock === false) {
            $hasStockCondition = ['and', 'commerce_variants.stock < 1', 'commerce_variants.hasUnlimitedStock != 1'];
            $this->subQuery->andWhere($hasStockCondition);
        }

        if (null !== $this->hasSales) {
            $query = Variant::find();
            $query->hasSales = null;
            $query->limit = null;
            $variants = $query->all();

            $ids = [];
            foreach ($variants as $variant) {
                $sales = Plugin::getInstance()->getSales()->getSalesForPurchasable($variant);

                if ($this->hasSales === true && count($sales) > 0) {
                    $ids[] = $variant->id;
                }

                if ($this->hasSales === false && count($sales) == 0) {
                    $ids[] = $variant->id;
                }
            }

            $this->subQuery->andWhere(['in', 'commerce_variants.id', $ids]);
        }

        return parent::beforePrepare();
    }
}
