<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\models\Sale;
use craft\commerce\Plugin;
use craft\commerce\records\Sale as SaleRecord;
use craft\commerce\records\SaleProduct as SaleProductRecord;
use craft\commerce\records\SaleProductType as SaleProductTypeRecord;
use craft\commerce\records\SaleUserGroup as SaleUserGroupRecord;
use craft\db\Query;
use yii\base\Component;

/**
 * Sale service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Sales extends Component
{
    /**
     * @var Sale[]
     */
    private $_allSales;

    /**
     * @var Sale[]
     */
    private $_allActiveSales;

    /**
     * @param int $id
     *
     * @return Sale|null
     */
    public function getSaleById($id)
    {
        foreach ($this->getAllSales() as $sale) {
            if ($sale->id == $id) {
                return $sale;
            }
        }

        return null;
    }

    /**
     * @return Sale[]
     */
    public function getAllSales()
    {
        if (null === $this->_allSales) {
            $sales = (new Query())->select(
                'sales.id,
                sales.name,
                sales.description,
                sales.dateFrom,
                sales.dateTo,
                sales.discountType,
                sales.discountAmount,
                sales.allGroups,
                sales.allProducts,
                sales.allProductTypes,
                sales.enabled,
                sp.productId,
                spt.productTypeId,
                sug.userGroupId')
                ->from('commerce_sales sales')
                ->leftJoin('commerce_sale_products sp', 'sp.saleId=sales.id')
                ->leftJoin('commerce_sale_producttypes spt', 'spt.saleId=sales.id')
                ->leftJoin('commerce_sale_usergroups sug', 'sug.saleId=sales.id')
                ->all();

            $allSalesById = [];
            $products = [];
            $productTypes = [];
            $groups = [];

            foreach ($sales as $sale) {
                $id = $sale['id'];
                if ($sale['productId']) {
                    $products[$id][] = $sale['productId'];
                }

                if ($sale['productTypeId']) {
                    $productTypes[$id][] = $sale['productTypeId'];
                }

                if ($sale['userGroupId']) {
                    $groups[$id][] = $sale['userGroupId'];
                }

                unset($sale['productId'], $sale['userGroupId'], $sale['productTypeId']);

                if (!isset($allSalesById[$id])) {
                    $allSalesById[$id] = new Sale($sale);
                }
            }

            foreach ($allSalesById as $id => $sale) {
                $sale->setProductIds($products[$id] ?? []);
                $sale->setProductTypeIds($productTypes[$id] ?? []);
                $sale->setUserGroupIds($groups[$id] ?? []);
            }

            $this->_allSales = $allSalesById;
        }

        return $this->_allSales;
    }

    /**
     * Populate a sale's relations.
     *
     * @param Sale $sale
     *
     * @return void
     */
    public function populateSaleRelations(Sale $sale) {
        $rows = (new Query())->select(
           'sp.productId,
            spt.productTypeId,
            sug.userGroupId')
            ->from('commerce_sales sales')
            ->leftJoin('commerce_sale_products sp', 'sp.saleId=sales.id')
            ->leftJoin('commerce_sale_producttypes spt', 'spt.saleId=sales.id')
            ->leftJoin('commerce_sale_usergroups sug', 'sug.saleId=sales.id')
            ->where(['sales.id' => $sale->id])
            ->all();

        $productIds = [];
        $productTypeIds = [];
        $userGroupIds = [];

        foreach ($rows as $row) {
            if ($row['productId']) {
                $productIds[] = $row['productId'];
            }

            if ($row['productTypeId']) {
                $productTypeIds[] = $row['productTypeId'];
            }

            if ($row['userGroupId']) {
                $userGroupIds[] = $row['userGroupId'];
            }
        }

        $sale->setProductIds($productIds);
        $sale->setProductTypeIds($productTypeIds);
        $sale->setUserGroupIds($userGroupIds);
    }
    
    /**
     * @param Product $product
     *
     * @return Sale[]
     */
    public function getSalesForProduct(Product $product)
    {
        $matchedSales = [];
        foreach ($this->_getAllActiveSales() as $sale) {
            if ($this->matchProductAndSale($product, $sale)) {
                $matchedSales[] = $sale;
            }
        }

        return $matchedSales;
    }

    private function _getAllActiveSales()
    {
        if (null === $this->_allActiveSales) {
            $sales = $this->getAllSales();
            $activeSales = [];
            foreach ($sales as $sale) {
                if ($sale->enabled) {
                    $from = $sale->dateFrom;
                    $to = $sale->dateTo;
                    $now = new \DateTime();
                    if ($from == null || $from < $now) {
                        if ($to == null || $to > $now) {
                            $activeSales[] = $sale;
                        }
                    }
                }
            }

            $this->_allActiveSales = $activeSales;
        }

        return $this->_allActiveSales;
    }

    /**
     * @param Product $product
     * @param Sale    $sale
     *
     * @return bool
     */
    public function matchProductAndSale(Product $product, Sale $sale)
    {
        // can't match something not promotable
        if (!$product->promotable) {
            return false;
        }

        // Product ID match
        if (!$sale->allProducts && !in_array($product->id, $sale->getProductIds())) {
            return false;
        }

        // Product Type match
        if (!$sale->allProductTypes && !in_array($product->typeId, $sale->getProductTypeIds())) {
            return false;
        }

        if (!$sale->allGroups) {
            // User Group match
            $userGroups = Plugin::getInstance()->getDiscounts()->getCurrentUserGroupIds();
            if (!$userGroups || !array_intersect($userGroups, $sale->getUserGroupIds())) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param Variant $variant
     *
     * @return Sale[]
     */
    public function getSalesForVariant(Variant $variant)
    {
        $matchedSales = [];
        foreach ($this->_getAllActiveSales() as $sale) {
            if ($this->matchProductAndSale($variant->product, $sale)) {
                $matchedSales[] = $sale;
            }
        }

        return $matchedSales;
    }

    /**
     * @param Sale  $model
     * @param array $groups       ids
     * @param array $productTypes ids
     * @param array $products     ids
     *
     * @return bool
     * @throws \Exception
     */
    public function saveSale(Sale $model, array $groups, array $productTypes, array $products) {
        if ($model->id) {
            $record = SaleRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No sale exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new SaleRecord();
        }

        $fields = [
            'id',
            'name',
            'description',
            'dateFrom',
            'dateTo',
            'discountType',
            'discountAmount',
            'enabled'
        ];
        foreach ($fields as $field) {
            $record->$field = $model->$field;
        }

        $record->allGroups = $model->allGroups = empty($groups);
        $record->allProductTypes = $model->allProductTypes = empty($productTypes);
        $record->allProducts = $model->allProducts = empty($products);

        $record->validate();
        $model->addErrors($record->getErrors());

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            if (!$model->hasErrors()) {
                $record->save(false);
                $model->id = $record->id;

                SaleUserGroupRecord::deleteAll(['saleId' => $model->id]);
                SaleProductRecord::deleteAll(['saleId' => $model->id]);
                SaleProductTypeRecord::deleteAll(['saleId' => $model->id]);

                foreach ($groups as $groupId) {
                    $relation = new SaleUserGroupRecord();
                    $relation->userGroupId = $groupId;
                    $relation->saleId = $model->id;
                    $relation->save();
                }

                foreach ($productTypes as $productTypeId) {
                    $relation = new SaleProductTypeRecord;
                    $relation->productTypeId = $productTypeId;
                    $relation->saleId = $model->id;
                    $relation->save();
                }

                foreach ($products as $productId) {
                    $relation = new SaleProductRecord();
                    $relation->productId = $productId;
                    $relation->saleId = $model->id;
                    $relation->save();
                }

                $transaction->commit();

                return true;
            }
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        $transaction->rollBack();

        return false;
    }

    /**
     * @param int $id
     *
     * @return bool
     */
    public function deleteSaleById($id): bool
    {
        $sale = SaleRecord::findOne($id);

        if ($sale) {
            return $sale->delete();
        }
        
        return false;
    }
}
