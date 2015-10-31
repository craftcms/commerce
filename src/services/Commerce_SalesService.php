<?php
namespace Craft;

use Commerce\Helpers\CommerceDbHelper;

/**
 * Sale service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_SalesService extends BaseApplicationComponent
{
    /**
     * @param int $id
     *
     * @return Commerce_SaleModel|null
     */
    public function getById($id)
    {
        $result = Commerce_SaleRecord::model()->findById($id);

        if ($result) {
            return Commerce_SaleModel::populateModel($result);
        }

        return null;
    }

    /**
     * Getting all sales applicable for the current user and given product
     *
     * @param Commerce_ProductModel $product
     *
     * @return Commerce_SaleModel[]
     */
    public function getForProduct(Commerce_ProductModel $product)
    {
        $productIds = [$product->id];
        $productTypeIds = [$product->typeId];

        return $this->getAllByConditions($productIds, $productTypeIds);
    }

    /**
     * @param $productIds
     * @param $productTypeIds
     *
     * @return Commerce_SaleModel[]
     */
    private function getAllByConditions($productIds, $productTypeIds)
    {
        $criteria = new \CDbCriteria();
        $criteria->group = 't.id';
        $criteria->addCondition('t.enabled = 1');
        $criteria->addCondition('t.dateFrom IS NULL OR t.dateFrom <= NOW()');
        $criteria->addCondition('t.dateTo IS NULL OR t.dateTo >= NOW()');

        $criteria->join = 'LEFT JOIN {{' . Commerce_SaleProductRecord::model()->getTableName() . '}} sp ON sp.saleId = t.id ';
        $criteria->join .= 'LEFT JOIN {{' . Commerce_SaleProductTypeRecord::model()->getTableName() . '}} spt ON spt.saleId = t.id ';
        $criteria->join .= 'LEFT JOIN {{' . Commerce_SaleUserGroupRecord::model()->getTableName() . '}} sug ON sug.saleId = t.id ';

        if ($productIds) {
            $list = implode(',', $productIds);
            $criteria->addCondition("sp.productId IN ($list) OR t.allProducts = 1");
        } else {
            $criteria->addCondition("t.allProducts = 1");
        }

        if ($productTypeIds) {
            $list = implode(',', $productTypeIds);
            $criteria->addCondition("spt.productTypeId IN ($list) OR t.allProductTypes = 1");
        } else {
            $criteria->addCondition("t.allProductTypes = 1");
        }

        $groupIds = craft()->commerce_discounts->getCurrentUserGroups();
        if ($groupIds) {
            $list = implode(',', $groupIds);
            $criteria->addCondition("sug.userGroupId IN ($list) OR t.allGroups = 1");
        } else {
            $criteria->addCondition("t.allGroups = 1");
        }

        //searching
        return $this->getAll($criteria);
    }

    /**
     * @param array|\CDbCriteria $criteria
     *
     * @return Commerce_SaleModel[]
     */
    public function getAll($criteria = [])
    {
        $records = Commerce_SaleRecord::model()->findAll($criteria);

        return Commerce_SaleModel::populateModels($records);
    }

    /**
     * @param Commerce_VariantModel $variant
     *
     * @return Commerce_SaleModel[]
     */
    public function getForVariant(Commerce_VariantModel $variant)
    {
        $productIds = [$variant->productId];
        $productTypeIds = [$variant->product->typeId];

        return $this->getAllByConditions($productIds, $productTypeIds);
    }

    /**
     * @param Commerce_ProductModel $product
     * @param Commerce_SaleModel $sale
     *
     * @return bool
     */
    public function matchProduct(
        Commerce_ProductModel $product,
        Commerce_SaleModel $sale
    )
    {
        if (!$sale->allProducts && !in_array($product->id,
                $sale->getProductsIds())
        ) {
            return false;
        }

        if (!$sale->allProductTypes && !in_array($product->typeId,
                $sale->getProductTypesIds())
        ) {
            return false;
        }

        $userGroups = craft()->commerce_discounts->getCurrentUserGroups();
        if (!$sale->allGroups && !array_intersect($userGroups,
                $sale->getGroupsIds())
        ) {
            return false;
        }

        return true;
    }

    /**
     * @param Commerce_SaleModel $model
     * @param array $groups ids
     * @param array $productTypes ids
     * @param array $products ids
     *
     * @return bool
     * @throws \Exception
     */
    public function save(
        Commerce_SaleModel $model,
        array $groups,
        array $productTypes,
        array $products
    )
    {
        if ($model->id) {
            $record = Commerce_SaleRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No sale exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new Commerce_SaleRecord();
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

        CommerceDbHelper::beginStackedTransaction();
        try {
            if (!$model->hasErrors()) {
                $record->save(false);
                $model->id = $record->id;

                Commerce_SaleUserGroupRecord::model()->deleteAllByAttributes(['saleId' => $model->id]);
                Commerce_SaleProductRecord::model()->deleteAllByAttributes(['saleId' => $model->id]);
                Commerce_SaleProductTypeRecord::model()->deleteAllByAttributes(['saleId' => $model->id]);

                foreach ($groups as $groupId) {
                    $relation = new Commerce_SaleUserGroupRecord;
                    $relation->attributes = [
                        'userGroupId' => $groupId,
                        'saleId' => $model->id
                    ];
                    $relation->insert();
                }

                foreach ($productTypes as $productTypeId) {
                    $relation = new Commerce_SaleProductTypeRecord;
                    $relation->attributes = [
                        'productTypeId' => $productTypeId,
                        'saleId' => $model->id
                    ];
                    $relation->insert();
                }

                foreach ($products as $productId) {
                    $relation = new Commerce_SaleProductRecord;
                    $relation->attributes = [
                        'productId' => $productId,
                        'saleId' => $model->id
                    ];
                    $relation->insert();
                }

                CommerceDbHelper::commitStackedTransaction();

                return true;
            }
        } catch (\Exception $e) {
            CommerceDbHelper::rollbackStackedTransaction();
            throw $e;
        }

        CommerceDbHelper::rollbackStackedTransaction();

        return false;
    }

    /**
     * @param int $id
     */
    public function deleteById($id)
    {
        Commerce_SaleRecord::model()->deleteByPk($id);
    }
}
